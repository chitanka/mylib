<?php
class EditSeriesPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'editSeries';
		$this->seriesId = (int) $this->request->value('seriesId', 0, 1);
		$this->title = ($this->seriesId == 0
			? 'Добавяне' : 'Редактиране') .' на поредица';
		$this->name = $this->request->value('name', '');
		$this->orig_name = $this->request->value('orig_name', '');
		$this->author = (array) $this->request->value('author');
		$this->showagain = $this->request->checkbox('showagain');
		$this->mainDbTable = 'series';
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
		if ($this->seriesId != 0) { $this->initData(); }
		$addLink = '<p style="text-align:center"><a href="'.$this->root.'/addSeries">Добавяне на поредица</a><p>';
		return $addLink . $this->makeEditForm();
	}


	protected function makeUpdateQueries() {
		$key = $this->seriesId;
		if ($this->seriesId == 0) {
			$this->seriesId = $this->db->autoIncrementId('series');
		}
		$set = array('id' => $this->seriesId, 'name' => $this->name,
			'orig_name' => $this->orig_name);
		$queries = array();
		$queries[] = $this->db->makeInsertOrUpdateQuery($this->mainDbTable, $set, $key);
		$is_changed = (array) $this->request->value('is_changed');
		if ( $is_changed['author'] ) {
			$key = array('series' => $this->seriesId);
			$queries[] = $this->db->deleteQ('ser_author_of', $key);
			foreach ($this->author as $pos => $author) {
				if ( empty($author) ) { continue; }
				$set = array('author' => $author, 'series' => $this->seriesId);
				$queries[] = $this->db->insertQ('ser_author_of', $set);
			}
		}
		return $queries;
	}


	protected function initData() {
		$sel = array('name', 'orig_name');
		$key = array('id' => $this->seriesId);
		$res = $this->db->select($this->mainDbTable, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		extract2object($data, $this);
	}


	protected function makeEditForm() {
		$this->addJs( $this->makePersonJs() );
		$author = $this->makePersonInput(1);
		$seriesId = $this->out->hiddenField('seriesId', $this->seriesId);
		$name = $this->out->textField('name', '', $this->name, 50);
		$orig_name = $this->out->textField('orig_name', '', $this->orig_name, 50);
		$showagain = $this->out->checkbox('showagain', '', $this->showagain,
			'Показване на формуляра отново');
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS

<form action="{FACTION}" method="post">
	$seriesId
<table>
<tr>
	<td><label for="name">Име:</label></td>
	<td>$name</td>
</tr><tr>
	<td><label for="orig_name">Оригинално име:</label></td>
	<td>$orig_name</td>
</tr><tr>
	<td><label for="authors">Автор(и):</label></td>
	<td>$author
		<p>[<a href="javascript:void(0)" onclick="addRow('author')">Още един</a>]</p>
	</td>
</tr><tr>
	<td colspan="2">$showagain</td>
</tr><tr>
	<td colspan="2">$submit</td>
</tr>
</table>
</form>
EOS;
	}


	// copied from EditPage.php with changes - грозно, грозно
	protected function makePersonInput($ind) {
		$keys = array(1 => 'author', 2 => 'translator');
		$key = $keys[$ind];
		$js = "\npersons['$key'] = {";
		$dbkey = array("(role & $ind)");
		foreach ($this->db->getObjects('person', null, null, $dbkey) as $id => $name) {
			$js .= "\n\t$id: '$name',";
		}
		$js = rtrim($js, ',') . "\n}; // end of array persons['$key']\n";
		$this->addJs($js);
		$dbkey = array('series' => $this->seriesId);
		$q = $this->db->selectQ('ser_'.$key.'_of', $dbkey, $key);
		$addRowFunc = create_function('$row',
			'return "addRow(\''.$key.'\', $row['.$key.']); ";');
		$load = $this->db->iterateOverResult($q, $addRowFunc);
		if ( empty($load) ) { $load = "addRow('$key', 0); "; }
		$is_changed = $this->out->hiddenField("is_changed[$key]", 0);
		return <<<EOS
	<table><tbody id="t$key"><tbody></table>
	$is_changed
	$this->scriptStart
		$load
	$this->scriptEnd
EOS;
	}


	// copied from EditPage.php - грозно, грозно
	protected function makePersonJs() {
		return <<<EOS

		var persons = new Array();

		function addRow(key, ind) {
			var tbody = document.getElementById("t"+key);
			var newRow = tbody.insertRow( tbody.rows.length );
			var cells = new Array();
			for (var i = 0; i < 1; i++) { cells[i] = newRow.insertCell(i); }
			i = 0;
			cells[i++].innerHTML = makePersonSelectMenu(key, ind);
		}

		function makePersonSelectMenu(key, selInd) {
			var o = '<select id="'+key+'" name="'+key+'[]" onchange="'+
				'this.form.elements[\'is_changed['+key+']\'].value=1;">'+
				'<option value="">(Избор)</option>';
			for (var i in persons[key]) {
				var sel = i == selInd ? ' selected="selected"' : '';
				o += '<option value="'+i+'"'+sel+'>'+persons[key][i]+'</option>';
			}
			o += '</select>';
			return o;
		}
EOS;
	}

}
?>