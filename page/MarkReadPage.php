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
		$set = array('user'=>$this->user->id, 'text'=>$this->textId, 'date' => date('Y-m-d'));
		$this->db->insert($this->mainDbTable, $set);
		$res = $this->db->select('text', array('id' => $this->textId), 'title');
		$data = $this->db->fetchAssoc($res);
		$this->addMessage("Произведението <strong>„<a href='$this->root/text/$this->textId'>$data[title]</a>“</strong> беше отбелязано като прочетено.");
		return '';
	}
}
?>
