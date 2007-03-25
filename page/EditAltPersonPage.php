<?php
class EditAltPersonPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'editAltPerson';
		$this->mainDbTable = 'person_alt';
		$this->altId = (int) $this->request->value('altId', 0, 1);
		$this->title = ($this->altId == 0 ? 'Добавяне' : 'Редактиране').
			' на алтернативно име на автор';
		$this->name = $this->request->value('name', '');
		$this->person = $this->request->value('person');
		$this->type = $this->request->value('type', 'p');
		$this->orig_name = $this->request->value('orig_name', '');
		$this->showagain = $this->request->checkbox('showagain');
	}


	protected function processSubmission() {
		$res = $this->db->insertOrUpdate($this->mainDbTable,
			$this->makeSetData(), $this->altId);
		if ( $res !== false ) {
			$this->addMessage('Редакцията беше успешна.');
		} else {
			$this->addMessage('Редакцията не сполучи.', true);
		}
		return $this->showagain ? $this->buildContent() : '';
	}


	protected function buildContent() {
		$this->initData();
		return $this->makeEditForm();
	}


	protected function makeSetData() {
		preg_match('/([^,]+) ([^,]+(, .+)?)/', $this->name, $m);
		$lastName = isset($m[2]) ? $m[2] : $this->name;
		return array('name' => $this->name,
			'orig_name' => $this->orig_name, 'last_name' => $lastName,
			'person' => $this->person, 'type' => $this->type);
	}


	protected function initData() {
		$sel = array('name', 'orig_name', 'person');
		$key = array('id' => $this->altId);
		$res = $this->db->select($this->mainDbTable, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) return false;
		extract2object($data, $this);
	}


	protected function makeEditForm() {
		$altId = $this->out->hiddenField('altId', $this->altId);
		$name = $this->out->textField('name', '', $this->name, 30);
		$orig_name = $this->out->textField('orig_name', '', $this->orig_name, 30);
		$person = $this->out->selectBox('person', '',
			$this->db->getObjects('person'), $this->person);
		$type = $this->makeTypeInput();
		$showagain = $this->out->checkbox('showagain', '', $this->showagain,
			'Показване на формуляра отново');
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS

<form action="{FACTION}" method="post">
	$altId
<table>
<tr>
	<td><label for="name">Алтернативно име:</label></td>
	<td>$name</td>
</tr><tr>
	<td><label for="orig_name">Оригинално изписване:</label></td>
	<td>$orig_name</td>
</tr><tr>
	<td><label for="person">Основно име:</label></td>
	<td>$person</td>
</tr><tr>
	<td>Вид:</td>
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


	protected function makeTypeInput() {
		$types = array('p' => 'Псевдоним', 'r' => 'Истинско име', 'a'=>'Алтернативно изписване');
		$o = '';
		foreach ($types as $code => $text) {
			$ch = $this->type == $code ? ' checked="checked"' : '';
			$o .= "<input type='radio' name='type' id='type-$code' value='$code'$ch><label for='type-$code'>$text</label> ";
		}
		return $o;
	}

}
?>
