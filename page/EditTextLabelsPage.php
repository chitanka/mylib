<?php
class EditTextLabelsPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'editTextLabels';
		$this->title = 'Редактиране на текстови етикети';
		$this->textId = $this->request->value('textId', 0, 1);
		$this->chunkId = $this->request->value('chunkId', 1, 2);
		$this->subaction = $this->request->value('subaction');
		$this->work = Work::newFromId($this->textId);
	}


	protected function processSubmission() {
		$changed = false;
		$old = (array) $this->request->value('old');
		$cur = (array) $this->request->value('cur');
		$del = array_diff($old, $cur);
		if ( !empty($del) ) {
			$key = array('text' => $this->textId, 'label' => array('IN', $del));
			$this->db->delete(DBT_TEXT_LABEL, $key);
			$this->log('-', implode(',', $del));
			$changed = true;
		}
		$new = (array) $this->request->value('new');
		if ( !empty($new) ) {
			$data = array();
			foreach ($new as $label) {
				$data[] = array($this->textId, $label);
			}
			$this->db->multiinsert(DBT_TEXT_LABEL, $data, array('text', 'label'));
			$this->log('+', implode(',', $new));
			$changed = true;
		}
		// Ако е въведен допълнителен етикет
		$hasExtra = $this->request->checkbox('newExtraLabelCh');
		$extraLabel = $this->request->value('newExtraLabel');
		if ( $hasExtra && !empty($extraLabel) ) {
			$res = $this->db->select(DBT_LABEL, array('name'=>$extraLabel), 'id');
			if ( $this->db->numRows($res) > 0 ) { // съществуващ етикет
				list($newId) = $this->db->fetchRow($res);
				$act = '+';
			} else { // нов етикет
				$newId = $this->db->autoIncrementId(DBT_LABEL);
				$set = array('id' => $newId, 'name' => $extraLabel);
				$this->db->insert(DBT_LABEL, $set);
				$act = '*';
			}
			$set = array('text' => $this->textId, 'label' => $newId);
			$this->db->insert(DBT_TEXT_LABEL, $set);
			$this->log($act, $newId);
			$changed = true;
		}
		if ($changed) {
			$this->addMessage('Промените бяха съхранени.');
			$link = $this->makeSimpleTextLink('текста', $this->textId, $this->chunkId);
			$this->addMessage('Обратно към '. $link);
		}
		return $this->buildContent();
	}


	protected function buildContent() {
		if ( is_null($this->work) ) {
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
		fillOnEmpty($curLabels, 'Няма');
		$text = $this->makeSimpleTextLink($this->work->title, $this->textId, $this->chunkId);
		$authorV = $this->makeFromAuthorSuffix($this->work->author_name);
		$textId = $this->out->hiddenField('textId', $this->textId);
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
		$extraLabelCh = $this->out->checkbox('newExtraLabelCh', 'l0');
		$extraLabel = $this->out->textField('newExtraLabel', '', '', 25, 255, null,
			'', array('onblur'=>'if (this.value!=\'\') { this.form.newExtraLabelCh.checked=true; }'));
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
		foreach ($this->db->getObjects(DBT_LABEL) as $id => $name) {
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
		$res = $this->db->select(DBT_TEXT_LABEL, array('text'=>$this->textId), 'label');
		while ( $row = $this->db->fetchRow($res) ) {
			$this->labels[$row[0]] = $row[0];
		}
	}


	protected function log($action, $labels) {
		$set = array(self::FF_ACTION=>$action, 'labels'=>$labels, 'text'=>$this->textId,
			'title'=>$this->work->title, 'author'=>$this->work->author_name,
			'user'=>$this->user->id);
		$this->db->insert(DBT_LABEL_LOG, $set);
	}
}
