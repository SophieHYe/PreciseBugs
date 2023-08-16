<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/subpages/RequestPage.php';
require_once __DIR__ . '/subpages/RequirementsBypassPage.php';

function truncate(string $str, int $length) : string {
	assert($length >= 0);
	
	return strlen($str) > $length ? substr($str, 0, $length) . '...' : $str;
}

class AccountRequestPager extends AbstractAccountRequestPager {
	const REQUEST_NOTES_TABLE_CELL_MAX_LENGTH = 500;
	private $linkRenderer, $language;
	function __construct($username, $status, $linkRenderer, $language) {
		parent::__construct($username, $status);

		$this->linkRenderer = $linkRenderer;
		$this->language = $language;
	}	

	function rowFromRequest($accountRequest) {
		$row = Html::openElement('tr');
		$row .= Html::rawElement('td', [], humanTimestamp( $accountRequest->lastUpdated, $this->language ));
		$row .= Html::rawElement('td', [], linkToScratchProfile($accountRequest->username));
		$row .= Html::element('td', ['class' => 'mw-scratch-confirmaccount-requestnotestablecell'], truncate($accountRequest->requestNotes, self::REQUEST_NOTES_TABLE_CELL_MAX_LENGTH));
		$row .= Html::rawElement(
			'td',
			[],
			$this->linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor('ConfirmAccounts', $accountRequest->id),
				wfMessage('scratch-confirmaccount-view')->text()
			)
		);
		$row .= Html::closeElement('tr');

		return $row;
	}
}

class SpecialConfirmAccounts extends SpecialPage {
	function __construct() {
		parent::__construct( 'ConfirmAccounts', 'createaccount' );
	}

	function getGroupName() {
		return 'users';
	}
	
	//return if the current user can view/edit blocks
	function canViewBlocks() {		
		return $this->getUser()->isAllowed('block');
	}

	function blocksListPage(&$request, &$output, &$session) {
		$dbr = getReadOnlyDatabase();
		
		$linkRenderer = $this->getLinkRenderer();

		//show the list of existing blocks
		$output->addHTML(Html::element(
			'h3',
			[],
			wfMessage('scratch-confirmaccount-blocks')->text()
		));

		$blocks = getBlocks($dbr);

		if (empty($blocks)) {
			$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-noblocks')));
		} else {
			$table = Html::openElement('table', [ 'class' => 'wikitable' ]);

			//table heading
			$table .= Html::openElement('tr');
			$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-scratchusername'));
			$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-blockreason'));
			$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-actions'));
			$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-block-expiration-time'));
			$table .= Html::closeElement('tr');

			//actual list of blocks
			$table .= implode(array_map(function ($block) use ($linkRenderer) {
				$row = Html::openElement('tr');
				$row .= Html::element('td', [], $block->blockedUsername);
				$row .= Html::element('td', [], $block->reason);
				$row .= Html::rawElement('td', [], $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-blocks')->text() . '/' . $block->blockedUsername),
					wfMessage('scratch-confirmaccount-view')->text()
				));
				$row .= Html::rawElement('td', [], humanTimestampOrInfinite($block->expirationTimestamp, $this->getLanguage()));
				$row .= Html::closeElement('tr');

				return $row;
			}, $blocks));

			$table .= Html::closeElement('table');

			$output->addHTML($table);
		}

