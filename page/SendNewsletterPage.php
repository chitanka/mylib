<?php

class SendNewsletterPage extends Page {

	protected
		$logFile = 'log/newsletter.log',
		$logFileNot = 'log/newsletter-not.log';

	public function __construct() {
		parent::__construct();
		$this->action = 'sendNewsletter';
		$this->title = 'Изпращане на новинарско писмо';
		$this->subject = $this->request->value('subject', '');
		$this->message = $this->request->value('content', '');
		$this->checked = (int) $this->request->value('checked', 1, 1);
		$emailsRow = (array) $this->request->value('emails');
		$emailsRow = array_keys($emailsRow);
		$this->emails = array();
		foreach ($emailsRow as $row) {
			list($user, $email) = explode("\t", base64_decode($row));
			$this->emails[$user] = $email;
		}
		// TODO remove
		$this->emails = (array) $this->request->value('emails');
	}


	protected function processSubmission() {
		if ( empty($this->subject) ) {
			$this->addMessage('Не сте въвели тема на писмото.', true);
			return $this->makeForm();
		}
		$headers = array(
			'Content-type' => 'text/plain; charset=utf-8',
			'From' => NEWS_EMAIL,
			'Reply-To' => NEWS_EMAIL,
			'Subject' => header_encode($this->subject),
			'X-Mailer' => 'MyLib mailer',
		);
		$sent = $notsent = array();
		$date = date('Y-m-d H:i:s');
		$mailer = Setup::mailer();
		foreach ($this->emails as $user => $email) {
			$to = header_encode($user) . " <$email>";
			$res = $mailer->send($to, $headers, $this->message);
			if ( $res === true ) {
				file_put_contents($this->logFile, "$date: $user <$email>\n", FILE_APPEND);
				unset( $this->emails[$user] );
				$sent[] = "$user &lt;$email&gt;";
			} else {
				$notsent[] = "$user &lt;$email&gt; — ".$res->getMessage();
				file_put_contents($this->logFileNot, "'$user' => '$email',\n", FILE_APPEND);
			}
		}

		if ( !empty($notsent) ) {
			$this->addMessage('Писмото не беше изпратено на следните адреси:<br />'.
				implode(', <br />', $notsent), true);
			return $this->makeForm();
		}
		$this->addMessage('Новинарското писмо беше изпратено на следните адреси:<br />'.
			implode(', <br />', $sent));
		return '';
	}


	protected function buildContent() {
		$this->initData();
		return $this->makeForm();
	}


	protected function initData() {
		$sel = array('username', 'realname', 'email');
		$key = array('news' => true, 'email' => array('LIKE', '%@%'));
		$res = $this->db->select(User::DB_TABLE, $key, $sel);
		while ( $data = $this->db->fetchAssoc($res)) {
			extract($data);
			fillOnEmpty($realname, $username);
			$this->emails[$realname] = $email;
		}
		$this->subject = 'Новото в '.$this->sitename;
		$this->message = $this->makeMailMessage();
	}


	protected function makeForm() {
		$recipients = $this->makeRecipientsInput();
		$subject = $this->out->textField('subject', '', $this->subject, 30, 255, 1);
		$content = $this->out->textarea('content', '', $this->message, 30, 80, 2);
		$submit = $this->out->submitButton('Изпращане', '', 3);
		return <<<EOS

<form action="{FACTION}" method="post">
<fieldset>
	<legend>Новинарско писмо</legend>
	Получатели:
	$recipients<br /><br />
	<label for="subject">Тема:</label>
	$subject<br />
	<label for="content">Съдържание:</label><br />
	$content<br />
	$submit
</fieldset>
</form>
EOS;
	}


	protected function makeMailMessage() {
		$y = date('Y');
		$m = mystrtolower( monthName( date('n', strtotime('last month')) ) );
		return <<<EOS
[Kodirane: UTF-8]

Здравейте!

Ето списък на произведенията, които бяха добавени в $this->sitename
през месец $m $y:

СПИСЪК

Приятно четене!

Борислав Манолов
$this->sitename
$this->purl
EOS;
	}


	protected function makeRecipientsInput() {
		$c = '';
		$ch = $this->checked ? ' checked="checked"' : '';
		foreach ($this->emails as $user => $email) {

			// TODO remove
			$c .= "\n<br/>'$user'=> '$email',"; continue;


			$enc = base64_encode("$user\t$email");
			$cemail = $this->out->checkbox("emails[$enc]", "m$enc",
				$this->checked, "$user &lt;$email&gt;");
			$c .= '<br/>'.$cemail;
		}
		return $c;
	}
}
