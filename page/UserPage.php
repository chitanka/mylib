<?php

class UserPage extends Page {

	const DB_TABLE = User::DB_TABLE;
	protected
		$contribLimit = 50, $defListLimit = 100, $maxListLimit = 400,
		$colCount = 4;

	public function __construct() {
		parent::__construct();
		$this->action = 'user';
		$this->contentDir = $GLOBALS['contentDirs']['user'];
		$this->username = $this->request->value('username', null, 1);
		$this->setDefaultTitle();
		$this->userpage = $this->request->value('userpage');
		$this->climit = $this->request->value('climit', 1);
		$this->q = $this->request->value(self::FF_QUERY, '');
		$this->initPaginationFields();
	}


	protected function buildContent() {
		if ( empty($this->username) ) {
			$this->title = 'Списък на потребителите';
			$maxlimit = $this->db->getCount(self::DB_TABLE, $this->getListDbKey());
			$qfields = empty($this->q) ? array() : array(self::FF_QUERY => $this->q);
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
		$res = $this->db->select(self::DB_TABLE, $key, $sel);
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
		$page->extQF = array(
			DBT_USER_TEXT .' ut' => 't.id = ut.text',
			self::DB_TABLE .' u' => 'ut.user = u.id');
		$page->extQW = array('ut.user' => $this->userId);
		$limit = $this->climit == 0 ? 0 : $this->contribLimit;
		$list = $page->makeListByDate($limit, false);
		$showAllLink = '';
		if ($this->climit != 0 && $contribCnt > $this->contribLimit) {
			$p = array(self::FF_ACTION=>$this->action, 'username'=>$this->username, 'climit'=> 0);
			$showAllLink = '<div class="pagelinks">'.$this->out->internLink("Показване на всичките $contribCnt произведения", $p, 2).'</div>';
		}
		return <<<EOS

<h2>Сканирани или обработени текстове</h2>
$list
$showAllLink
EOS;
	}


	protected function getContribCount() {
		$key = array('user' => $this->userId);
		return $this->db->getCount(DBT_USER_TEXT, $key);
	}


	protected function makeEditLink() {
		if ($this->username != $this->user->username) {
			return '';
		}
		$link = $this->out->internLink('редактиране', 'editOwnPage', 1,
			'Редактиране на личната страница');
		return "<p style='font-size:small; text-align:right'>[$link]</p>";
	}


	protected function makeAllUsersLink() {
		$link = $this->out->link('{FACTION}', 'Всички потребители',
			'Преглед на всички потребители');
		return "<p style='font-size:small; text-align:right'>$link</p>";
	}


	protected function setDefaultTitle() {
		$this->title = 'Лична страница на '. $this->username;
	}


	protected function makeList() {
		$sel = array('username', 'email', 'allowemail', '`group`');
		$q = $this->db->selectQ(self::DB_TABLE, $this->getListDbKey(), $sel,
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
		$q = $this->out->textField(self::FF_QUERY, '', $this->q, 30, 30);
		$label = $this->out->label('Разлистване от:', self::FF_QUERY);
		$submit = $this->out->submitButton('Показване', '', 0, false);
		return <<<EOS
<form action="{FACTION}" method="get">
<div>
	{HIDDEN_ACTION}
	$label
	$q
	$submit
</div>
</form>
EOS;
	}
}
