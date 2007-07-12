<?php

class EditTextPage extends EditPage {

	public function __construct() {
		if ( !isset($_REQUEST['obj']) ) $_REQUEST['obj'] = 'textonly';
		parent::__construct();
		$this->action = 'editText';
	}

}
