<?php
class CommentPage extends Page {

	const DB_TABLE = DBT_COMMENT;
	protected
		$sortOrder = 'ASC',
		$wheres = array(
			-1 => array('`show`' => false), // only hidden comments
			1 => array('`show`' => true), // only visible comments
			0 => '1'), // all comments
		$viewTypes = array('tree' => 'Дърво', 'list' => 'Списък'),
		$defViewType = 'tree',
		$defListLimit = 20,
		$maxListLimit = 100;


	public function __construct() {
		parent::__construct();
		$this->action = 'comment';
		$this->title = 'Читателски мнения';
		$this->addRssLink();
		$this->reader = $this->user->isAnon()
			? $this->request->value('reader')
			: $this->user->userName();
		$this->comment = $this->request->value('commenttext');
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->chunkId = (int) $this->request->value('chunkId', 1, 2);
		$this->replyto = (int) $this->request->value('replyto', 0);
		$this->viewType = normKey($this->request->value('viewType'),
			$this->viewTypes, $this->defViewType);
		$this->initCaptchaFields();
		$this->showMode = 1; // only visible
		$this->putNr = false;
		$this->initDone = false;
		$this->initPaginationFields();
	}


	protected function processSubmission() {
		if ( empty($this->reader) || empty($this->comment) ) {
			$this->addMessage('Попълнете всички полета!', true);
			return $this->buildContent();
		}
		if ( $this->user->isAnon() && isSpam($this->comment) ) {
			$this->addMessage('Коментарът ви е определен като спам. Вероятно съдържа прекалено много уеб адреси.', true);
			return $this->buildContent();
		}
		if ( !empty($this->textId) ) {
			$this->initData();
		}
		require_once 'include/replace.php';
		$this->comment = my_replace($this->comment);
		if ( $this->request->value('preview') != NULL ) {
			$this->addMessage('Това е само предварителен преглед. Мнението ви все още не е съхранено.');
			return $this->makeComment() . $this->makeForm();
		}
		$showComment = true;
		if ( !$this->verifyCaptchaAnswer(true) ) {
			if ( $this->hasMoreCaptchaTries() ) {
				return $this->makeForm();
			} else {
				$showComment = false;
			}
		}
		$set = array('text' => $this->textId,
			'rname' => $this->reader, 'user' => $this->user->id,
			'ctext' => $this->comment, 'ctexthash' => md5($this->comment),
			'replyto' => $this->replyto, 'time' => date('Y-m-d H:i:s'),
			'show' => $showComment,
		);
		$this->db->insert(self::DB_TABLE, $set, true);
		$this->addMessage('Мнението ви беше получено.');
		if ( !$showComment ) {
			$this->addMessage('Ще бъде показано след преглед от модератор.');
		}
		$this->replyto = $this->comment = '';
		$this->clearCaptchaQuestion();
		return $this->buildContent();
	}


	protected function buildContent() {
		if ( empty($this->textId) ) {
			return $this->makeAllComments($this->llimit, $this->loffset, 'DESC');
		}
		$this->initData();
		return $this->makeComments() . $this->makeForm();
	}


	protected function makeComments() {
		$key = $this->wheres[$this->showMode];
		$key['text'] = $this->textId;
		if ( !empty($this->replyto) ) {
			$key['id'] = $this->replyto;
		}
		$q = $this->db->selectQ(self::DB_TABLE, $key, '*', '`time` '.$this->sortOrder);
		$this->comments = '';
		$this->acomments = $this->acommentsTree = $this->acommentsTmp = array();
		$this->curRowNr = 0;
		$this->db->iterateOverResult($q, 'processCommentDbRow', $this);
		if ( empty($this->acomments) ) {
			$this->addMessage('Няма читателски мнения за произведението.');
			return $this->makeNewCommentLink();
		}
		// TODO правилна инициализация на дървото, ако се почва някъде от средата
		if ( empty($this->acommentsTree) ) {
			$this->acommentsTree = $this->acomments;
		}
		$this->putNr = true;
		if ($this->viewType == 'tree') {
			$this->makeCommentsAsTree($this->acommentsTree);
		} else {
			$this->makeCommentsAsList();
		}
		$newcommentlink = empty($this->replyto) ? $this->makeNewCommentLink() : '';
		return $this->makeViewTypeInput() . $newcommentlink .
			'<div id="readercomments" style="clear:both">'. $this->comments . '</div>'.
			$newcommentlink;
	}


	protected function makeNewCommentLink() {
		return '<p><a href="#postform" onclick="initReply(0)">Пускане на ново мнение</a> ↓</p>';
	}


