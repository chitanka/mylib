<?php

class RegisterPage extends Page {

	private $invalidReferers = array('login', 'logout', 'register', 'sendNewPassword');


	public function __construct() {
		parent::__construct();
		$this->action = 'register';
		$this->title = 'Регистрация';
		$this->mainDbTable = User::MAIN_DB_TABLE;
		$this->username = $this->request->value('username');
		$this->realname = $this->request->value('realname');
		$this->password = $this->request->value('password');
		$this->passwordRe = $this->request->value('passwordRe');
		$this->email = $this->request->value('email');
		$this->news = (int) $this->request->checkbox('news');
		$this->returnto = $this->request->value('returnto', $this->request->referer());
		foreach ($this->invalidReferers as $invalidReferer) {
			if ( strpos($this->returnto, $invalidReferer) !== false ) {
				$this->returnto = '';
			}
		}
	}


	protected function processSubmission() {
		$err = $this->validateInput();
		if ( !empty($err) ) {
			$this->addMessage($err, true);
			return $this->buildContent();
		}
		$key = array('username' => $this->username);
		if ( $this->db->exists($this->mainDbTable, $key) ) {
			$this->addMessage("Името $this->username вече е заето.", true);
			return $this->buildContent();
		}
		$set = array('username' => $this->username, 'realname' => $this->realname,
			'lastname' => ltrim(strrchr($this->realname, ' ')),
			'password' => $this->db->encodePasswordDB($this->password),
			'email' => $this->email, 'news' => $this->news,
			'registration' => date('Y-m-d H:i:s'));
		if ( $this->db->insert($this->mainDbTable, $set) !== false ) {
			$this->addMessage("Регистрирахте се в <em>$this->sitename</em> като $this->username.");
			return $this->redirect('login');
		}
		$this->addMessage('Имаше някакъв проблем при регистрацията.', true);
		return '';
	}


	protected function validateInput() {
		if ( empty($this->username) || empty($this->password) ||
				empty($this->passwordRe) ) {
			return 'Не сте попълнили всички полета.';
		}
		if ( strcmp($this->password, $this->passwordRe) !== 0 ) {
			return 'Двете въведени пароли се различават.';
		}
		$isValid = User::isValidUsername($this->username);
		if ( $isValid !== true ) {
			return "Знакът „{$isValid}“ не е позволен в потребителското име.";
		}
	}


	protected function buildContent() {
		$returnto = $this->out->hiddenField('returnto', $this->returnto);
		$username = $this->out->textField('username', '', $this->username, 25, 50, 1);
		$password = $this->out->passField('password', '', '', 25, 40, 2);
		$passwordRe = $this->out->passField('passwordRe', '', '', 25, 40, 3);
		$realname = $this->out->textField('realname', '', $this->realname, 25, 50, 4);
		$email = $this->out->textField('email', '', $this->email, 25, 60, 5);
		$news = $this->out->checkbox('news', '', false,
			'Получаване на месечно новинарско писмо', '', 6);
		$submit = $this->out->submitButton('Регистриране', '', 7);
		return <<<EOS
<p>Чрез <a href="#registerform" title="Към регистрационния формуляр">долния формуляр</a> можете да се регистрирате в <em>$this->sitename</em>. <a href="#why-register" title="Разяснения относно нуждата от регистрация">По-долу</a> можете да се осведомите дали въобще ви е нужно това.</p>
<p>Ако вече сте се регистрирали, няма нужда да го правите още веднъж. Можете направо да <a href="$this->root/login">влезете</a>.</p>
<p><strong>Внимание:</strong> Регистрацията в библиотеката няма нищо общо с регистрацията във <a href="/phpBB2/" title="Сайтовия форум">форума</a>. </p>
<p>Можете да ползвате кирилица, когато въвеждате потребителското си име.</p>
<p>Като парола се опитайте да изберете нещо, което за вас да е лесно запомнящо се, а за останалите — невъзможно за разгадаване.</p>
<p>Попълването на полетата, обозначени със звездички, не е задължително.</p>

<form action="{FACTION}" method="post" id="registerform">
	<fieldset style="width:28em; margin:1em auto" align="center">
	<a name="registerform"> </a>
		$returnto
	<legend>Регистриране</legend>
	<table>
	<tr>
		<td style="text-align:right"><label for="username">Потребителско име:</label></td>
		<td>$username</td>
	</tr><tr>
		<td style="text-align:right"><label for="password">Парола:</label></td>
		<td>$password</td>
	</tr><tr>
		<td style="text-align:right"><label for="passwordRe">Паролата още веднъж:</label></td>
		<td>$passwordRe</td>
	</tr><tr>
		<td style="text-align:right"><label for="realname">Истинско име<a id="n1" name="n1" href="#nb1">*</a>:</label></td>
		<td>$realname</td>
	</tr><tr>
		<td style="text-align:right"><label for="email">Е-поща<a id="n2" name="n2" href="#nb2">**</a>:</label></td>
		<td>$email</td>
	</tr><tr>
		<td colspan="2">$news</td>
	</tr><tr>
		<td colspan="2" align="center">$submit</td>
	</tr>
	</table>
	</fieldset>
</form>

<p><a id="nb1" name="nb1" href="#n1">*</a>, <a id="nb2" name="nb2" href="#n2">**</a>
Посочването на истинско име и валидна е-поща ще
позволи по-доброто общуване между вас и библиотеката.
Можете например да поискате нова парола, ако забравите сегашната си, или пък
да се абонирате за месечно новинарско писмо. Адресът ви няма да се публикува
на страниците.</p>

<h2 id="why-register">Необходима ли е регистрацията?</h2>
<p><a name="why-register"> </a>
Текстовете в <em>$this->sitename</em> могат да се четат и свалят и без регистрация.
Евентуална регистрация ще ви позволи да ползвате следните функции:</p>
<ul>
	<li>oтбелязване на текстовете като прочетени;</li>
	<li>добавяне на нови произведения в <a href="$this->root/work" title="Списък на произведения, подготвящи се за добавяне в библиотеката">списъка с подготвящите се</a>;</li>
	<li>получаване на месечно новинарско писмо — може да се избере в настройките,
	като освен това е нужно да посочите и правилна е-поща;</li>
	<li>редактиране на <a href="$this->root/label" title="Преглед на произведенията по етикет">етикетите</a> на произведенията.</li>
	<!--li>възможност да добавяте свои произведения в раздела „Млади автори“, както и
	биографична информация на ваша лична страница.</li-->
</ul>

<p>Някои регистрирани потребители също така имат право да правят промени на
данните в <em>$this->sitename</em>, например поправка на грешки в текстовете,
добавяне или редактиране на произведения, автори, преводачи, поредици.</p>
<p>Ако все още желаете да се регистрирате, скачайте на <a href="#registerform" title="Към регистрационния формуляр">формуляра</a>.</p>
EOS;
	}
}
?>
