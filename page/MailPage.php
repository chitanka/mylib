<?php

class MailPage extends Page {

	protected $logFile = 'log/email';
	protected $mailSuccessMessage, $mailFailureMessage, $mailTo,
		$mailFrom, $mailSubject = '';
	protected $extraMailHeaders = array();

	public function __construct() {
		parent::__construct();
		$this->action = 'mail';
		$this->mailTo = ADMIN_EMAIL_ENC;
		$this->mailFrom = SITE_EMAIL_ENC;
		$this->mailSubject = 'Тема на писмото';
		$this->mailSuccessMessage = 'Съобщението беше изпратено.';
		$this->mailFailureMessage = 'Изглежда е станал някакъв фал при
			изпращането. Ако желаете, пробвайте още веднъж.';
		$this->mailMessage = '';
	}


	protected function processSubmission() {
		$mailer = Setup::mailer();
		$message = $this->makeMailMessage();
		$headers = $this->makeMailHeaders();
		$res = $mailer->send($this->mailTo, $headers, $message);
		$this->logEmail($message, $headers);
		if ( $res !== true ) {
			$this->addMessage($this->mailFailureMessage .
				'<br />Съобщението за грешка, между другото, гласи: <code>'.
				htmlspecialchars($res->getMessage()) .'</code>', true);
			return $this->buildContent();
		}
		$this->addMessage($this->mailSuccessMessage);
		return $this->makeSubmissionReturn();
	}


	protected function buildContent() {
		return $this->makeForm();
	}


	protected function makeMailHeaders() {
		$headers = array(
			'Content-type' => 'text/plain; charset=utf-8',
			'From' => $this->mailFrom,
			'Reply-To' => $this->mailFrom,
			'Subject' => header_encode($this->mailSubject),
			'X-Mailer' => 'Mylib',
		);
		return array_merge($headers, $this->extraMailHeaders);
	}

	protected function makeSubmissionReturn() { return ''; }

	protected function makeForm() { return ''; }

	protected function makeMailMessage() { return $this->mailMessage; }


	protected function makeFullAddress($user, $email) {
		fillOnEmpty($user, 'Анонимен');
		fillOnEmpty($email, 'anonymous@anonymous.net');
		return header_encode($user) ." <$email>";
	}

	protected function logEmail($message, $headers) {
		$sheaders = '';
		foreach ($headers as $header => $value) {
			$sheaders .= "$header: $value\n";
		}
		$date = date('Y-m-d H:i:s');
		$logString = <<<EOS
+++ EMAIL +++
[$date]
$sheaders
Subject: $this->mailSubject
To: $this->mailTo
Message:
$message
--- EMAIL ---

EOS;
		file_put_contents($this->logFile, $logString, FILE_APPEND);
	}
}
