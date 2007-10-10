<?php
class MarkReadPage extends Page {

	const DB_TABLE = DBT_READER_OF;

	public function __construct() {
		parent::__construct();
		$this->action = 'markRead';
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->title = 'Прочетено';
	}


	protected function buildContent() {
		$key = array('user' => $this->user->id, 'text' => $this->textId);
		if ( $this->db->exists(self::DB_TABLE, $key) ) {
			$this->addMessage('Това произведение вече е било отбелязано като прочетено!');
			return '';
		}
		$set = $key + array('date' => date('Y-m-d'));
		$this->db->insert(self::DB_TABLE, $set);
		$work = Work::newFromId($this->textId);
		$link = $this->makeSimpleTextLink($work->title, $this->textId);
		$author = $this->makeFromAuthorSuffix($work->author_name);
		$this->addMessage("Произведението <strong>„{$link}“</strong>$author беше отбелязано като прочетено.");
		return '';
	}
}
