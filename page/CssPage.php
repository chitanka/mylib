<?php

class CssPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'css';
		$this->dir = 'style/';
		$this->file = $this->request->value('f', 'main', 1);
	}


	protected function buildContent() {
		#header("Content-type: $this->contentType; charset=$this->outencoding");
		$file = $this->dir . $this->file .'.css';
		if ( !file_exists($file) ) {
			$this->addMessage("$this->file не е валидно име на стил.", true);
		} else {
			$this->contentType = 'text/css';
			$this->sendCommonHeaders();
			require $file;
			$this->outputDone = true;
		}
		return '';
	}
}
