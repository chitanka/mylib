<?php

class MailPage extends Page {

	protected $mailSuccessMessage, $mailFailureMessage, $mailTo,
		$mailFrom, $mailSubject = '';
	protected $extraMailHeaders = array();

	public function __construct() {
		parent::__construct();
		$this->action = 'mail';
		$this->mailTo = ADMIN_EMAIL;
		$this->mailFrom = SITE_EMAIL;
		$this->mailSubject = 'Тема на писмото';
		$this->mailSuccessMessage = 'Съобщението беше изпратено.';
		$this->mailFailureMessage = 'Изглежда е станал някакъв фал при
			изпращането. Ако желаете, пробвайте още веднъж.';
		$this->mailMessage = '';
	}


	protected function processSubmission() {
		error_reporting(E_ALL);
		$mailer = Setup::mailer();
		$res = $mailer->send($this->mailTo, $this->makeMailHeaders(), $this->makeMailMessage());
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
			'Subject' => header_encode($this->mailSubject),
			'X-Mailer' => 'MyLib mailer',
		);
		return array_merge($headers, $this->extraMailHeaders);
	}

	protected function makeSubmissionReturn() { return ''; }

	protected function makeForm() { return ''; }

	protected function makeMailMessage() { return $this->mailMessage; }

}
?>
