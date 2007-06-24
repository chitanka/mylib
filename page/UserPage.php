<?php

class UserPage extends Page {

	protected $contribLimit = 20, $defListLimit = 100, $maxListLimit = 400,
		$colCount = 4;

	public function __construct() {
		parent::__construct();
		$this->action = 'user';
		$this->contentDir = $GLOBALS['contentDirs']['user'];
		$this->mainDbTable = User::MAIN_DB_TABLE;
		$this->username = $this->request->value('username', NULL, 1);
		$this->setDefaultTitle();
		$this->userpage = $this->request->value('userpage');
		$this->q = $this->request->value($this->FF_QUERY, '');
		$this->initPaginationFields();
	}


	protected function buildContent() {
		if ( is_null($this->username) ) {
			$this->title = 'Списък на потребителите';
			$maxlimit = $this->db->getCount($this->mainDbTable, $this->getListDbKey());
			$qfields = empty($this->q) ? array() : array($this->FF_QUERY => $this->q);
			$pagelinks = $this->makePrevNextPageLinks($maxlimit, 0, $qfields);
			return $this->makeSearchForm() . $pagelinks . $this->makeList() . $pagelinks;
		}
		if ( !$this->userExists() ) {
			$this->title = 'Няма такъв потребител';
			return;
		}
		return $this->makeEditLink() . $this->makeAllUsersLink() .
			$this->makeHTML() . $this->makeContribList();
	}


	protected function userExists() {
		$key = array('username' => $this->username);
		$sel = array('id userId', 'username', 'realname');
		$res = $this->db->select($this->mainDbTable, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			$this->addMessage("Няма потребител с име <strong>$this->username</strong>.", true);
			$this->userId = 0;
			$this->userpage = '';
			return false;
		}
		extract2object($data, $this);
		$this->filename = $this->contentDir . $this->userId;
		return true;
	}


	protected function makeHTML() {
		$c = '';
// 		if ( !empty($this->realname) ) {
// 			$nameEnc = urlencode($this->realname);
// 			$c .= "\n<p style='margin-bottom:1em'>Истинско име: <a href='$this->root/author/$nameEnc' title='Списък на публикуваните произведения на $this->realname'>$this->realname</a></p>";
// 		}
		if ( !file_exists($this->filename) ) {
			return '';
		}
		$parser = new Sfb2HTMLConverter($this->filename);
		$parser->parse();
		return $this->userpage = $parser->text;
	}


	protected function makeContribList() {
		$contribCnt = $this->getContribCount();
		if ($contribCnt == 0) return '';
		$page = PageManager::buildPage('history');
		$page->date = -1;
		$page->extQS = ', u.username';
		$page->extQF = "LEFT JOIN /*p*/user_text ut ON t.id = ut.text
			LEFT JOIN /*p*/$this->mainDbTable u ON ut.user = u.id";
		$page->extQW = 'ut.user = '.$this->userId;
		$list = $page->makeListByDate(0, false);
		return <<<EOS

<h2>Сканирани или обработени текстове</h2>
$list
EOS;
	}


	protected function getContribCount() {
		$key = array('user' => $this->userId);
		return $this->db->getCount('user_text', $key);
	}


	protected function makeEditLink() {
		return $this->username == $this->user->username
			? "<p style='font-size:small; text-align:right' title='Редактиране на личната страница'>[<a href='$this->root/editOwnPage'>редактиране</a>]</p>"
			: '';
	}


	protected function makeAllUsersLink() {
		return "<p style='font-size:small; text-align:right' title='Преглед на всички потребители'><a href='{FACTION}'>Всички потребители</a></p>";
	}


	protected function setDefaultTitle() {
		$this->title = 'Лична страница на '. $this->username;
	}


	protected function makeList() {
		$sel = array('username', 'email', 'allowemail', '`group`');
		$q = $this->db->selectQ($this->mainDbTable, $this->getListDbKey(), $sel,
			'username ASC', $this->loffset, $this->llimit);
		$this->items = array();
		$this->db->iterateOverResult($q, 'addListItem', $this);
		return $this->out->multicolTable($this->items, $this->colCount);
	}


	public function addListItem($dbrow) {
		extract($dbrow);
		$this->items[] = $this->makeUserLinkWithEmail($username, $email, $allowemail);
		return '';
	}


	protected function getListDbKey() {
		if ( !empty($this->q) ) {
			return array('username' => array('>=', $this->q));
		}
		return array();
	}


	protected function makeSearchForm() {
		$q = $this->out->textField($this->FF_QUERY, '', $this->q, 30, 30);
		$submit = $this->out->submitButton('Показване', '', 0, false);
		return <<<EOS
<form action="{FACTION}" method="get">
<div>
	<label for="$this->FF_QUERY">Разлистване от:</label>
	$q
	$submit
</div>
</form>
EOS;
	}
}
?>
