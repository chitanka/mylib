<?php

class LoginPage extends RegisterPage {

	const MAX_LOGIN_TRIES = 20;

	public function __construct() {
		parent::__construct();
		$this->action = 'login';
		$this->title = 'Вход';
		$this->remember = $this->request->checkbox('remember');
	}


	protected function processSubmission() {
		$err = $this->validateInput();
		if ( !empty($err) ) {
			$this->addMessage($err, true);
			return $this->buildContent();
		}
		$key = array('username' => $this->username);
		$sel = array('id', 'password', 'newpassword');
		$result = $this->db->select($this->mainDbTable, $key, $sel);
		$count = $this->db->numRows($result);
		if ( $count <= 0 ) {
			$this->addMessage("Не съществува потребител с име <strong>$this->username</strong>.", true );
			return $this->buildContent();
		}
		$udata = $this->db->fetchAssoc($result);
		extract($udata);
		$this->password = $this->db->encodePasswordDB($this->password);
		if ( strcmp($this->password, $password) !== 0 ) { // no match
			if ( strcmp($this->password, $newpassword) !== 0 ) { // no match
				if ( User::getLoginTries($this->username) >= self::MAX_LOGIN_TRIES ) {
					$this->addMessage('Направили сте повече от '. self::MAX_LOGIN_TRIES .' неуспешни опита за влизане в библиотеката, затова сметката ви беше блокирана.', true);
					$this->addMessage("Ползвайте страницата „<a href='$this->root/sendNewPassword'>Изпращане на нова парола</a>“, за да получите нова парола за достъп, или се свържете с администратора на библиотеката.", true);
					return $this->redirect();
				}
				$this->addMessage('Въвели сте грешна парола.', true);
				User::incLoginTries($this->username);
				return $this->buildContent();
			}
			User::activateNewPassword($id);
			$password = $newpassword;
		}
		$this->user = User::login($id, $password, $this->remember);
		$this->addMessage("Влязохте в <em>$this->sitename</em> като $this->username.");
		if ( !empty($this->returnto) ) {
			$this->addMessage('Обратно към <a href="'.
				"$this->returnto/cache=0\">предишната страница</a>");
		}
		return '';
	}


	protected function validateInput() {
		if ( empty($this->username) ) {
			return 'Не сте въвели потребителско име.';
		}
		if ( empty($this->password) ) {
			return 'Не сте въвели парола.';
		}
		return '';
	}


	protected function buildContent() {
		$returnto = $this->out->hiddenField('returnto', $this->returnto);
		$username = $this->out->textField('username', '', $this->username, 25, 255, 1);
		$password = $this->out->passField('password', '', '', 25, 40, 2);
		$remember = $this->out->checkbox('remember', '', false, '', '', 3);
		$submit = $this->out->submitButton('Влизане', '', 4);
		return <<<EOS

<p>Ако все още не сте се регистрирали, можете да го <a href="$this->root/register">направите</a> за секунди.</p>
<form action="{FACTION}" method="post">
	<fieldset style="width:38em; margin:1em auto" align="center">
		$returnto
	<legend>Влизане</legend>
	<table>
	<tr>
		<td class="fieldname-left"><label for="username">Потребителско име:</label></td>
		<td>$username <a href="$this->root/sendUsername">Забравено име</a></td>
	</tr><tr>
		<td class="fieldname-left"><label for="password">Парола:</label></td>
		<td>$password <a href="$this->root/sendNewPassword">Забравена парола</a></td>
	</tr><tr>
		<td class="fieldname-left">$remember</td>
		<td><label for="remember">Запомняне на паролата</label></td>
	</tr><tr>
		<td colspan="2" style="text-align:center">$submit</td>
	</tr>
	</table>
	</fieldset>
</form>
EOS;
	}

}
?>
