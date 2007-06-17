<?php

class SuggestTranslatorPage extends MailPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'suggestTranslator';
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->chunkId = (int) $this->request->value('chunkId', 1, 2);
		$this->info = $this->request->value('info');
		$this->initData();
	}


	protected function processSubmission() {
		if ( empty($this->info) ) { return $this->buildContent(); }
		if ( isSpam($this->info, 2) ) {
			$this->addMessage('Съобщението ви е определено като спам. Вероятно съдържа прекалено много уеб адреси.', true);
			return $this->buildContent();
		}
		$this->mailSubject = 'Информация за преводач';
		$this->mailSuccessMessage = 'Съобщението ви беше изпратено. Благодаря ви!';
		$this->mailFailureMessage = 'Изглежда е станал някакъв фал при
			изпращането на съобщението ви. Ако желаете, пробвайте още веднъж.';
		return parent::processSubmission();
	}


	protected function makeSubmissionReturn() {
		return "<p>Обратно към „<a href='$this->root/text/$this->textId/".
			"$this->chunkId'>$this->textTitle</a>“</p>";
	}


	protected function makeForm() {
		if ( empty($this->textTitle) ) return '';
		$textId = $this->out->hiddenField('textId', $this->textId);
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
		$author = $this->makeAuthorLink($this->author);
		$info = $this->out->textarea('info', '', $this->info, 8, 50);
		$submit = $this->out->submitButton('Пращане');
		return <<<EOS
<p>Ако знаете кой е превел
„<a href="$this->root/text/$this->textId">$this->textTitle</a>“
от $author и желаете да ми помогнете да добавя преводача,
можете да ми пратите набързо едно съобщение чрез долния формуляр.</p>
<form action="{FACTION}" method="post">
<fieldset style="margin-top:1em; width:30em">
	$textId
	$chunkId
	<label for="info">Информация за преводача:</label>
	$info<br />
	$submit
</fieldset>
</form>
EOS;
	}


	protected function makeMailMessage() {
		return <<<EOS
„{$this->textTitle}“ от $this->author

$this->info

$this->purl/edit/$this->textId/mode=full
EOS;
	}

	protected function initData() {
		$sql = "SELECT t.title textTitle, t.orig_title,
			GROUP_CONCAT(DISTINCT a.name) author
			FROM /*p*/text t
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id
			WHERE t.id = '$this->textId'
			GROUP BY t.id LIMIT 1";
		$data = $this->db->fetchAssoc( $this->db->query($sql) );
		if ( empty($data) ) {
			$this->addMessage("Не съществува текст с номер <strong>$this->textId</strong>.", true);
			$this->textTitle = $this->orig_title = $this->author = '';
			return;
		}
		extract2object($data, $this);
	}
}
?>
