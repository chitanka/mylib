<?php
class SitemapPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->url = Setup::setting('server') . $this->root . '/';
		$this->media = $this->request->value('media', 'print', 1);
	}


	protected function buildContent() {
		if ($this->media == 'print') {
			header('Content-Type: text/plain; charset='.$this->outencoding);
		}
		$this->readfile();
		$this->outputDone = true;
	}

	protected function readfile() {
		$fp = fopen('sitemap', 'r');
		while ( !feof($fp) ) {
			$line = fgets($fp);
			$furl = $this->url . rtrim($line);
			$oline = ($this->media == 'print' ? $furl : $this->out->link($furl).'<br/>')."\n";
			$this->encprint($oline);
		}
		fclose($fp);
	}
}
