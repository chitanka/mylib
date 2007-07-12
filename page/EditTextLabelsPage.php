<?php
class EditTextLabelsPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'editTextLabels';
		$this->title = 'Редактиране на текстови етикети';
		$this->mainDbTable = 'label';
		$this->suplDbTable = 'text_label';
		$this->textId = $this->request->value('textId', 0, 1);
		$this->chunkId = $this->request->value('chunkId', 1, 2);
		$this->subaction = $this->request->value('subaction');
		$this->textTitle = $this->request->value('title', '');
		$this->author = $this->request->value('author', '');
	}


	protected function processSubmission() {
		$changed = false;
		$old = (array) $this->request->value('old');
		$cur = (array) $this->request->value('cur');
		$del = array_diff($old, $cur);
		if ( !empty($del) ) {
			$dels = implode(',', $del);
			$key = array('text'=>$this->textId, "label IN ($dels)");
			$this->db->delete($this->suplDbTable, $key);
			$this->log('-', $dels);
			$changed = true;
		}
		$new = (array) $this->request->value('new');
		if ( !empty($new) ) {
			$q = "INSERT /*p*/$this->suplDbTable (text, label) VALUES";
			foreach ($new as $label) {
				$q .= " ($this->textId, $label),";
			}
			$this->db->query( rtrim($q, ',') );
			$this->log('+', implode(',', $new));
			$changed = true;
		}
		// Ако е въведен допълнителен етикет
		$hasExtra = $this->request->checkbox('newExtraLabelCh');
		$extraLabel = $this->request->value('newExtraLabel');
		if ( $hasExtra && !empty($extraLabel) ) {
			$res = $this->db->select($this->mainDbTable, array('name'=>$extraLabel), 'id');
			if ( $this->db->numRows($res) > 0 ) {
				list($newId) = $this->db->fetchRow($res);
			} else {
				$newId = $this->db->autoIncrementId($this->mainDbTable);
			}
			$set = array('id' => $newId, 'name' => $extraLabel);
			$this->db->replace($this->mainDbTable, $set);
			$set = array('text' => $this->textId, 'label' => $newId);
			$this->db->insert($this->suplDbTable, $set);
			$this->log('*', $newId);
			$changed = true;
		}
		if ($changed) {
			$this->addMessage('Промените бяха съхранени.');
			$this->addMessage("Обратно към <a href='$this->root/text/$this->textId/$this->chunkId'>текста</a>");
		}
		return $this->buildContent();
	}


	protected function buildContent() {
		if ( !$this->initTitleData() ) {
			$this->addMessage("Не съществува текст с номер <strong>$this->textId</strong>.", true);
			return '';
		}
		if ( $this->subaction == 'revert' ) {
			$this->subaction = '';
			return $this->processSubmission();
		}
		$this->initData();
		return $this->makeForm();
	}


	protected function makeForm() {
		list($curLabels, $otherLabels) = $this->makeLabelInput();
		if ( empty($curLabels) ) $curLabels = 'Няма';
		$text = $this->makeSimpleTextLink($this->textTitle, $this->textId, $this->chunkId);
		$authorV = $this->makeAuthorLink($this->author);
		if ( !empty($authorV) ) { $authorV = ' от '.$authorV; }
		$textId = $this->out->hiddenField('textId', $this->textId);
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
		$title = $this->out->hiddenField('title', $this->textTitle);
		$author = $this->out->hiddenField('author', $this->author);
		$extraLabelCh = $this->out->checkbox('newExtraLabelCh', 'l0');
		$extraLabel = $this->out->textField('newExtraLabel', '', '', 25, 255, 0,
			'', 'onchange="if (this.value!=\'\') this.form.newExtraLabelCh.checked=true;"');
		$submit = $this->out->submitButton('Съхраняване на промените');
		return <<<EOS

<div style="width:50%; float:right; margin-left:1.5em">
<p>Целта на етикетите е групирането на произведенията по някакъв признак (жанр, лиценз, тема и др.), за да се улесни претърсването на библиотеката. </p>
<p>На тази страница можете да променяте етикетите на произведението „{$text}“$authorV.</p>
<p>Етикетите са изброени в две групи. В началото са тези, присвоени на произведението, а след тях — всички останали етикети, съществуващи в базата от данни.</p>
<p>За да изтриете етикет (от тези в първата група), махнете отметката пред името му. За да добавите пък някой етикет от втората група, сложете отметка пред него.</p>
<p>Ако искате да добавите етикет, който все още не съществува, можете да го създадете. За целта в края на втората група има текстово поле, в което трябва да въведете името на новия етикет.</p>
<p>В края, след като направите добре обмисления си избор, натиснете някой от бутоните „Съхраняване на промените“.</p>
</div>

<div>
<form action="{FACTION}" method="post">
	$textId
	$chunkId
	$title
	$author

	<fieldset>
	<legend>Присвоени етикети:</legend>
	$curLabels
	</fieldset>
	$submit

	<fieldset>
	<legend>Други етикети:</legend>
	$otherLabels
	$extraLabelCh $extraLabel
	</fieldset>
	$submit
</form>
</div>
EOS;
	}


	protected function makeLabelInput() {
		$cur = $other = '';
		foreach ($this->db->getObjects('label') as $id => $name) {
			if ( isset($this->labels[$id]) ) {
				$oldL = $this->out->hiddenField('old[]', $id);
				$l = $this->out->checkbox('cur[]', "l$id", true, $name, $id);
				$cur .= "\n<div>$oldL $l</div>";
			} else {
				$l = $this->out->checkbox('new[]', "l$id", false, $name, $id);
				$other .= "\n<div>$l</div>";
			}
		}
		return array($cur, $other);
	}


	protected function initData() {
		$this->labels = array();
		$res = $this->db->select($this->suplDbTable, array('text'=>$this->textId), 'label');
		while ( $row = $this->db->fetchRow($res) ) {
			$this->labels[$row[0]] = $row[0];
		}
	}


	protected function initTitleData() {
		$sql = "SELECT t.title textTitle, GROUP_CONCAT(DISTINCT a.name) author
			FROM /*p*/text t
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id
			WHERE t.id = '$this->textId'
			GROUP BY t.id LIMIT 1";
		$data = $this->db->fetchAssoc( $this->db->query($sql) );
		if ( empty($data) ) { return false; }
		extract2object($data, $this);
		return true;
	}


	protected function log($action, $labels) {
		$set = array('action'=>$action, 'labels'=>$labels, 'text'=>$this->textId,
			'title'=>$this->textTitle, 'author'=>$this->author, 'user'=>$this->user->id);
		$this->db->insert('label_log', $set);
	}
}
