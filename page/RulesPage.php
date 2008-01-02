<?php

class RulesPage extends WikiPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'rules';
		$this->title = 'Правила за използване на <em>{SITENAME}</em>';
	}

}
