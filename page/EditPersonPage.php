<?php
class EditPersonPage extends Page {

	const DB_TABLE = DBT_PERSON;
	protected $roles = array('a' => 'Автор', 't' => 'Преводач');

	public function __construct() {
		parent::__construct();
		$this->action = 'editPerson';
		$this->personId = (int) $this->request->value('id', 0, 1);
		$this->title = ($this->personId == 0 ? 'Добавяне' : 'Редактиране').' на личност';
		$this->name = $this->request->value('name', '');
		$this->orig_name = $this->request->value('orig_name', '');
		$this->real_name = $this->request->value('real_name', '');
		$this->oreal_name = $this->request->value('oreal_name', '');
		$this->country = $this->request->value('country', '');
		$this->role = (array) $this->request->value('role');
		$this->info = $this->request->value('info', '');
		$this->showagain = $this->request->checkbox('showagain');
	}


	protected function processSubmission() {
		$res = $this->db->update(self::DB_TABLE,
			$this->makeSetData(), $this->personId);
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
		preg_match('/([^,]+) ([^,]+)(, .+)?/', $this->name, $m);
		$lastName = isset($m[2]) ? $m[2] : $this->name;
		return array('name' => $this->name,
			'orig_name' => $this->orig_name, 'last_name' => $lastName,
			'real_name' => $this->real_name, 'oreal_name' => $this->oreal_name,
			'country' => $this->country, 'role' => implode(',', $this->role),
			'info' => $this->info
		);
	}


	protected function initData() {
		$key = array('id' => $this->personId);
		$sel = array('name', 'orig_name', 'country', 'role', 'info', 'real_name', 'oreal_name');
		$res = $this->db->select(self::DB_TABLE, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			return false;
		}
		extract2object($data, $this);
		$this->role = explode(',', $this->role);
	}


	protected function makeEditForm() {
		$opts = array_merge(array('-' => '(Няма данни)'), $GLOBALS['countries']);
		$country = $this->out->selectBox('country', '', $opts, $this->country);
		$personId = $this->out->hiddenField('id', $this->personId);
		$name = $this->out->textField('name', '', $this->name, 30);
		$orig_name = $this->out->textField('orig_name', '', $this->orig_name, 30);
		$real_name = $this->out->textField('real_name', '', $this->real_name, 30);
		$oreal_name = $this->out->textField('oreal_name', '', $this->oreal_name, 30);
		$role = $this->makeRoleInput();
		$opts = array('' => '(Няма)', 'w' => 'Уикипедия', 'f' => 'БГ-Фантастика');
		$info = $this->out->selectBox('info', '', $opts, $this->info);
		$showagain = $this->out->checkbox('showagain', '', $this->showagain,
			'Показване на формуляра отново');
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS

<form action="{FACTION}" method="post"><div>
	$personId
<table>
<tr>
	<td><label for="name">Име (или псевдоним):</label></td>
	<td>$name</td>
</tr><tr>
	<td><label for="orig_name">Оригинално изписване на името:</label></td>
	<td>$orig_name</td>
</tr><tr>
	<td><label for="real_name">Истинско име:</label></td>
	<td>$real_name</td>
</tr><tr>
	<td><label for="oreal_name">Ориг. изписване на истинското име:</label></td>
	<td>$oreal_name</td>
</tr><tr>
	<td><label for="country">Държава:</label></td>
	<td>$country</td>
</tr><tr>
	<td>Роля</td>
	<td>$role</td>
</tr><tr>
	<td><label for="info">Има статия в:</label></td>
	<td>$info</td>
</tr><tr>
	<td colspan="2">$showagain</td>
</tr><tr>
	<td colspan="2">$submit</td>
</tr>
</table>
</div></form>
EOS;
	}


	protected function makeRoleInput() {
		$o = '';
		foreach ($this->roles as $code => $name) {
			$o .= $this->out->checkbox('role[]', "role$code",
				in_array($code, $this->role), $name, $code).' &nbsp; ';
		}
		return $o;
	}
}