	protected function makeViewTypeInput() {
		$o = 'Преглед: ';
		$params = array(self::FF_ACTION=>$this->action, 'textId'=>$this->textId,
			'chunkId'=>$this->chunkId);
		foreach ($this->viewTypes as $type => $name) {
			$lparams = $params + array('viewType' => $type);
			$o .= $this->viewType == $type ? "<strong>$name</strong> | "
				: $this->out->internLink($name, $lparams, 3) . ' | ';
		}
		return '<div style="float:right">'. rtrim($o, ' |') .'</div>';
	}


	public function processCommentDbRow($dbrow) {
		$id = $dbrow['id']; $replyto = $dbrow['replyto'];
		$dbrow['nr'] = ++$this->curRowNr;
		$this->acomments[$id] = $dbrow;
		if ( !isset($this->acommentsTmp[$id]) ) {
			$this->acommentsTmp[$id] = array();
		}
		if ( empty($replyto) || !array_key_exists($replyto, $this->acommentsTmp) ) {
			$this->acommentsTree[$id] = & $this->acommentsTmp[$id];
		} else {
			$this->acommentsTmp[$replyto][$id] = & $this->acommentsTmp[$id];
		}
	}

	protected function makeCommentsAsTree($tree, $level = 0, $id = '') {
		$this->comments .= "\n<ul id='sublistof$id'>";
		foreach ($tree as $id => $subtree) {
			$this->comments .= isset($this->acomments[$id])
				? "<li id='it$id' class='lev$level'>". $this->makeComment( $this->acomments[$id] )
				: '';
			if ( is_array($subtree) && !empty($subtree) ) {
				$this->makeCommentsAsTree($subtree, $level + 1, $id);
			}
			$this->comments .= "\n".'</li>';
		}
		$this->comments .= "\n".'</ul>';
	}

	protected function makeCommentsAsList() {
		foreach ($this->acomments as $id => $acomment) {
			$this->comments .= $this->makeComment($acomment);
		}
	}

	/**
	@param $fields array Associative array containing following (optional)
		elements: rname, ctext, user, time, textId, textTitle, author, edit, showtime
	@return string
	*/
	public function makeComment($fields = array()) {
		extract($fields);
		fillOnEmpty($id, 0);
		fillOnEmpty($rname, $this->reader);
		fillOnEmpty($ctext, $this->comment);
		fillOnEmpty($textId, $this->textId);
		$firstrow = $secondrow = '';
		if ( !isset($showtitle) || $showtitle ) { // show per default
			$rnameview = empty($user) ? $rname : $this->makeUserLink($rname);
			$timev = !isset($showtime) || $showtime // show per default
				? ' <small>('. humanDate(@$time) .')</small>' : '';
			$firstrow = empty($textId) || empty($textTitle) ? ''
				: '<p class="firstrow">'.
					$this->makeSimpleTextLink($textTitle, $textId) .
					$this->makeFromAuthorSuffix($fields) .'</p>';
			$acts = '';
			if ( !empty($textId) ) {
				$params = array(self::FF_ACTION=>$this->action, 'textId'=>$textId);
				$replyParams = $params + array('replyto' => $id);
				$attrs = array('class'=>'js', 'onclick'=>"initReply($id)");
				$link = $this->out->internLink('Отговор', $replyParams, 2,
					'Отговор на коментара', $attrs, "e$id");
				if ( empty($this->textId) ) {
					$link .= ' | '. $this->out->internLink('Всички коментари', $params, 2,
						'Всички коментари за произведението');
				}
				$acts = "<span style='float:right'>$link</span>";
			}
			$nr = $this->putNr ? $nr.'. ' : '';
			$secondrow = "<p class='secondrow'>$acts<strong>$nr$rnameview</strong>$timev</p><hr />";
		}
		$ctext = str_replace("\n", "<br/>\n", escapeInput($ctext));
		#$editLink = $this->user->canExecute('editComment')
		#	? ' — '.$this->makeEditCommentLink($ctext) : '';
		$editBoxes = isset($edit) && $edit ? '<br />'.$this->makeEditBoxes($textId) : '';
		return <<<EOS

	<fieldset class="readercomment" id="e$id">
		$firstrow
		$secondrow
		<div class="commenttext" style="clear:both">
		$ctext
		</div>
		$editBoxes
	</fieldset>
	<div id="replyto$id"></div>
EOS;
	}


	protected function makeForm() {
		if ( empty($this->work) ) {
			return '';
		}
		return $this->makeEditForm();
	}


