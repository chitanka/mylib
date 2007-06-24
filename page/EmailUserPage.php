<?php

class EmailUserPage extends MailPage {


	public function __construct() {
		parent::__construct();
		$this->action = 'emailUser';
		$this->title = 'Пращане на писмо на потребител';
		$this->username = $this->request->value('username');
		$this->mailTo = ADMIN_EMAIL_ENC;
		$this->mailFrom = SITE_EMAIL_ENC;
		$this->mailSubject = $this->request->value('subject', 'Писмо чрез {SITENAME}');
		$this->mailMessage = $this->request->value('message', '');
		$this->mailSuccessMessage = 'Писмото беше изпратено.';
	}


	protected function processSubmission() {
		$err = $this->validateData();
		if ( !empty($err) ) {
			$this->addMessage($err, true);
			return '';
		}
		$err = $this->validateInput();
		if ( !empty($err) ) {
			$this->addMessage($err, true);
			return $this->makeForm();
		}
		$this->mailFrom = $this->makeFullAddress($this->user->username,
			$this->user->email);
		$toname = empty($this->udata['realname'])
			? $this->udata['username'] : $this->udata['realname'];
		$this->mailTo = $this->makeFullAddress($toname, $this->udata['email']);
		return parent::processSubmission();
	}


	protected function buildContent() {
		$err = $this->validateData();
		if ( !empty($err) ) {
			$this->addMessage($err, true);
			return '';
		}
		return $this->makeForm();
	}


	protected function validateData() {
		if ( $this->user->isAnon() ) {
			return 'Необходимо е да се регистрирате и да посочите валидна електронна поща, за да можете да пращате писма на други потребители.';
		}
		if ( empty($this->user->email) ) {
			$settingslink = $this->out->link('настройките си', 'settings');
			return "Необходимо е да посочите валидна електронна поща в $settingslink, за да можете да пращате писма на други потребители.";
		}
		return '';
	}


	protected function validateInput() {
		if ( empty($this->username) ) {
			return 'Не е избран потребител.';
		}
		$this->udata = User::getDataByName($this->username);
		if ( empty($this->udata) ) {
			return "Не съществува потребител с име <strong>$this->username</strong>.";
		}
		if ( $this->udata['allowemail'] == 'false' ) {
			return "<strong>$this->username</strong> не желае да получава писма чрез {SITENAME}.";
		}
		if ( empty($this->mailSubject) ) {
			return 'Въведете тема на писмото!';
		}
		if ( empty($this->mailMessage) ) {
			return 'Въведете текст на писмото!';
		}
		return '';
	}


	protected function makeForm() {
		$ownsettingslink = $this->out->link('настройките ви', 'settings');
		$fromuserlink = $this->makeUserLink($this->user->username);
		$username = $this->out->textField('username', '', $this->username, 30, 30);
		$subject = $this->out->textField('subject', '',
			$this->mailSubject, 60, 200);
		$message = $this->out->textarea('message', '', $this->mailMessage, 20, 80);
		$submit = $this->out->submitButton('Изпращане на писмото');
		return <<<EOS

<p>Чрез долния формуляр можете да пратите писмо на потребител по електронната поща. Адресът, записан в $ownsettingslink, ще се появи в полето „От“ на изпратеното писмо, така че получателят ще е в състояние да ви отговори.</p>
<form action="{FACTION}" method="post">
<fieldset>
	<legend>Писмо</legend>
	<table border="0"><tr>
		<td class="fieldname-left">От:</td>
		<td>$fromuserlink</td>
	</tr><tr>
		<td class="fieldname-left">До:</td>
		<td>$username</td>
	</tr><tr>
		<td class="fieldname-left"><label for="subject">Относно:</label></td>
		<td>$subject</td>
	</tr></table>
	<div><label for="message">Съобщение:</label><br />
	$message</div>
	<p>$submit</p>
</fieldset>
</form>
EOS;
	}


	protected function makeMailMessage() {
		return <<<EOS
$this->mailMessage

----
Това писмо е изпратено чрез $this->sitename ($this->purl).
EOS;
	}

}
?>
