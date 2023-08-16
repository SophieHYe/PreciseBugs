<?php

use MediaWiki\Linker\LinkRenderer;

class SpecialGlobalNewFiles extends SpecialPage {
	/** @var LinkRenderer */
	private $linkRenderer;

	function __construct( LinkRenderer $linkRenderer ) {
		parent::__construct( 'GlobalNewFiles' );
		$this->linkRenderer = $linkRenderer;
	}

	function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$pager = new GlobalNewFilesPager( $this->getContext(), $this->linkRenderer );

		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}

	protected function getGroupName() {
		return 'other';
	}
}
