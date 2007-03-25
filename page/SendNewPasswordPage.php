<?php

class SendNewPasswordPage extends MailPage {


	public function __construct() {
		parent::__construct();
		$this->action = 'sendNewPassword';
		$this->title = 'Изпращане на нова парола';
		$this->username = $this->request->value('username');
	}


	protected function processSubmission() {
		$key = array('username' => $this->username);
		$res = $this->db->select(User::MAIN_DB_TABLE, $key, 'email');
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			$this->addMessage("Не съществува потребител с име
				<strong>$this->username</strong>.", true);
			return $this->buildContent();
		}
		extract($data);
		if ( empty($email) ) {
			$this->addMessage("За потребителя <strong>$this->username</strong>
				не е посочена електронна поща.", true);
			return $this->buildContent();
		}
		$this->mailTo = header_encode($this->username) ." <$email>";
		$this->newPassword = User::randomPassword();
		User::saveNewPassword($this->username, $this->newPassword);
		$this->mailSubject = "Нова парола за $this->sitename";
		$this->mailSuccessMessage = "Нова парола беше изпратена на електронната поща на
			<strong>$this->username</strong>. Моля,
			<a href='$this->root/login'>влезте отново</a>, след като я получите.";
		$this->mailFailureMessage = 'Изпращането на новата парола не сполучи.';
		return parent::processSubmission();
	}


	protected function makeForm() {
		$username = $this->out->textField('username', '', $this->username, 25, 255, 1);
		$submit = $this->out->submitButton('Изпращане на нова парола', '', 2);
		return <<<EOS

<p>Чрез долния формуляр можете да поискате нова парола за влизане в
<em>$this->sitename</em>, ако сте забравили сегашната си. Такава обаче може да ви бъде
изпратена само ако сте посочили валидна електронна поща в потребителските си данни.</p>
<p>&nbsp;</p>
<form action="{FACTION}" method="post">
<fieldset>
	<legend>Нова парола</legend>
	<label for="username">Потребителско име:</label>
	$username
	$submit
</fieldset>
</form>

EOS;
	}


	protected function makeMailMessage() {
		return <<<EOS
Здравейте!

Някой (най-вероятно вие) поиска да ви изпратим нова парола за
влизане в $this->sitename (http://purl.org/NET/mylib). Ако все пак
не сте били вие, можете да не обръщате внимание на това писмо и да
продължите да ползвате сегашната си парола.

Новата парола за потребителя „{$this->username}“ е
„{$this->newPassword}“ (без кавичките).
След като влезете с нея в $this->sitename, е препоръчително да я
смените с някоя по-лесно запомняща се, за да не се налага пак да
прибягвате до функцията „Изпращане на нова парола“. ;)

$this->sitename

EOS;
	}

}
?>
