<?php

class LinksPage extends MailPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'links';
		$this->title = 'Връзки';
		$this->elem['link'] = $this->request->value('link', 'http://');
		$this->elem['desc'] = $this->request->value('desc');
	}


	protected function processSubmission() {
		if ( empty($this->elem['link']) ) {
			$this->addMessage('Не сте посочили адрес на сайт!', true);
			return $this->buildContent();
		}
		if ( empty($this->elem['desc']) ) {
			$this->addMessage('Не сте въвели описание на сайта!', true);
			return $this->buildContent();
		}
		if ( $this->user->isAnon() && isSpam($this->elem['desc'], 1) ) {
			$this->addMessage('Описанието е определено като спам. Вероятно съдържа уеб адреси.', true);
			return $this->buildContent();
		}
		$this->mailSubject = "$this->sitename: Нова връзка";
		$this->mailSuccessMessage = 'Предложението ви е прието.';
		$this->mailFailureMessage = 'Изглежда е станал някакъв фал при обработката
			на предложението ви. Ако искате, изчакайте малко и пробвайте пак.
			Извинете за неудобството!';
		return parent::processSubmission();
	}


	protected function buildContent() {
		$wikipage = PageManager::buildPage('wiki');
		$wikipage->setAction('links');
		return $wikipage->execute() . $this->makeForm();
	}


	protected function makeSubmissionReturn() {
		return $this->buildContent();
	}


	protected function makeForm() {
		$link = $this->out->textField('link', '', $this->elem['link'], 60, 255, 1);
		$desc = $this->out->textarea('desc', '', $this->elem['desc'], 4, 60, 2);
		$submit = $this->out->submitButton('Изпращане', '', 3);
		return <<<EOS

<hr />
<p>Чрез долния формуляр можете да предложите нова връзка за включване в
списъка. Въвеждането на описание на сайта е <em>задължително</em>.</p>
<p>Няма да бъдат приемани сайтове, които са достъпни само от България, напр.
такива от мрежата на дата.бг, както и сайтове, за чието нормално използване
се изисква Флаш, Джаваскрипт или Джава.</p>

<form action="{FACTION}" method="post">
<fieldset style="margin: 1em auto; width: 40em">
	<legend>Предложение за нова връзка</legend>
	<label for="link">Адрес:</label>
	$link<br />
	<label for="desc">Име и кратко описание на сайта:</label><br />
	$desc<br />
	$submit
</fieldset>
</form>

EOS;
	}


	protected function makeMailMessage() {
		return $this->elem['link'] ."\n\n". $this->elem['desc'];
	}
}
