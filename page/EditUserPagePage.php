<?php

class EditUserPagePage extends UserPage {


	public function __construct() {
		parent::__construct();
		$this->action = 'editUserPage';
		$this->save = $this->request->value('save');
		$this->preview = $this->request->value('preview');
	}


	protected function processSubmission() {
		if ( !$this->userExists() ) { return; }
		require_once 'include/replace.php';
		$this->userpage = my_replace($this->userpage);
		if ( isset($this->preview) ) {
			$html = $this->makeHTML();
			$this->addMessage('Това е само предварителен преглед. Страницата все още не е съхранена.');
			return "\n<div id='previewbox'>\n$html\n</div>".$this->makeEditForm();
		}
		file_put_contents($this->filename, $this->userpage);
		$this->setDefaultTitle();
		return $this->makeEditLink() . $this->makeHTML();
	}


	protected function buildContent() {
		if ( !$this->userExists() ) { return; }
		$this->userpage = file_exists($this->filename) ? file_get_contents($this->filename) : '';
		return $this->makeEditForm();
	}


	protected function makeEditForm() {
		$this->title .= ' — Редактиране';
		$username = $this->out->hiddenField('username', $this->username);
		$userpage = $this->out->textarea('userpage', '', $this->userpage, 20, 80,
			0, 'style="width:95%"');
		$submit1 = $this->out->submitButton('Предварителен преглед', '', 0, 'preview');
		$submit2 = $this->out->submitButton('Съхраняване', '', 0, 'send');
		return <<<EOS

<!--p style="text-align:right"><a href="$this->root/help/edit">Съвети за редактирането</a></p-->
<p>За въвеждане на съдържанието се ползва форматът SFB — същият формат, в който са съхранени текстовете на библиотеката. В скоро време ще се появи и неговото описание. Дотогава се учете от свалените текстове. ;-)</p>
<form action="{FACTION}" method="post">
<div>
	$username
	<label for="userpage">Съдържание:</label><br />
	$userpage<br />
	<!--$submit1-->
	$submit2
</div>
</form>
EOS;
	}

}
