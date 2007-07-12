<?php

class AddPage extends EditPage {

	protected $defEditComment = 'Добавяне';

	public function __construct() {
		parent::__construct();
		$this->action = 'add';
		$this->title = 'Добавяне';
		if ($this->textId > 0) {
			$this->obj = 'text';
		}
		$this->mode = 'full';
		$this->withText = true;
		$this->isNew = true;
	}

}
