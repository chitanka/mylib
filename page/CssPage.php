<?php

class CssPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'css';
		$this->dir = 'style/';
		$this->file = $this->request->value('f', 'main', 1);
	}


	protected function buildContent() {
		header('Content-type: text/css; charset=utf-8');
		require $this->dir . $this->file .'.css';
		exit;
		return '';
	}
}
?>
