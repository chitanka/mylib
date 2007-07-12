<?php
class EditWikiPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'editWiki';
		$this->file = $this->request->value('file', 'main', 1);
		$this->title = "Редактиране на страница „{$this->file}“";
		$this->content = str_replace("\r", '', $this->request->value('content'));
		$this->replace = $this->request->checkbox('replace');
	}


	protected function processSubmission() {
		if ( $this->replace ) {
			require 'include/replace.php';
			$this->content = my_replace($this->content);
		}
		file_put_contents($this->filename(), $this->content);
		$this->addMessage('Промените бяха съхранени.');
		return $this->makeEditForm();
	}


	protected function buildContent() {
		$this->initData();
		return $this->makeEditForm();
	}


	protected function filename() {
		return $GLOBALS['contentDirs']['wiki'] . $this->file;
	}


	protected function initData() {
		$this->content = @file_get_contents($this->filename());
	}


	protected function makeEditForm() {
		$file = $this->out->hiddenField('file', $this->file);
		$content = $this->out->textarea('content', '', $this->content, 25, 85);
		$replace = $this->out->checkbox('replace', '', false, 'Оправяне на кавички и тирета');
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS

<form action="{FACTION}" method="post">
	<fieldset>
		<legend>Страница $this->file</legend>
		$file
		<label for="content">Съдържание:</label><br />
		$content<br />
		$replace<br />
		$submit
	</fieldset>
</form>
EOS;
	}

}
