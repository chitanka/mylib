<?php
class MarkReadPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'markRead';
		$this->mainDbTable = 'reader_of';
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->title = 'Прочетено';
	}


	protected function buildContent() {
		$key = array('user' => $this->user->id, 'text' => $this->textId);
		if ( $this->db->exists($this->mainDbTable, $key) ) {
			$this->addMessage('Това произведение вече е било отбелязано като прочетено!');
			return '';
		}
		$set = $key + array('date' => date('Y-m-d'));
		$this->db->insert($this->mainDbTable, $set);
		$work = Work::newFromId($this->textId);
		$this->addMessage("Произведението <strong>„<a href='$this->root/text/$this->textId'>$work->title</a>“</strong> беше отбелязано като прочетено.");
		return '';
	}
}
