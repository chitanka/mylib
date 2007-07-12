<?php

class FeedbackPage extends MailPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'feedback';
		$this->title = 'Обратна връзка';
		$this->name = $this->request->value('name');
		$this->email = $this->request->value('email');
		$this->comment = $this->request->value('comment');
		$this->referer = $this->request->value('referer', $this->request->referer());
	}


	protected function processSubmission() {
		if ( empty($this->comment) ) {
			$this->addMessage('Е-е, това пък на какво прилича?!
				Без коментар не се приема! ;-)', true);
			return $this->buildContent();
		}
		if ( $this->user->isAnon() && isSpam($this->comment) ) {
			$this->addMessage('Коментарът ви е определен като спам. Вероятно съдържа прекалено много уеб адреси.', true);
			return $this->buildContent();
		}
		$this->mailFrom = $this->makeFullAddress($this->name, $this->email);
		$this->mailSubject = "Обратна връзка от $this->sitename";
		$this->mailSuccessMessage = 'Съобщението ви беше изпратено. Благодаря ви!';
		$this->mailFailureMessage = 'Изглежда е станал някакъв фал при
			изпращането на съобщението ви. Ако желаете, пробвайте още веднъж.';
		return parent::processSubmission();
	}


	protected function makeSubmissionReturn() {
		if ( empty($this->referer) ) {
			return '';
		}
		return "<p>Обратно към <a href='$this->referer'>предишната страница</a></p>";
	}


	protected function makeForm() {
		$referer = $this->out->hiddenField('referer', $this->referer);
		$name = $this->out->textField('name', '', $this->name, 50);
		$email = $this->out->textField('email', '', $this->email, 50);
		$comment = $this->out->textarea('comment', '', $this->comment, 10, 60);
		$submit = $this->out->submitButton('Пращане');
		$adminMail = $this->out->obfuscateEmail(ADMIN_EMAIL);
		return <<<EOS

<p>Ако искате да ми кажете мнението си за библиотеката или каквото друго ви е на душата, имате богат избор от възможности за това. Можете да пишете в <a href="$this->forum_root" title="Форумната система на сайта">сайтовия форум</a>, можете да ми пратите писмо по електронната поща ($adminMail), а можете да ползвате и долния формуляр. Посочването на име и електронна поща, между другото, не е задължително.</p>
<p>Ако пишете на български, ползвайте кирилица!</p>
<form action="{FACTION}" method="post">
<fieldset style="margin-top:1em; width:30em">
	<legend>Формулярче</legend>
	$referer
	<table summary="table for the layout"><tr>
		<td class="fieldname-left"><label for="name">Име:</label></td>
		<td>$name</td>
	</tr><tr>
		<td class="fieldname-left"><label for="email">Е-поща:</label></td>
		<td>$email</td>
	</tr></table>
	<label for="comment">Коментар:</label>
	$comment
	$submit
</fieldset>
</form>
EOS;
	}


	protected function makeMailMessage() {
		return $this->comment;
	}

}
