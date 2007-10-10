<?php
class Skin {

	private $skinDir = '';
	private $imgDir = '';
	private $images = array();


	public function __construct($skinDir = 'main/') {
		$this->skinDir = $skinDir;
		$this->imgDir = Setup::setting('docroot') .'/img/';
	}


	public function image($name) {
		$img = isset($this->images[$name]) ? $this->images[$name] : $name.'.png';
		return $this->imgDir.$this->skinDir.$img;
	}

	public function imageDir() {
		return $this->imgDir;
	}

	public function bannerDir() {
		return $this->imgDir . 'banner/';
	}

	public function useAbsolutePath() {
		$this->imgDir = 'http://'.$_SERVER['SERVER_NAME'] . $this->imgDir;
	}
}
