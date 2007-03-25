<?php

class SendUsernamePage extends MailPage {


	public function __construct() {
		parent::__construct();
		$this->action = 'sendUsername';
		$this->title = 'Изпращане на потребителско име';
		$this->email = $this->request->value('email');
	}


	protected function processSubmission() {
		$key = array('email' => $this->email);
		$res = $this->db->select(User::MAIN_DB_TABLE, $key, 'username');
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			$this->addMessage("Не съществува потребител с електронна поща
				<strong>$this->email</strong>.", true);
			return $this->buildContent();
		}
		extract($data);
		$this->username = $username;
		$this->mailTo = header_encode($username) ." <$this->email>";
		$this->mailSubject = 'Напомняне за име от '.$this->sitename;
		$this->mailSuccessMessage = "На адреса <strong>$this->email</strong> беше
			изпратено напомнящо писмо. Ако не се сещате и за паролата си,
			ползвайте функцията
			„<a href='$this->root/sendNewPassword'>Изпращане на нова парола</a>“.
			Иначе можете спокойно да <a href='$this->root/login'>влезете</a>.";
		$this->mailFailureMessage = 'Изпращането на напомняне не сполучи.';
		return parent::processSubmission();
	}


	protected function makeForm() {
		$email = $this->out->textField('email', '', $this->email, 25, 255, 1);
		$submit = $this->out->submitButton('Изпращане на потребителското име', '', 2);
		return <<<EOS

<p>Е, на всекиго може да се случи да си забрави името. ;) Няма страшно!
Ако в потребителските си данни сте посочили валидна електронна поща, сега
можете да поискате напомняне за името, с което сте се регистрирали в
<em>$this->sitename</em>.</p>
<p><br /></p>
<form action="{FACTION}" method="post">
<fieldset>
	<legend>Напомняне за име</legend>
	<label for="email">Електронна поща:</label>
	$email
	$submit
</fieldset>
</form>

EOS;
	}


	protected function makeMailMessage() {
		return <<<EOS
Здравейте!

Някой (най-вероятно вие) поиска да ви изпратим потребителското име, с което сте
се регистрирали в $this->sitename (http://purl.org/NET/mylib).
Ако все пак не сте били вие, можете да не обръщате внимание на това писмо.

Потребителското име, отговарящо на адреса $this->email, е
„{$this->username}“ (без кавичките).
Ако не се сещате и за паролата си, ползвайте функцията
„Изпращане на нова парола“ (http://purl.org/NET/mylib/sendNewPassword).

$this->sitename

EOS;
	}

}
?>
