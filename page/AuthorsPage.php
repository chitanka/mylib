<?php
class AuthorsPage {

	public function __construct() {
		$requrl = str_replace('authors/', 'author/', $_SERVER['REQUEST_URI']);
		header("Location: $requrl");
		exit;
	}
}
