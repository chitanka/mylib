<?php

class InfoPage extends MailPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'info';
		$this->contentDir = './content/info/';
		$this->term = $this->request->value('term', NULL, 1);
		$this->file = $this->contentDir . $this->term;
		$this->title = 'Информация за '. $this->term;
		$this->rawContent = file_exists($this->file)
			? file_get_contents($this->file)
			: "    _Няма информация за $this->term._";
		$defInfo = $this->user->canExecute('*') ? $this->rawContent : '';
		$this->info = $this->request->value('info', $defInfo);
		$this->info = str_replace("\r", '', $this->info);
	}


	protected function processSubmission() {
		if (  $this->user->canExecute('*') ) {
			$this->rawContent = $this->info;
			require_once 'include/replace.php';
			$this->info = my_replace($this->info);
			file_put_contents($this->file, $this->info);
			return $this->buildContent();
		} elseif ( !empty($this->info) ) {
			$this->mailSuccessMessage = 'Съобщението ви беше изпратено. Благодаря ви!';
			$this->mailSubject = "Информация за $this->term";
			return parent::processSubmission();
		}
		return $this->buildContent();
	}


	protected function buildContent() {
		return $this->makeHTML() . $this->makeForm();
	}


	protected function makeHTML() {
		$links = array();
		if ( $this->isAuthor() ) {
			$links[] = $this->makeAuthorLink($this->term, '');
		}
		if ( $this->isTranslator() ) {
			$links[] = $this->makeTranslatorLink($this->term, '');
		}
		$o = '';
		if ( !empty($links) ) {
			$o = '<p style="text-align:right">'. implode(', ', $links) .'</p>';
		}
		return $o . wiki2html($this->rawContent);
	}


	protected function makeForm() {
		$wpLink = $this->makeMwLink($this->term, 'w', false);
		$term = $this->out->hiddenField('term', $this->term);
		$info = $this->out->textarea('info', '', $this->info, 15, 80);
		$submit = $this->out->submitButton('Пращане');
		return <<<EOS

<form action="{FACTION}" method="post">
<fieldset style="margin-top:2em;">
<p style="margin-bottom:1em;">Ако желаете да добавите информация за $this->term, въведете текста в долното поле и натиснете „Пращане“.
В случай че вече има статия за $wpLink в Уикипедия, напишете само „Уикипедия“.
</p>
	$term
	<label for="info">Информация:</label><br />
	$info<br />
	$submit
</fieldset>
</form>
EOS;
	}


	protected function makeMailMessage() {
		return $this->info;
	}


	protected function makeSubmissionReturn() {
		return $this->buildContent();
	}


	protected function isAuthor() {
		return $this->db->exists(DBT_PERSON, array('name' => $this->term, '(role&1)'));
	}


	protected function isTranslator() {
		return $this->db->exists(DBT_PERSON, array('name' => $this->term, '(role&2)'));
	}

}
