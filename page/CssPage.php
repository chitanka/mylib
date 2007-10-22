<?php

class CssPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'css';
		$this->dir = 'style/';
		$this->file = $this->request->value('f', 'main', 1);
		$this->contentType = 'text/css';
	}


	protected function buildContent() {
		header("Content-type: $this->contentType; charset=$this->outencoding");
		require $this->dir . $this->file .'.css';
		exit;
		return '';
	}
}
