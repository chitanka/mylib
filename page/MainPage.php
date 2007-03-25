<?php

class MainPage extends WikiPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'main';
		$this->title = 'Начална страница';
		$this->forceStatic = false;
	}


	protected function buildContent() {
		if ( $this->user->option('mainpage') == 'd' && !$this->forceStatic ) {
			return $this->redirect('dynMain');
		}
		return parent::buildContent();
	}

}
?>
