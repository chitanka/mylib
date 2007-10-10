<?php
class SitemapPurlPage extends SitemapPage {

	public function __construct() {
		parent::__construct();
		$this->url = $this->purl .'/';
	}
}
