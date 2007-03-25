<?php
class RunQueryPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'runQuery';
		$this->query = $this->request->value('query');
		$this->default = $this->request->value('default', '');
		$this->param = $this->request->value('db_param', $this->default);
		$this->title = 'Заявка към литературната база от данни';
	}


	protected function processSubmission() {
		if ( empty($this->query) ) { return "Празна заявка!"; }
		$query = str_replace('$1', $this->param, $this->query);
		$result = $this->db->query($query);
		if ( $this->db->affectedRows() > 0 ) {
			$this->addMessage("Заявката <code>$query</code> беше изпълнена.");
		} else {
			$this->addMessage("Заявката <code>$query</code> не беше изпълнена.", true);
		}
		return $this->buildContent();
	}


	protected function buildContent() {
		$this->param = str_replace('"', '&quot;', stripslashes($this->param));
		$box = $this->out->textField('db_param', '', $this->param, 50, 255, 1,
			'', 'style="border:thin solid silver; padding:.1em .2em"');
		$this->query = stripslashes($this->query);
		$query = $this->out->hiddenField('query', $this->query);
		$queryView = str_replace('$1', $box, $this->query);
		$submit = $this->out->submitButton('Изпълнение');
		return <<<EOS

<form action="{FACTION}" method="post">
	<fieldset>
	<legend>Заявка</legend>
	$query
	$queryView
	</fieldset>
	<div>$submit</div>
</form>
EOS;
	}
}
?>
