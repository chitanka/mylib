<?php
class CommentPage extends Page {

	protected $sortOrder = 'ASC';
	protected $wheres = array(0 => '1',
		-1 => array('`show`' => false),
		1 => array('`show`' => true) );
	protected $defListLimit = 20, $maxListLimit = 100;


	public function __construct() {
		parent::__construct();
		$this->action = 'comment';
		$this->title = 'Читателски мнения';
		$this->mainDbTable = 'comment';
		$this->reader = $this->request->value('reader', $this->user->userName());
		$this->comment = $this->request->value('commenttext');
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->chunkId = (int) $this->request->value('chunkId', 1, 2);
		// 0 - all comments, -1 - only hidden, 1 - only visible
		$this->showMode = 1;
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
			extract($row);
			$c .= $this->makeComment($rname, $ctext, $time);
		}
		return $c;
	}


	protected function makeComment($name = '', $comment = '', $time = NULL, $textId = NULL, $title = '', $author = '', $edit = false) {
		if ( empty($name) ) $name = $this->reader;
		if ( empty($comment) ) $comment = $this->comment;
		$format = 'd.m.Y H:i:s';
		$time = empty($time) ? date($format) : date($format, strtotime($time));
		$comment = str_replace("\n", "<br/>\n", $comment);
		#$editLink = $this->user->canExecute('editComment')
		#	? ' — '.$this->makeEditCommentLink($comment) : '';
		$textLink = empty($textId) ? ''
			: ' за '.$this->makeSimpleTextLink($title, $textId);
		if ( !empty($author) ) {
			$textLink .= ' от '.$this->makeAuthorLink($author);
		}
		$editBoxes = $edit ? $this->makeEditBoxes($textId) : '';
		return <<<EOS

	<fieldset class="readercomment">
		<legend><strong>$name</strong> <small>($time)</small>$textLink</legend>
		$comment<br />
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
		$reader = $this->out->textField('reader', '', $this->reader, 40, 160, 1);
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
		if ( is_null($order) ) { $order = $this->sortOrder; }
		$where = $this->db->makeWhereClause( $this->wheres[$this->showMode] );
		$count = $this->db->getCount($this->mainDbTable, $this->wheres[$this->showMode]);
		$sql = "SELECT c.*, t.id textId, t.title textTitle, t.collection,
			GROUP_CONCAT(DISTINCT a.name ORDER BY aof.pos) author
			FROM /*p*/$this->mainDbTable c
			LEFT JOIN /*p*/text t ON c.text = t.id
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id
			$where GROUP BY c.id ORDER BY `time` $order";
		if ( $limit > 0 ) $sql .= " LIMIT $offset, $limit";
		$res = $this->db->query($sql);
		if ($this->db->numRows($res) == 0) {
			$this->addMessage('Няма читателски мнения.');
			return '';
		}
		$pagelinks = $showPageLinks
			? $this->makePageLinks($count, $this->llimit, $this->loffset) : '';
		$c = '';
		while ($row = $this->db->fetchAssoc($res)) {
			extract($row);
			$author = $collection == 'true' ? '' : trim($author, ',');
			$c .= $this->makeComment($rname, $ctext, $time, $textId, $textTitle, $author, $this->showMode == -1);
		}
		return $pagelinks . $c . $pagelinks;
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
		$this->authorlink = $this->collection == true ? ''
			: $this->makeAuthorLink($this->author);
		if ( !empty($this->authorlink) ) $this->authorlink = 'от '.$this->authorlink;
		$this->titlelink = "„<a href='$this->root/text/$this->textId'>$this->textTitle</a>“
$this->authorlink";
		$this->title .= ' за '.$this->titlelink;
	}


	protected function userCanPostComments() {
		return $this->user->canExecute('editComments') || !$this->user->isAnon();
	}

}
?>
