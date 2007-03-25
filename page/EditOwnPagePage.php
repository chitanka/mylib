<?php

class EditOwnPagePage extends EditUserPagePage {

	public function __construct() {
		parent::__construct();
		$this->action = 'editOwnPage';
		$this->username = $this->user->username;
		$this->setDefaultTitle();
	}

}
?>
