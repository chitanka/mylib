<?php

class SendNewsletterLocalPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'sendNewsletter';
		$this->title = 'Изпращане на новинарско писмо';
		$this->logfile = './log/newsletter.log';
		$this->logfile_not = './log/newsletter-not.log';
		$this->subject = $this->request->value('subject', '');
		$this->message = $this->request->value('content', '');
		$this->checked = (int) $this->request->value('checked', 1, 1);
		$this->emails = (array) $this->request->value('emails');
	}


	protected function processSubmission() {
		if ( empty($this->subject) ) {
			$this->addMessage('Не сте въвели тема на писмото.', true);
			return $this->makeForm();
		}
		$headers = array(
			'Content-type' => 'text/plain; charset=utf-8',
			'From' => SITE_EMAIL,
			'Return-Path' => SITE_EMAIL,
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
				file_put_contents($this->logfile, "$date: $user <$email>\n", FILE_APPEND);
				unset( $this->emails[$user] );
				$sent[] = "$user &lt;$email&gt;";
			} else {
				$notsent[] = "$user &lt;$email&gt; — ".$res->getMessage();
				file_put_contents($this->logfile_not, "'$user' => '$email',\n", FILE_APPEND);
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
		$query = "SELECT username, realname, email FROM /*p*/user
			WHERE news = '1' AND email != ''";
		$result = $this->db->query($query);
		while ( $data = mysql_fetch_assoc($result)) {
			extract($data);
			if ( empty($email) || strpos($email, '@') === false ) {
				continue;
			}
			if ( empty($realname) ) { $realname = $username; }
			$this->emails[$realname] = $email;
		}
		$this->subject = 'Новото в '.$this->sitename;
		$this->message = $this->makeMailMessage();
	}


	protected function makeForm() {
		$recipients = $this->makeRecipientsInput();
		return <<<EOS

<form action="{FACTION}" method="post">
<fieldset>
	<legend>Новинарско писмо</legend>
	Получатели:
	$recipients
	<br /><br />
	<label for="subject">Тема:</label>
	<input type="text" id="subject" name="subject"
		size="30" value="$this->subject" tabindex="1" />
	<br />
	<label for="content">Съдържание:</label>
	<br />
	<textarea id="content" name="content" cols="80" rows="30"
		 tabindex="2">$this->message</textarea>
	<br />
	<input type="submit" value="Изпращане" tabindex="3" />
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
http://purl.org/NET/mylib

EOS;
	}


	protected function makeRecipientsInput() {
		$c = '';
		$ch = $this->checked ? ' checked="checked"' : '';
		$fch = false;
		foreach ($this->emails as $user => $email) {
			#if ($user == 'Drago') { $fch = true; continue; }
			#if (!$fch) continue;
			$enc = base64_encode("$user\t$email");
			$c .= <<<EOS
		<br />
		<input type="checkbox" id="m$enc" name="emails[$enc]"$ch />
		<label for="m$enc">$user &lt;$email&gt;</label>
EOS;
		}
		return $c;
	}
}
?>
