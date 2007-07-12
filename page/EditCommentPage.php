<?php
class EditCommentPage extends CommentPage {

	public function __construct() {
		parent::__construct();
		$this->action = 'editComment';
		$this->title = 'Редактиране на читателски мнения';
		$this->showMode = (int) $this->request->value('mode', 0, 1);
		if ($this->showMode < -1 || $this->showMode > 1) $this->showMode = 0;
		#$this->initData();
	}


	protected function buildContent() {
		return $this->makeAllCommentsForm($this->makeAllComments());
	}


	protected function makeAllCommentsForm($comments) {
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS

<form action="{FACTION}" method="post">
$comments
	<div>$submit</div>
</form>
EOS;
	}
}