	protected function makeEditForm() {
		$textId = $this->out->hiddenField('textId', $this->textId);
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
		$viewType = $this->out->hiddenField('viewType', $this->viewType);
		$replyto = $this->out->hiddenField('replyto', $this->replyto);
		$reader = $this->user->isAnon()
			? $this->out->textField('reader', '', $this->reader, 40, 160)
			: $this->user->username;
		$comment = $this->out->textarea('commenttext', '', $this->comment, 20, 77);
		$submit1 = $this->out->submitButton('Предварителен преглед', '', 0, 'preview');
		$submit2 = $this->out->submitButton('Пращане', '', 0, 'send');
		$hideform = !empty($this->comment) || !empty($this->replyto) ? ''
			: 'postform.style.display = "none";';
		$nextcid = $this->db->autoIncrementId(self::DB_TABLE);
		$question = $this->makeCaptchaQuestion();
		global $allowableTags;
		$allowHelp = empty($allowableTags) ? ''
			: '<p>Разрешени етикети: &lt;'.implode('&gt;, &lt;', $allowableTags).'&gt;.</p>';
		return <<<EOS

<form action="{FACTION}#e$nextcid" method="post" id="postform">
<p style="margin-top:2em">Задължително е попълването на всички полета, както и писането на кирилица. Коментарите на латиница най-вероятно ще бъдат изтрити.<span id="jshelp"></span></p>
<p>Спазвайте елементарни правописни правила: започвайте изреченията с главна буква; оставяйте интервал след препинателния знак, а не преди него!</p>
$allowHelp
<fieldset style="width:46em">
	<legend>Нов коментар</legend>
	$textId
	$chunkId
	$viewType
	$replyto
	<label for="reader">Име:</label>
	$reader
	<script src="/cyr5ko/cyr5ko6.source.js" type="text/javascript"></script>
	<br />
	<label for="commenttext">Коментар:</label><br />
	$comment<br />
	$question
	$submit1
	$submit2
</fieldset>
</form>
$this->scriptStart
	var help = " Ползвайте кирилизатора, ако нямате възможност да пишете на кирилица!";
	var parent = document.getElementById("jshelp");
	if (parent) parent.appendChild( document.createTextNode(help) );

	var commenttext = document.getElementById("commenttext");
	var postform = document.getElementById("postform");
	$hideform

	// make reply links local to the page
	var links = document.getElementsByTagName("a");
	for (var i=0; i < links.length; i++) {
		var link = links[i];
		if (link.className != "js") { continue; }
		var urlparts = link.href.split("#");
		link.href = "#" + urlparts[1];
	}
$this->scriptEnd
EOS;
	}


	protected function makeEditBoxes($textId) {
		$show = $this->out->checkbox("show[$textId]", "show$textId", false,
			'Показване');
		$del = $this->out->checkbox("del[$textId]", "del$textId", false,
			'Изтриване');
		return <<<EOS

	<br />
	$show
	<div class="error">$del</div>
	<br /><br />
EOS;
	}


	public function makeAllComments($limit = 0, $offset = 0, $order = null, $showPageLinks = true) {
		$sql = $this->makeSqlQuery($limit, $offset, $order);
		$res = $this->db->query($sql);
		if ($this->db->numRows($res) == 0) {
			$this->addMessage('Няма читателски мнения.');
			return '';
		}
		$count = $this->db->getCount(self::DB_TABLE, $this->wheres[$this->showMode]);
		$pagelinks = $showPageLinks
			? $this->makePageLinks($count, $this->llimit, $this->loffset) : '';
		$c = '';
		while ($row = $this->db->fetchAssoc($res)) {
			$row['edit'] = $this->showMode == -1;
			$c .= $this->makeComment($row);
		}
		return $pagelinks . $c . $pagelinks;
	}


	public function makeSqlQuery($limit = 0, $offset = 0, $order = null) {
		if ( is_null($order) ) { $order = $this->sortOrder; }
		$qa = array(
			'SELECT' => 'c.*, t.id textId, t.title textTitle, t.collection,
				GROUP_CONCAT(DISTINCT a.name ORDER BY aof.pos) author',
			'FROM' => self::DB_TABLE .' c',
			'LEFT JOIN' => array(
				DBT_TEXT .' t' => 'c.text = t.id',
				DBT_AUTHOR_OF .' aof' => 't.id = aof.text',
				DBT_PERSON .' a' => 'aof.person = a.id',
			),
			'WHERE' => $this->wheres[$this->showMode],
			'GROUP BY' => 'c.id',
			'ORDER BY' => "`time` $order",
			'LIMIT' => array($offset, $limit)
		);
		return $this->db->extselectQ($qa);
	}


	protected function initData() {
		if ($this->initDone) {
			return true;
		}
		$this->initDone = true;
		$this->work = Work::newFromId($this->textId);
		if ( empty($this->work) ) {
			$this->addMessage("Не съществува текст с номер <strong>$this->textId</strong>.", true);
			return false;
		}
		$this->title .= ' за „'.
			$this->makeSimpleTextLink($this->work->title, $this->textId) .'“';
		if ( !$this->work->collection ) {
			$this->title .= $this->makeFromAuthorSuffix($this->work->author_name);
		}
		return true;
	}

}
