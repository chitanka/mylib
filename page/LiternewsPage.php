<?php
class LiternewsPage extends Page {

	const DB_TABLE = DBT_LITERNEWS;
	protected
		$defListLimit = 10, $maxListLimit = 25,
		$defSrc = 'http://';

	public function __construct() {
		parent::__construct();
		$this->action = 'liternews';
		$this->title = 'Литературни новини';
		$this->newsuser = $this->request->value('newsuser', $this->user->userName());
		$this->newstitle = $this->request->value('newstitle');
		$this->newstext = $this->request->value('newstext');
		$this->newssrc = $this->request->value('newssrc', $this->defSrc);
		$this->objId = $this->request->value('id', 0, 1);
		$this->initPaginationFields();
		$this->dbkey = array(1);#array('`show`' => 'true');
	}


	public function makeNews($limit = 0, $offset = 0, $showPageLinks = true) {
		$res = $this->db->query($this->makeSqlQuery($limit, $offset));
		if ($this->db->numRows($res) == 0) {
			$this->addMessage('Няма новини.');
			return '';
		}
		$c = '';
		while ($row = $this->db->fetchAssoc($res)) {
			$c .= $this->makeNewsEntry($row);
		}
		$count = $this->db->getCount(self::DB_TABLE, $this->dbkey);
		$pagelinks = $showPageLinks
			? $this->makePageLinks($count, $this->llimit, $this->loffset) : '';
		return $pagelinks . $c . $pagelinks;
	}


	public function makeSqlQuery($limit = 0, $offset = 0, $order = null) {
		fillOnEmpty($limit, $this->llimit);
		fillOnEmpty($offset, $this->loffset);
		fillOnEmpty($order, 'DESC');
		if ( !empty($this->objId) && is_numeric($this->objId) ) {
			$this->dbkey = array('id' => $this->objId);
		}
		return $this->db->selectQ(self::DB_TABLE, $this->dbkey, '*',
			"`time` $order", $offset, $limit);
	}


	protected function processSubmission() {
		if ( empty($this->newsuser) || empty($this->newstext) ) {
			$this->addMessage('Попълнете всички полета!');
			return $this->buildContent();
		}
		if ( $this->user->isAnon() && isSpam($this->newstext, 3) ) {
			$this->addMessage('Новината ви е определена като спам. Вероятно съдържа прекалено много уеб адреси.', true);
			return $this->buildContent();
		}
		require_once 'include/replace.php';
		$this->newstitle = my_replace($this->newstitle);
		$this->newstext = my_replace($this->newstext);
		if ( $this->request->value('preview') != NULL ) {
			$this->addMessage('Това е само предварителен преглед. Новината все още не е съхранена.');
			return $this->makeNewsEntry() . $this->makeForm();
		}
		$set = array('id' => $this->objId, 'username' => $this->newsuser,
			'user' => $this->user->id,
			'title' => $this->newstitle, 'text' => $this->newstext,
			'texthash' => md5($this->newstext),
			'src' => ($this->newssrc == $this->defSrc ? '' : $this->newssrc),
			'time' => date('Y-m-d H:i:s'), 'show' => $this->userCanEditNews());
		$this->db->insert(self::DB_TABLE, $set, true);
		$this->addMessage('Новината беше получена.');
		return $this->buildContent();
	}


	protected function buildContent() {
		$this->addRssLink();
		return $this->makeNews() . $this->makeForm();
	}


	public function makeNewsEntry($fields = array()) {
		extract($fields);
		fillOnEmpty($username, $this->newsuser);
		fillOnEmpty($text, $this->newstext);
		fillOnEmpty($src, $this->newssrc);
		fillOnEmpty($id, $this->objId);
		$titlev = '';
		$sendfrom = empty($user) ? $username : $this->makeUserLink($username);
		if ( !isset($showtitle) || $showtitle ) { // show per default
			fillOnEmpty($title, $this->newstitle);
			$titlev = "<strong>$title</strong>";
			if ($this->userCanEditNews()) {
				$titlev .=  ' '. $this->makeEditLiternewsLink($id);
			}
		}
		if ( !empty($time) && (!isset($showtime) || $showtime) ) { // show per default
			$sendfrom .= ' на '. humanDate($time);
		}
		$text = str_replace("\n", "<br/>\n", $text);
		$text = wiki2html($text);
		if ( !empty($src) && $src != $this->defSrc ) {
			$link = $this->out->link($src, 'Източник', $src.' — източник на новината');
			$text .= " ($link)";
		}
		return <<<EOS

	<dl class="post" style="clear:both" id="e$id">
		<dt>$titlev</dt>
		<dd><p class="postauthor">пратена от $sendfrom</p>
		<div>
		$text
		</div>
		</dd>
	</dl>
EOS;
	}


	protected function makeForm() {
		return $this->makeEditFormIntro() . $this->makeEditForm();
	}


	protected function makeEditFormIntro() {
		$ext = $this->userCanEditNews() ? '' : 'Тя обаче ще остане скрита, докато не бъде одобрена.';
		return <<<EOS

<p style="margin-top:3em">Вие също можете да добавите новина. $ext</p>
EOS;
	}


	protected function makeEditForm($showEditBoxes = false, $id = 0) {
		fillOnEmpty($id, $this->objId);
		$objId = $this->out->hiddenField('id', $id);
		$newsuser = $this->out->textField('newsuser', '', $this->newsuser, 30, 160, 1);
		$newstitle = $this->out->textField('newstitle', '', $this->newstitle, 60, 160, 2);
		$newstext = $this->out->textarea('newstext', '', $this->newstext, 20, 76, 3);
		$newssrc = $this->out->textField('newssrc', '', $this->newssrc, 60, 255, 4);
		$boxes = $showEditBoxes ? '<br />'.$this->makeEditBoxes($id) : '';
		$submit1 = $this->out->submitButton('Предварителен преглед', '', 5, 'preview');
		$submit2 = $this->out->submitButton('Пращане', '', 6, 'send');
		return <<<EOS

<form action="{FACTION}" method="post">
<fieldset style="width:46em; clear:both">
	<legend>Новина</legend>
	$objId
	<label for="newsuser">Потребител:</label>
	$newsuser
	<script src="/cyr5ko/cyr5ko6.source.js" type="text/javascript" language="javascript1.2"></script>
	<br />
	<label for="newstitle">Заглавие:</label>
	$newstitle<br />
	<label for="newstext">Съдържание:</label><br />
	$newstext<br />
	<label for="newssrc">Източник:</label>
	$newssrc<br />
	$boxes
	$submit1
	$submit2
</fieldset>
</form>
EOS;
	}


	protected function makeEditBoxes($id) {
		$show = $this->out->checkbox("show[$id]", "show$id", $this->shownews,
			'Показване');
		$del = $this->out->checkbox("del[$id]", "del$id", $this->delnews,
			'Изтриване');
		return <<<EOS

	<br />
	$show
	<div class="error">$del</div>
	<br /><br />
EOS;
	}


	protected function userCanEditNews() {
		return $this->user->canExecute('editLiternews');
	}
}
