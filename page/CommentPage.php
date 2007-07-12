<?php
class CommentPage extends Page {

	protected
		$sortOrder = 'ASC',
		$wheres = array(
			-1 => array('`show`' => false), // only hidden comments
			1 => array('`show`' => true), // only visible comments
			0 => '1'), // all comments
		$defListLimit = 20,
		$maxListLimit = 100;


	public function __construct() {
		parent::__construct();
		$this->action = 'comment';
		$this->title = 'Читателски мнения';
		$this->mainDbTable = 'comment';
		$this->reader = $this->user->isAnon()
			? $this->request->value('reader')
			: $this->user->userName();
		$this->comment = $this->request->value('commenttext');
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->chunkId = (int) $this->request->value('chunkId', 1, 2);
		$this->showMode = 1; // only visible
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
		if ( !empty($this->textId) ) { $this->initData(); }
		require_once 'include/replace.php';
		$this->comment = my_replace($this->comment);
		if ( $this->request->value('preview') != NULL ) {
			$this->addMessage('Това е само предварителен преглед. Мнението ви все още не е съхранено.');
			return $this->makeComment() . $this->makeForm();
		}
		$set = array('text' => $this->textId,
			'rname' => $this->reader, 'user' => $this->user->id,
			'ctext' => $this->comment, 'ctexthash' => md5($this->comment),
			'time' => date('Y-m-d H:i:s'));
		$set['show'] = $this->userCanPostComments() ? 'true' : 'false';
		$this->db->insert($this->mainDbTable, $set, true);
		$this->addMessage('Мнението ви беше получено.');
		return $this->buildContent();
	}


	protected function buildContent() {
		$this->addRssLink();
		if ( empty($this->textId) ) {
			return $this->makeAllComments($this->llimit, $this->loffset);
		}
		$this->initData();
		return $this->makeComments() . $this->makeForm();
	}


	protected function makeComments() {
		$key = $this->wheres[$this->showMode];
		$key['text'] = $this->textId;
		$res = $this->db->select($this->mainDbTable, $key, '*', '`time` '.$this->sortOrder);
		if ($this->db->numRows($res) == 0) {
			$this->addMessage('Няма читателски мнения за произведението.');
			return '';
		}
		$c = '';
		while ($row = $this->db->fetchAssoc($res)) {
			$c .= $this->makeComment($row);
		}
		return $c;
	}


	/**
	 * @param $fields array Associative array containing following (optional)
	 *        elements: rname, ctext, user, time, textId, textTitle, author,
	 *        edit, showtime
	 *
	 * @return string
	 */
	public function makeComment($fields = array()) {
		extract($fields);
		if ( empty($id) ) $id = 0;
		if ( empty($rname) ) $rname = $this->reader;
		if ( empty($ctext) ) $ctext = $this->comment;
		$titlev = '';
		if ( !isset($showtitle) || $showtitle ) { // show per default
			$rnameview = empty($user) ? $rname : $this->makeUserLink($rname);
			$format = 'd.m.Y H:i:s';
			if ( !isset($showtime) || $showtime ) { // show per default
				$timev = empty($time) ? date($format) : date($format, strtotime($time));
				$timev = " <small>($timev)</small>";
			} else {
				$timev = '';
			}
			$textLink = empty($textId) || empty($textTitle) ? ''
				: ' за '.$this->makeSimpleTextLink($textTitle, $textId) .
					$this->makeFromAuthorSuffix($fields);
			$titlev = "<legend><strong>$rnameview</strong>$timev$textLink</legend>";
		}
		$ctext = str_replace("\n", "<br/>\n", $ctext);
		#$editLink = $this->user->canExecute('editComment')
		#	? ' — '.$this->makeEditCommentLink($ctext) : '';
		$editBoxes = isset($edit) && $edit ? '<br />'.$this->makeEditBoxes($textId) : '';
		return <<<EOS

	<fieldset class="readercomment" id="e$id">
		$titlev
		$ctext
		$editBoxes
	</fieldset>
EOS;
	}


	protected function makeForm() {
		if ( empty($this->textTitle) ) return '';
		return $this->makeEditFormIntro() . $this->makeEditForm();
	}


	protected function makeEditFormIntro() {
		$ext = $this->userCanPostComments() ? '' : 'То обаче ще остане скрито, докато не бъде одобрено.';
		return <<<EOS

<p style="margin-top:2em">Можете да дадете и вашето мнение за това произведение. $ext Коментарите на латиница най-вероятно ще бъдат изтрити.<span id="jshelp"></span></p>
	$this->scriptStart
		var help = " Ползвайте кирилизатора, ако нямате възможност да пишете на кирилица.";
		var parent = document.getElementById("jshelp");
		if (parent) parent.appendChild( document.createTextNode(help) );
	$this->scriptEnd
EOS;
	}


	protected function makeEditForm() {
		$textId = $this->out->hiddenField('textId', $this->textId);
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
		$reader = $this->user->isAnon()
			? $this->out->textField('reader', '', $this->reader, 40, 160, 1)
			: $this->user->username;
		$comment = $this->out->textarea('commenttext', '', $this->comment, 10, 76, 2);
		$submit1 = $this->out->submitButton('Предварителен преглед', '', 3, 'preview');
		$submit2 = $this->out->submitButton('Пращане', '', 4, 'send');
		return <<<EOS

<form action="{FACTION}" method="post">
<fieldset style="width:46em">
	<legend>Нов коментар</legend>
	$textId
	$chunkId
	<label for="reader">Име:</label>
	$reader
	<script src="/cyr5ko/cyr5ko6.source.js" type="text/javascript" language="javascript1.2"></script>
	<br />
	<label for="commenttext">Коментар:</label><br />
	$comment<br />
	$submit1
	$submit2
</fieldset>
</form>
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
		$count = $this->db->getCount($this->mainDbTable, $this->wheres[$this->showMode]);
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
		$where = $this->db->makeWhereClause( $this->wheres[$this->showMode] );
		$sql = "SELECT c.*, t.id textId, t.title textTitle, t.collection,
			GROUP_CONCAT(DISTINCT a.name ORDER BY aof.pos) author
			FROM /*p*/$this->mainDbTable c
			LEFT JOIN /*p*/text t ON c.text = t.id
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id
			$where GROUP BY c.id ORDER BY `time` $order";
		if ( $limit > 0 ) $sql .= " LIMIT $offset, $limit";
		return $sql;
	}


	protected function initData() {
		if ($this->initDone) return;
		$this->initDone = true;
		$sql = "SELECT t.title textTitle, t.collection,
				GROUP_CONCAT(DISTINCT a.name ORDER BY aof.pos) author
			FROM /*p*/text t
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id
			WHERE t.id = '$this->textId'
			GROUP BY t.id LIMIT 1";
		$data = $this->db->fetchAssoc( $this->db->query($sql) );
		if ( empty($data) ) {
			$this->addMessage("Не съществува текст с номер <strong>$this->textId</strong>.", true);
			$this->textTitle = $this->orig_title = $this->author = '';
			return;
		}
		extract2object($data, $this);
		$this->title .= " за „<a href='$this->root/text/$this->textId'>$this->textTitle</a>“".
			$this->makeFromAuthorSuffix($data);
	}


	protected function userCanPostComments() {
		return !$this->user->isAnon();
	}

}
