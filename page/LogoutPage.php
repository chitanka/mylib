<?php

class LogoutPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'logout';
		$this->title = 'Изход';
	}


	protected function buildContent() {
		if ( $this->user->isAnon() ) {
			$this->addMessage('Не сте влезли, а искате да излезете! ;)');
		} else {
			$this->user->logout();
			$this->addMessage("Излязохте от $this->sitename.");
		}
		return '';
	}
}
?>
