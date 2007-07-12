<?php
class TextsPage {

	public function __construct() {
		$requrl = str_replace('texts/', 'text/', $_SERVER['REQUEST_URI']);
		header("Location: $requrl");
		exit;
	}
}
