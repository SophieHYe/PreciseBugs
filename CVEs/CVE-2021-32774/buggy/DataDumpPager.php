<?php

use MediaWiki\MediaWikiServices;

class DataDumpPager extends TablePager {

	private $config = null;
	private $pageTitle;
	private $permissionManager = null;

	public function __construct( IContextSource $context, $pageTitle ) {
		$this->setContext( $context );

		$this->mDb = wfGetDB( DB_PRIMARY );

		if ( $this->getRequest()->getText( 'sort', 'dumps_date' ) == 'dumps_date' ) {
			$this->mDefaultDirection = IndexPager::DIR_DESCENDING;
		} else {
			$this->mDefaultDirection = IndexPager::DIR_ASCENDING;
		}

		parent::__construct( $context );

		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'datadump' );
		$this->pageTitle = $pageTitle;
		$this->permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
	}

	public function getFieldNames() {
		static $headers = null;

		$headers = [
			'dumps_timestamp' => 'listfiles_date',
			'dumps_filename'  => 'datadump-table-header-name',
			'dumps_type'      => 'datadump-table-header-type',
			'dumps_size'      => 'datadump-table-header-size',
			'dumps_status'    => 'datadump-table-header-status',
			'dumps_delete'    => 'datadump-table-header-delete',
		];

		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	public function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'dumps_timestamp':
				$time = isset( $row->dumps_timestamp ) ? $row->dumps_timestamp : '';
				$formatted = htmlspecialchars(
					$this->getLanguage()->userTimeAndDate( $time, $this->getUser() )
				);
				break;
			case 'dumps_type':
				$formatted = htmlspecialchars( $row->dumps_type );
				break;
			case 'dumps_filename';
				$formatted = $this->getDownloadUrl( $row );
				break;
			case 'dumps_status':
				if ( (int)$row->dumps_completed === 1 ) {
					$formatted = wfMessage( 'datadump-table-column-ready' )->text();
				} elseif ( (int)$row->dumps_failed === 1 ) {
					$formatted = wfMessage( 'datadump-table-column-failed' )->text();
				} else {
					$formatted = wfMessage( 'datadump-table-column-queued' )->text();
				}
				break;
			case 'dumps_size':
				$formatted = htmlspecialchars(
					$this->getLanguage()->formatSize( isset( $row->dumps_size ) ? $row->dumps_size : 0 ) );
				break;
			case 'dumps_delete':
				$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

				$query = [
					'action' => 'delete',
					'type' => $row->dumps_type,
					'dump' => $row->dumps_filename
				];

				$formatted = $linkRenderer->makeLink( $this->pageTitle, wfMessage( 'datadump-delete-button' )->text(), [], $query );
				break;
			default:
				$formatted = "Unable to format $name";
				break;
		}

		return $formatted;
	}

	public function getQueryInfo() {
		return [
			'tables' => [ 'data_dump' ],
			'fields' => [ 'dumps_completed', 'dumps_failed', 'dumps_filename', 'dumps_size', 'dumps_timestamp', 'dumps_type' ],
			'conds' => [],
			'joins_conds' => [],
		];
	}

	public function getDefaultSort() {
		return 'dumps_timestamp';
	}

	public function isFieldSortable( $name ) {
		return true;
	}

	public function getForm() {
		$dataDumpConfig = $this->config->get( 'DataDump' );

		$opts = [];

		$user = $this->getContext()->getUser();
		foreach ( $dataDumpConfig as $name => $value ) {
			$perm = $config[$name]['permissions']['generate'] ?? 'generate-dump';
			if ( $this->permissionManager->userHasRight( $user, $perm ) ) {
				$opts[$name] = $name;
			}
		}

		if ( !$opts ) {
			return;
		}

		$formDescriptor = [
			'generatedump' => [
				'type' => 'select',
				'label-message' => 'datadump-label-generate',
				'options' => $opts,
				'name' => 'generatedumptype',
				'required' => true,
			],
		];

		foreach ( $dataDumpConfig as $name => $value ) {
			$type = $dataDumpConfig[$name];

			if ( !( $type['htmlform'] ?? false ) ) {
				continue;
			}

			$htmlform = $type['htmlform'];

			$formDescriptor[ $htmlform['name'] ] = $htmlform;
		}

		$htmlFormGenerate = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'searchForms' );
		$htmlFormGenerate->setMethod( 'post' )
			->setFormIdentifier( 'generateDumpForm' )
			->setSubmitCallback( [ $this, 'onGenerate' ] )
			->prepareForm()
			->show();
	}

	public function onGenerate( array $params ) {
		$out = $this->getOutput();

		$dataDumpDisableGenerate = $this->config->get( 'DataDumpDisableGenerate' );
		if ( $dataDumpDisableGenerate ) {
			$out->addHTML(
				Html::errorBox( wfMessage( 'datadump-generated-disabled' )->escaped() )
			);

			$out->addHTML( 
				'<br />' . Linker::specialLink( 'DataDump', 'datadump-refresh' ) 
			);

			return true;
		}

		$dataDumpConfig = $this->config->get( 'DataDump' );
		$dbName = $this->config->get( 'DBname' );

		foreach ( $dataDumpConfig as $name => $value ) {
			$type = $dataDumpConfig[$name];

			if ( !( $type['htmlform'] ?? false ) ) {
				continue;
			}

			$htmlform = $type['htmlform'];
			
			if ( ( $htmlform['noArgsValue'] ?? '' ) == $params[ $htmlform['name'] ] ) {
				continue;	
			}

			$arguments = $type['generate']['arguments'] ?? [];

			foreach ( $arguments as $arg => $value ) {
				$args[$name]['generate']['arguments'][$arg] = $value . '=' . ( $htmlform['value'] ?? '' ) . $params[ $htmlform['name'] ];
			}
		}

		$type = $params['generatedump'];
		if ( !is_null( $type ) && $type !== '' ) {

			$user = $this->getContext()->getUser();

			$perm = $dataDumpConfig[$type]['permissions']['generate'];
			if ( !$this->permissionManager->userHasRight( $user, $perm) ) {
				throw new PermissionsError( $perm );
			}

			if ( $this->getGenerateLimit( $type ) ) {
				$fileName = $dbName . '_' . $type . '_' .
					bin2hex( random_bytes( 10 ) ) .
						$dataDumpConfig[$type]['file_ending'];
				$this->mDb->insert(
					'data_dump',
					[
						'dumps_completed' => 0,
						'dumps_failed' => 0,
						'dumps_filename' => $fileName,
						'dumps_timestamp' => $this->mDb->timestamp(),
						'dumps_type' => $type
					],
					__METHOD__
				);

				$logEntry = new ManualLogEntry( 'datadump', 'generate' );
				$logEntry->setPerformer( $user );
				$logEntry->setTarget( $this->pageTitle );
				$logEntry->setComment( 'Generated dump' );
				$logEntry->setParameters( [ '4::filename' => $fileName ] );
				$logEntry->publish( $logEntry->insert() );

				$jobParams = [
					'fileName' => $fileName,
					'type' => $type,
					'arguments' => $args[$type]['generate']['arguments'] ?? []
				];

				$job = new DataDumpGenerateJob(
					Title::newFromText( 'Special:DataDump' ), $jobParams );
				JobQueueGroup::singleton()->push( $job );

				$out->addHTML(
					Html::successBox( wfMessage( 'datadump-generated-success' )->escaped() )
				);
			}
		} else {
			return 'Invalid type.';
		}

		return true;
	}

	private function getGenerateLimit( string $type ) {
		$dataDumpConfig = $this->config->get( 'DataDump' );

		if ( isset( $dataDumpConfig[$type]['limit'] ) && $dataDumpConfig[$type]['limit'] ) {
			$db = wfGetDB( DB_PRIMARY );
			$row = $db->selectRow(
				'data_dump',
				'*',
				[
					'dumps_type' => $type
				]
			);

			$limit = $dataDumpConfig[$type]['limit'];

			if ( (int)$row < $limit ) {
				return true;
			} else {
				$this->getOutput()->addHTML(
					Html::errorBox( wfMessage( 'datadump-generated-error', $limit )->escaped() )
				);

				return false;
			}
		}

		return true;
	}
	
	private function getDownloadUrl( object $row ) {
		// Do not create a link if the file has not been created.
		if ( (int)$row->dumps_completed !== 1 ) {
			return $row->dumps_filename;
		}

		// If wgDataDumpDownloadUrl is configured, use that
		// rather than using the internal streamer.
		if ( $this->config->get( 'DataDumpDownloadUrl' ) ) {
			$url = preg_replace(
				'/\$\{filename\}/im',
				$row->dumps_filename,
				$this->config->get( 'DataDumpDownloadUrl' )
			);
			return Linker::makeExternalLink( $url, $row->dumps_filename );
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$query = [
			'action' => 'download',
			'dump' => $row->dumps_filename
		];

		return $linkRenderer->makeLink( $this->pageTitle, $row->dumps_filename, [], $query );
	}
}
