<?php

class TitleWoAnnoPage extends TitlePage {

	protected $titles = array(
		'simple' => 'Заглавия без анотация — ',
	);

	public function __construct() {
		parent::__construct();
		$this->action = 'titleWoAnno';
	}


	protected function isShownSimpleListItem($dbrow) {
		// show only texts without annotation
		return !file_exists( getContentFilePath('text-anno', $dbrow['textId']) );
	}

}
