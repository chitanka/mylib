<?php
class StaticMainPage extends MainPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'main';
		$this->forceStatic = true;
	}

}
