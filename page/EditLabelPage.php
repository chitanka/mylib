<?php
class EditLabelPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'editLabel';
		$this->labelId = (int) $this->request->value('labelId', 0, 1);
		$this->title = ($this->labelId == 0 ? 'Добавяне' : 'Редактиране').' на етикет';
		$this->name = $this->request->value('name', '');
		$this->mainDbTable = 'label';
	}


	protected function processSubmission() {
		$res = $this->db->insertOrUpdate($this->mainDbTable,
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
		$res = $this->db->select($this->mainDbTable, $key, 'name');
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) return false;
		extract2object($data, $this);
		return true;
	}


	protected function makeEditForm() {
		$labelId = $this->out->hiddenField('labelId', $this->labelId);
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
		$q = $this->db->selectQ($this->mainDbTable, array(), '*', 'name');
		$items = $this->db->iterateOverResult($q, 'makeListItem', $this);
		return "<ul>$items</ul>";
	}


	public function makeListItem($dbrow) {
		extract($dbrow);
		return <<<EOS

	<li><a href="$this->root/label/$name"
		title="Преглед на заглавията с етикет „{$name}“">$name</a> —
		<a class="edit" href="$this->root/editLabel/$id"
		title="Редактиране на етикет „{$name}“">ред</a>,
		<a class="delete" href="$this->root/deleteLabel/$id"
		title="Изтриване на етикет „{$name}“">изтр</a>
	</li>
EOS;
	}
}
?>
