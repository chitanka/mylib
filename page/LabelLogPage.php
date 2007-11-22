<?php
class LabelLogPage extends Page {

	const DB_TABLE = DBT_LABEL_LOG;

	public function __construct() {
		parent::__construct();
		$this->action = 'labelLog';
		$this->title = 'Преглед на промените, свързани с етикетите';
	}


	protected function processSubmission() {
		$del = array_keys((array) $this->request->value('ch'));
		if ( !empty($del) ) {
			if ( $this->db->delete(self::DB_TABLE, array('id'=>array('IN', $del))) ) {
				$this->addMessage('Избраните записи бяха изтрити.');
			}
		}
		return $this->buildContent();
	}


	protected function buildContent() {
		$this->initData();
		$style = <<<EOS
	.delLab { background-color: #fdd; }
	.addLab { background-color: #dfd; }
	.newLab { background-color: #afa; }
EOS;
		$this->addStyle($style);
		return $this->makeLogList();
	}


	protected function makeLogList() {
		$qa = array(
			'SELECT' => 'll.*, u.username',
			'FROM' => self::DB_TABLE .' ll',
			'LEFT JOIN' => array(User::DB_TABLE .' u' => 'll.user = u.id'),
			'ORDER BY' => 'text, time',
		);
		$q = $this->db->extselectQ($qa);
		$this->classes = array('-'=>'delLab', '+'=>'addLab', '*'=>'newLab');
		$l = $this->db->iterateOverResult($q, 'makeLogListItem', $this);
		if ( empty($l) ) {
			return '<p>Няма записи.</p>';
		}
		$submit = $this->out->submitButton('Изтриване на избраните записи');
		return <<<EOS

<script type="text/javascript">
	function checkAll() {
		for (var i=0; i < document.forms[0].elements.length; i++) {
			var el = document.forms[0].elements[i];
			if ( !el.name.match(/^ch/) ) {
				continue;
			}
			el.checked = true;
		}
		return false;
	}
</script>
<form action="{FACTION}" method="post">
<ul>$l
</ul>
	<div id="checker">Избиране на <a href="#" onclick="javascript:return checkAll();">всички</a></div>
	<div>$submit</div>
</form>
EOS;
	}


	public function makeLogListItem($dbrow) {
		extract($dbrow);
		$tlink = $this->makeSimpleTextLink($title, $text);
		$alink = $this->makeAuthorLink($author);
		$labels = explode(',', $labels);
		$labelstr = '';
		foreach ($labels as $label) {
			$labelstr .= $this->makeLabelLink($this->labels[$label]) .
				$this->makeEditLabelLink($label).', ';
		}
		$labelstr = rtrim($labelstr, ', ');
		$revert = $this->makeRevertLink($text, $action, $labels);
		$ch = $this->out->checkbox("ch[$id]", "ch$id", false, "<tt>$time</tt>");
		$userlink = $this->makeUserLink($username);
		return "\n<li class='{$this->classes[$action]}'>$ch $tlink ($alink) $action $labelstr &nbsp; ($userlink) [$revert]</li>";
	}


	protected function makeRevertLink($text, $action, $labels) {
		$reqvar = $action == '-' ? 'new' : 'old';
		$params = array(self::FF_ACTION=>'editTextLabels', 'textId'=>$text, 'subaction'=>'revert');
		foreach ($labels as $label) {
			$params[$reqvar.'[]'] = $label;
		}
		return $this->out->internLink('връщане', $params);
	}


	protected function initData() {
		$this->labels = $this->db->getObjects(DBT_LABEL);
	}

}
