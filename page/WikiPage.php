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
		if ( !file_exists($file) ) { return ''; }
		$parser = new Sfb2HTMLConverter($file);
		$parser->parse();
		return explainAcronyms( $parser->text );
	}


	protected function buildContent() {
		return $this->filecontent(NULL, $this->processAll);
	}


	protected function filename($action = NULL) {
		if ( empty($action) ) $action = $this->action;
		return $GLOBALS['contentDirs']['wiki'] . $action;
	}
}
?>
