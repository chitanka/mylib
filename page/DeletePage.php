<?php

class DeletePage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'delete';
		$this->title = 'Изтриване на текст';
		$this->textId = (int) $this->request->value('textId', 0);
	}


	protected function processSubmission() {
		$qs = array();
		$key = array('text' => $this->textId);
		$qs[] = $this->db->deleteQ(DBT_TEXT, array('id'=>$this->textId), 1);
		$qs[] = $this->db->deleteQ(DBT_AUTHOR_OF, $key);
		$qs[] = $this->db->deleteQ(DBT_TRANSLATOR_OF, $key);
		$qs[] = $this->db->deleteQ(DBT_READER_OF, $key);
		$qs[] = $this->db->deleteQ(DBT_HEADER, $key);
		if ( $this->db->transaction($qs) ) {
			$this->addMessage("Текстът с номер $this->textId и всички данни, свързани с него — автор(и), преводач(и), читатели, бяха безвъзвратно изтрити.");
		} else {
			$this->addMessage("Имало е проблем при някоя заявка!", true);
		}
		return $this->buildContent();
	}


	protected function buildContent() {
		$textId = $this->out->textField('textId', '', $this->textId, 6, 8);
		$submit = $this->out->submitButton('Изпълнение');
		return <<<EOS

<p>С долния формуляр можете <strong>безвъзвратно</strong> да изтриете даден текст.</p>
<form action="{FACTION}" method="post">
	<fieldset>
	<legend>Изтриване</legend>
	<label for="textId">Номер на текста:</label>
	$textId
	$submit
	</fieldset>
</form>
EOS;
	}
}
