<?php
class EditBookPage extends Page {

	const DB_TABLE = DBT_BOOK;
	protected
		$bookTypes = array(
			'book' => 'Обикновена книга',
			'collection' => 'Сборник',
			'poetry' => 'Стихосбирка',
		)
	;

	public function __construct() {
		parent::__construct();
		$this->action = 'editBook';
		$this->objId = (int) $this->request->value('id', 0, 1);
		$this->title = ($this->objId == 0
			? 'Добавяне' : 'Редактиране') .' на книга';
		$this->objTitle = $this->request->value('title', '');
		$this->subtitle = $this->request->value('subtitle', '');
		$this->type = $this->request->value('type');
		$this->showagain = $this->request->checkbox('showagain');
	}


	protected function processSubmission() {
		$queries = $this->makeUpdateQueries();
		$res = $this->db->transaction($queries);
		if ( in_array(false, $res) ) {
			$this->addMessage('Редакцията не сполучи.', true);
		} else {
			$this->addMessage('Редакцията беше успешна.');
		}
		return $this->showagain ? $this->makeEditForm() : '';
	}


	protected function buildContent() {
		if ( !empty($this->objId) ) {
			$this->initData();
		}
		$add = $this->out->internLink('Добавяне на книга',
			array(self::FF_ACTION=>$this->action), 1);
		return '<p style="text-align:center">'.$add.'</p>'. $this->makeEditForm();
	}


	protected function makeUpdateQueries() {
		$key = $this->objId;
		if ($this->objId == 0) {
			$this->objId = $this->db->autoIncrementId(self::DB_TABLE);
		}
		$set = array('id' => $this->objId, 'title' => $this->objTitle,
			'subtitle' => $this->subtitle, 'type' => $this->type);
		$queries = array();
		$queries[] = $this->db->updateQ(self::DB_TABLE, $set, $key);
		return $queries;
	}


	protected function initData() {
		$sel = array('title', 'subtitle', 'type');
		$key = array('id' => $this->objId);
		$res = $this->db->select(self::DB_TABLE, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		$this->objTitle = $data['title'];
		$this->subtitle = $data['subtitle'];
		$this->type = $data['type'];
	}


	protected function makeEditForm() {
		$objId = $this->out->hiddenField('id', $this->objId);
		$objTitle = $this->out->textField('title', '', $this->objTitle, 50);
		$subtitle = $this->out->textField('subtitle', '', $this->subtitle, 50);
		$type = $this->out->selectBox('type', '', $this->bookTypes, $this->type);
		$showagain = $this->out->checkbox('showagain', '', $this->showagain,
			'Показване на формуляра отново');
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS

<form action="{FACTION}" method="post">
	$objId
<table>
<tr>
	<td><label for="title">Заглавие:</label></td>
	<td>$objTitle</td>
</tr><tr>
	<td><label for="subtitle">Подзаглавие:</label></td>
	<td>$subtitle</td>
</tr><tr>
	<td><label for="type">Тип:</label></td>
	<td>$type</td>
</tr><tr>
	<td colspan="2">$showagain</td>
</tr><tr>
	<td colspan="2">$submit</td>
</tr>
</table>
</form>
EOS;
	}

}
