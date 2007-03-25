<?php

class AboutPage extends WikiPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'about';
		$this->title = 'За '.$this->sitename;
	}

}
?>
