<?php

class SuggestDataPage extends MailPage {

	protected
		$subactions = array(
			'origTitle' => '+оригинално заглавие',
			'year' => '+година на написване или първа публикация',
			'translator' => '+преводач',
			'transYear' => '+година на превод',
			'annotation' => 'Предложение за анотация'
		),
		$defSubaction = 'annotation',
		$work = null;

	public function __construct() {
		parent::__construct();
		$this->action = 'suggestData';
		$this->subaction = normKey(
			$this->request->value('sa', $this->defSubaction, 1),
			$this->subactions, $this->defSubaction);
		$this->title = strtr($this->subactions[$this->subaction],
			array('+' => 'Информация за '));
		$this->textId = (int) $this->request->value('textId', 0, 2);
		$this->chunkId = (int) $this->request->value('chunkId', 1, 3);
		$this->info = $this->request->value('info');
		$this->name = $this->request->value('name', $this->user->username);
		$this->email = $this->request->value('email', $this->user->email);
		$this->initData();
	}


	protected function processSubmission() {
		if ( empty($this->info) ) {
			$this->addMessage('Не сте въвели никаква информация.', true);
			return $this->buildContent();
		}
		if ( $this->user->isAnon() && isSpam($this->info, 2) ) {
			$this->addMessage('Съобщението ви е определено като спам. Вероятно съдържа прекалено много уеб адреси.', true);
			return $this->buildContent();
		}
		$this->mailFrom = $this->makeFullAddress($this->name, $this->email);
		$this->mailSubject = $this->title;
		$this->mailSuccessMessage = 'Съобщението ви беше изпратено. Благодаря ви!';
		$this->mailFailureMessage = 'Изглежда е станал някакъв фал при изпращането на съобщението ви. Ако желаете, пробвайте още веднъж.';
		return parent::processSubmission();
	}


	protected function makeSubmissionReturn() {
		return '<p>Обратно към „'.
			$this->makeSimpleTextLink($this->work->title, $this->textId, $this->chunkId)
			.'“</p>';
	}


	protected function makeForm() {
		if ( empty($this->work) ) {
			return '';
		}
		$intro = $this->makeIntro();
		$textId = $this->out->hiddenField('textId', $this->textId);
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
		$subaction = $this->out->hiddenField('subaction', $this->subaction);
		$info = $this->out->textarea('info', '', $this->info, 15, 80);
		$name = $this->out->textField('name', '', $this->name, 50);
		$email = $this->out->textField('email', '', $this->email, 50);
		$submit = $this->out->submitButton('Пращане');
		return <<<EOS
$intro
<p>Посочването на име и електронна поща не е задължително.</p>
<form action="{FACTION}" method="post">
<fieldset style="margin-top:1em; width:30em">
	$textId
	$chunkId
	$subaction
	<table summary="table for the layout"><tr>
		<td class="fieldname-left"><label for="name">Име:</label></td>
		<td>$name</td>
	</tr><tr>
		<td class="fieldname-left"><label for="email">Е-поща:</label></td>
		<td>$email</td>
	</tr></table>
	<label for="info">Информация:</label><br />
	$info<br />
	$submit
</fieldset>
</form>
EOS;
	}


	protected function makeIntro() {
		$ta = '„'. $this->makeSimpleTextLink($this->work->title, $this->textId, $this->chunkId) .'“'.
			$this->makeFromAuthorSuffix($this->work->author_name);
		switch ($this->subaction) {
		case 'origTitle':
			$img = $this->out->image($this->skin->image('wink'), ';-)', 'Намигане');
			return "<p>Ако знаете какво е оригиналното заглавие на $ta, можете да ми пратите набързо едно съобщение чрез долния формуляр, за да го въведа в базата от данни на библиотеката. Ще се радвам и на всякаква друга допълнителна информация за произведението. $img</p>";
		case 'translator':
			return "<p>Ако знаете кой е превел $ta и желаете да ми помогнете да добавя преводача, можете да ми пратите набързо едно съобщение чрез долния формуляр.</p>";
		case 'annotation':
			$params = array(self::FF_ACTION=>'comment', 'textId'=>$this->textId);
			$commentlink = $this->out->internLink('страницата за читателски мнения', $params, 2);
			return <<<EOS
<p>Чрез долния формуляр можете да предложите анотация на $ta. Ако просто искате да оставите коментар към произведението, ползвайте $commentlink.</p>
<p><strong>Ако сте копирали анотацията, задължително посочете източника!</strong></p>
EOS;
		case 'year':
			return "<p>Ако имате информация за годината на написване или първа публикация на $ta, можете да ми пратите набързо едно съобщение чрез долния формуляр.</p>";
		case 'transYear':
			return "<p>Ако имате информация за годината на превод на $ta, можете да ми пратите набързо едно съобщение чрез долния формуляр.</p>";
		}
	}


	protected function makeMailMessage() {
		return <<<EOS
„{$this->work->title}“ от {$this->work->author_name}

$this->info

$this->purl/text/$this->textId
$this->purl/edit/$this->textId
EOS;
	}


	protected function initData() {
		$this->work = Work::newFromId($this->textId);
		if ( is_null($this->work) ) {
			$this->addMessage("Не съществува текст с номер <strong>$this->textId</strong>.", true);
		}
	}
}
