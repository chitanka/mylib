<?php

class NoPagePage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'noPage';
		$this->title = 'Несъществуваща страница';
	}


	protected function buildContent() {
		$this->addMessage("Няма такава страница.", true);
		$reqUri = $this->request->requestUri();
		return "<p>Поискан адрес: $reqUri</p>";
	}

}
