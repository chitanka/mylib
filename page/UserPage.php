<?php

class UserPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'user';
		$this->contentDir = './content/user';
		$this->mainDbTable = User::MAIN_DB_TABLE;
		$this->username = $this->request->value('username', NULL, 1);
		$this->setDefaultTitle();
		$this->userpage = $this->request->value('userpage');
		$this->contribLimit = 20;
	}


	protected function buildContent() {
		if ( !$this->userExists() ) { return; }
		return $this->makeEditLink().$this->makeHTML().$this->makeContribList();
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
		$this->filename = "$this->contentDir/$this->userId";
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


	protected function setDefaultTitle() {
		$this->title = 'Лична страница на '.$this->username;
	}
}
?>
