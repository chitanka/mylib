<?php

class WikiPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'wiki';
		$this->title = 'Статична страница';
		$this->processAll = true;
	}


	public function filecontent($action, $replace = true) {
		$file = $this->filename($action);
		if ( !file_exists($file) ) {
			return '';
		}
		$this->parser = new Sfb2HTMLConverter($file);
		$this->customizeParser();
		$this->parser->parse();
		return explainAcronyms( $this->parser->text );
	}


	protected function buildContent() {
		return $this->filecontent(NULL, $this->processAll);
	}


	protected function filename($action = NULL) {
		fillOnEmpty($action, $this->action);
		return $GLOBALS['contentDirs']['wiki'] . $action;
	}

	/**
	Subclasses can customize the parser with this method.
	*/
	protected function customizeParser() {}
}
