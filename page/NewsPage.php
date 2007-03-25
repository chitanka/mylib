<?php

class NewsPage extends WikiPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'news';
		$this->title = 'Новини';
	}
}
?>