		//also show a form to add a new block
		$output->addHTML(Html::element('h3', [], wfMessage('scratch-confirmaccount-add-block')->text()));
		$this->singleBlockForm('', $request, $output, $session, $dbr);
	}

	//show a form that allows editing an existing block or adding a new one (leave the username blank)
	function singleBlockForm($blockedUsername, &$request, &$output, &$session, IDatabase $dbr) {
		if (!$this->canViewBlocks()) {
			throw new PermissionsError('block');
		}
		
		//get the block associated with the provided username
		if ($blockedUsername) {
			$block = getSingleBlock($blockedUsername, $dbr);
			if (!$block) {
				$output->showErrorPage('error', 'scratch-confirmaccount-not-blocked');
				return;
			}
		} else {
			$block = false;
		}

		if ($block) {
			$output->addHTML(Html::element('h3', [], wfMessage('scratch-confirmaccount-vieweditblock')));
		}

		$output->addHTML(Html::openElement('form', ['method' => 'post', 'enctype' => 'multipart/form-data', 'action' => SpecialPage::getTitleFor('ConfirmAccounts')->getFullURL()]));
		
		// anti-CSRF
		$output->addHTML(Html::element('input', ['type' => 'hidden', 'name' => 'csrftoken', 'value' => setCSRFToken($session)]));
		
		$output->addHTML(Html::element('input', ['type' => 'hidden', 'name' => 'blockAction', 'value' => $block ? 'update' : 'create']));

		$table = Html::openElement('table', [ 'class' => 'wikitable' ]);

		$table .= Html::openElement('tr');
		$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-scratchusername')->text());
		$table .= Html::rawElement('td', [], Html::element('input', ['type' => 'text', 'name' => 'username', 'value' => $blockedUsername, 'readonly' => (bool)$block]));
		$table .= Html::closeElement('tr');

		$table .= Html::openElement('tr');
		$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-blockreason')->text());
		$table .= Html::rawElement('td', [], Html::element('textarea', ['class' => 'mw-scratch-confirmaccount-textarea', 'name' => 'reason'], $block ? $block->reason : ''));
		$table .= Html::closeElement('tr');

		$table .= Html::openElement('tr');
		$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-block-expiration-time')->text());
		$table .= Html::rawElement('td', [], blockExpirationForm(
			$this->getLanguage(),
			$this->getUser(),
			$block !== false,
			$block ? $block->expirationTimestamp : null
		));
		$table .= Html::closeElement('tr');

		$table .= Html::closeElement('table');

		$output->addHTML($table);

		$output->addHTML(Html::element('input', ['type' => 'submit', 'name' => 'blockSubmit', 'value' => wfMessage('scratch-confirmaccount-submit')->text()]));

		if ($block) {
			$output->addHTML(Html::element('input', ['type' => 'submit', 'name' => 'unblockSubmit', 'value' => wfMessage('scratch-confirmaccount-unblock')->text()]));
		}

		$output->addHTML(Html::closeElement('form'));
	}

	function blocksPage($par, &$request, &$output, &$session) {
		if (!$this->canViewBlocks()) {
			throw new PermissionsError('block');
		}
				
		$subpageParts = explode('/', $par);

		if (sizeof($subpageParts) < 2) {
			return $this->blocksListPage($request, $output, $session);
		} else {
			return $this->singleBlockForm($subpageParts[1], $request, $output, $session, getReadOnlyDatabase());
		}
	}

	function requestTable($username, $status, &$linkRenderer) {
		$pager = new AccountRequestPager($username, $status, $linkRenderer, $this->getLanguage());

		if ($pager->getNumRows() == 0) {
			return Html::element('p', [], wfMessage('scratch-confirmaccount-norequests')->text());
		}

		$table = $pager->getNavigationBar();

		$table .= Html::openElement('table', [ 'class' => 'wikitable' ]);

		//table heading
		$table .= Html::openElement('tr');
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-lastupdated')->text()
		);
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-username')->text()
		);
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-requestnotes')->text()
		);
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-actions')->text()
		);
		$table .= Html::closeElement('tr');

		//results
		$table .= $pager->getBody();

		$table .= Html::closeElement('table');

		$table .= $pager->getNavigationBar();

		return $table;
	}

	function listRequestsByStatus($status, &$output) {
		$linkRenderer = $this->getLinkRenderer();

		$output->addHTML(Html::element(
			'h3',
			[],
			wfMessage('scratch-confirmaccount-confirm-header-' . $status)->text()
		));

		$table = $this->requestTable(null, $status, $linkRenderer);

		$output->addHTML($table);
	}

	function defaultPage(&$output) {
		$linkRenderer = $this->getLinkRenderer();

		$disp = Html::element('h3', [], wfMessage('scratch-confirmaccount-request-options')->text());
		$disp .= Html::openElement('form', [
			'action' => '',
			'method' => 'get'
		]);
		$disp .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-usernamesearch'],
			wfMessage('scratch-confirmaccount-search-label')->text()
		);
		$disp .= Html::element('br');
		$disp .= Html::element('input', [
			'type' => 'search',
			'id' => 'scratch-confirmaccount-usernamesearch',
			'name' => 'username'
		]);
		$disp .= Html::element('input', [
			'type' => 'submit',
			'value' => wfMessage('scratch-confirmaccount-search')->parse()
		]);
		$disp .= Html::closeElement('form');
		$output->addHTML($disp);

		$this->listRequestsByStatus('new', $output);
		$this->listRequestsByStatus('awaiting-admin', $output);
	}

	function handleBlockFormSubmission(&$request, &$output, &$session) {
		if (!$this->canViewBlocks()) {
			throw new PermissionsError('block');
		}
		
		$username = $request->getText('username');
		$reason = $request->getText('reason');
		$expirationTimestamp = $request->getText('expiration_timestamp');
		if ($expirationTimestamp !== 'existing') {
			if ($expirationTimestamp === 'infinite') {
				$expirationTimestamp = null;
			} else {
				if ($expirationTimestamp === 'othertime') {
					$expirationTimestamp = $request->getText('expiration_timestamp_time');
				}
				$expirationTimestamp = empty(trim($expirationTimestamp)) ? null : wfTimestamp(TS_MW, strtotime($expirationTimestamp));
			}
		}
		
		// anti-CSRF
		if (isCSRF($session, $request->getText('csrftoken'))) {
			$output->showErrorPage('error', 'scratch-confirmaccount-csrf');
			return;
		}

		if (!$username) {
			$output->showErrorPage('error', 'scratch-confirmaccount-block-invalid-username');
			return;
		}
		if (!$reason) {
			$output->showErrorPage('error', 'scratch-confirmaccount-block-invalid-reason');
			return;
		}

		$mutexId = 'scratch-confirmaccount-update-block-' . $username;
		$dbw = getTransactableDatabase($mutexId);
		
		$block = getSingleBlock($username, $dbw);
		if ($block) {
			updateBlock($username, $reason, $expirationTimestamp, $this->getUser(), $dbw);
		} else {
			if ($expirationTimestamp === 'existing') $expirationTimestamp = null;
			addBlock($username, $reason, $expirationTimestamp, $this->getUser(), $dbw);
		}

		$output->redirect(SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-blocks')->text())->getFullURL());
		
		commitTransaction($dbw, $mutexId);
		JobQueueGroup::singleton()->push(new ExpiredBlockCleanupJob());
	}

	function handleUnblockFormSubmission(&$request, &$output, &$session) {
		if (!$this->canViewBlocks()) {
			throw new PermissionsError('block');
		}
		
		$username = $request->getText('username');
		
		if (isCSRF($session, $request->getText('csrftoken'))) {
			$output->showErrorPage('error', 'scratch-confirmaccount-csrf');
			return;
		}
		
		$mutexId = 'scratch-confirmaccount-update-unblock-' . $username;
		
		$dbw = getTransactableDatabase($mutexId);

		$block = getSingleBlock($username, $dbw);
		if (!$block) {
			cancelTransaction($dbw, $mutexId);
			$output->showErrorPage('error', 'scratch-confirmaccount-not-blocked');
			return;
		}

		deleteBlock($username, $dbw);

		$output->redirect(SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-blocks')->text())->getFullURL());
		
		commitTransaction($dbw, $mutexId);
		JobQueueGroup::singleton()->push(new ExpiredBlockCleanupJob());
	}

	function handleFormSubmission(&$request, &$output, &$session) {
		if ($request->getText('action')) {
			handleRequestActionSubmission('admin', $this, $session);
		} else if ($request->getText('blockSubmit')) {
			$this->handleBlockFormSubmission($request, $output, $session);
		} else if ($request->getText('unblockSubmit')) {
			$this->handleUnblockFormSubmission($request, $output, $session);
		} else if ($request->getText('bypassAddUsername') || $request->getText('bypassRemoveUsername')) { //TODO: refactor to move all the subpages into their own files
			$bypassPage = new RequirementsBypassPage($this);
			$bypassPage->handleFormSubmission();
		}
	}

	function searchByUsername($username, &$request, &$output) {		
		$linkRenderer = $this->getLinkRenderer();

		$output->addHTML(Html::element(
			'h3',
			[],
			wfMessage('scratch-confirmaccount-confirm-search-results', $username)->text()
		));

		$table = $this->requestTable($username, null, $linkRenderer);

		$output->addHTML($table);
	}

	function showTopLinks() {
		$linkRenderer = $this->getLinkRenderer();

		$links = [];

		//the root ConfirmAccounts page
		$links[] = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts'),
			wfMessage('confirmaccounts')->text()
		);

		//the pages for each status
		$links += array_map(function ($status, $statusmsg) use($linkRenderer) {
			return $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor('ConfirmAccounts', $status),
				wfMessage('scratch-confirmaccount-' . $status)->text()
			);
		}, array_keys(statuses), array_values(statuses));
		
		//blocks (if the current user can view them)
		if ($this->canViewBlocks()) {
			$links[] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-blocks')),
				wfMessage('scratch-confirmaccount-blocks')->text()
			);
		}

		$links[] = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-requirements-bypasses-url')),
			wfMessage('scratch-confirmaccount-requirements-bypasses-admin-text')->text()
		);

		$this->getOutput()->setSubtitle($this->getLanguage()->pipeList($links));
	}

	function execute( $par ) {		
		$request = $this->getRequest();
		$output = $this->getOutput();
		$language = $this->getLanguage();
		
		$output->setPageTitle( $this->msg( "confirmaccounts" )->escaped() );
		
		$output->addModules('ext.scratchConfirmAccount.js');
		$output->addModuleStyles('ext.scratchConfirmAccount.css');
		
		$session = $request->getSession();
		
		$this->setHeaders();
		$this->checkReadOnly();

		$this->showTopLinks();

		//check permissions
		$user = $this->getUser();

		if (!$user->isAllowed('createaccount')) {
			throw new PermissionsError('createaccount');
		}

		if ($request->wasPosted()) {
			return $this->handleFormSubmission($request, $output, $session);
		} else if (strpos($par, wfMessage('scratch-confirmaccount-blocks')->text()) === 0) {
			return $this->blocksPage($par, $request, $output, $session);
		} else if (strpos($par, wfMessage('scratch-confirmaccount-requirements-bypasses-url')->text()) === 0) {
			$bypassPage = new RequirementsBypassPage($this);
			return $bypassPage->render();
		} else if ($request->getText('username')) {
			return $this->searchByUsername($request->getText('username'), $request, $output);
		} else if (isset(statuses[$par])) {
			return $this->listRequestsByStatus($par, $output);
		} else if (ctype_digit($par)) {
			return requestPage($par, 'admin', $this, $request->getSession());
		} else if (empty($par)) {
			return $this->defaultPage($output);
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
