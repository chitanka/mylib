<?php
class EditLabelPage extends Page {

	const DB_TABLE = DBT_LABEL;

	public function __construct() {
		parent::__construct();
		$this->action = 'editLabel';
		$this->labelId = (int) $this->request->value('id', 0, 1);
		$this->title = ($this->labelId == 0 ? 'Добавяне' : 'Редактиране').' на етикет';
		$this->name = $this->request->value('name', '');
	}


	protected function processSubmission() {
		$res = $this->db->update(self::DB_TABLE,
			$this->makeSetData(), $this->labelId);
		if ( $res !== false ) {
			$this->addMessage('Редакцията беше успешна.');
		} else {
			$this->addMessage('Редакцията не сполучи.', true);
		}
		return $this->makeEditForm();
	}


	protected function buildContent() {
		if ( !$this->initData() ) return $this->makeList();
		return $this->makeEditForm();
	}


	protected function makeSetData() {
		return array('name' => $this->name);
	}


	protected function initData() {
		$key = array('id' => $this->labelId);
		$res = $this->db->select(self::DB_TABLE, $key, 'name');
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			return false;
		}
		extract2object($data, $this);
		return true;
	}


	protected function makeEditForm() {
		$labelId = $this->out->hiddenField('id', $this->labelId);
		$name = $this->out->textField('name', '', $this->name, 30);
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS

<form action="{FACTION}" method="post">
<div>
	$labelId
<table>
<tr>
	<td><label for="name">Име:</label></td>
	<td>$name</td>
</tr><tr>
	<td colspan="2">$submit</td>
</tr>
</table>
</div>
</form>
EOS;
	}


	protected function makeList() {
		$q = $this->db->selectQ(self::DB_TABLE, array(), '*', 'name');
		$items = $this->db->iterateOverResult($q, 'makeListItem', $this);
		return "<ul>$items</ul>";
	}


	public function makeListItem($dbrow) {
		extract($dbrow);
		$p = array(self::FF_ACTION=>'label', self::FF_QUERY => $name);
		$label = $this->out->internLink($name, $p, 2,
			"Преглед на заглавията с етикет „{$name}“");
		$edit = $this->makeEditLabelLink($id, $name);
		$p = array(self::FF_ACTION=>'deleteLabel', 'id' => $id);
		$del = $this->out->internLink('изтр.', $p, 2,
			"Изтриване на етикета „{$name}“", array('class'=>'delete'));
		return "\n\t<li>$label — $edit, $del</li>";
	}
}
