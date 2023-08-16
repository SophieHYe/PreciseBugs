<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesPager extends TablePager {
	function __construct() {
		$this->mDb = GlobalNewFilesHooks::getGlobalDB( DB_REPLICA, 'gnf_files' );

		if ( $this->getRequest()->getText( 'sort', 'files_date' ) == 'files_date' ) {
			$this->mDefaultDirection = IndexPager::DIR_DESCENDING;
		} else {
			$this->mDefaultDirection = IndexPager::DIR_ASCENDING;
		}

		parent::__construct( $this->getContext() );
	}

	function getFieldNames() {
		static $headers = null;

		$headers = [
			'files_timestamp' => 'listfiles_date',
			'files_dbname'    => 'createwiki-label-dbname',
			'files_name'      => 'listfiles_name',
			'files_url'       => 'listfiles_thumb',
			'files_user'      => 'listfiles_user',
		];

		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		$wiki = $row->files_dbname;

		switch ( $name ) {
			case 'files_timestamp':
				$formatted = htmlspecialchars( $this->getLanguage()->userTimeAndDate( $row->files_timestamp, $this->getUser() ) );
				break;
			case 'files_dbname':
				$formatted = $row->files_dbname;
				break;
			case 'files_url':
				$formatted = "<img src=\"{$row->files_url}\" style=\"width:135px;height:135px;\">";
				break;
			case 'files_name':
				$formatted = "<a href=\"{$row->files_page}\">{$row->files_name}</a>";
				break;
			case 'files_user':
				$formatted = "<a href=\"/wiki/Special:CentralAuth/{$row->files_user}\">{$row->files_user}</a>";
				break;
			default:
				$formatted = "Unable to format $name";
				break;
		}

		return $formatted;
	}

	function getQueryInfo() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$info = [
			'tables' => [ 'gnf_files' ],
			'fields' => [ 'files_dbname', 'files_url', 'files_page', 'files_name', 'files_user', 'files_private', 'files_timestamp' ],
			'conds' => [],
			'joins_conds' => [],
		];

		$mwService = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$mwService->userHasRight( $config->get( 'User' ), 'viewglobalprivatefiles' ) ) {
			$info['conds']['files_private'] = 0;
		}

		return $info;
	}

	function getDefaultSort() {
		return 'files_timestamp';
	}

	function isFieldSortable( $name ) {
		return true;
	}
}
