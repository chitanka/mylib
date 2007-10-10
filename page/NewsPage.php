<?php

class NewsPage extends Page {

	const DB_TABLE = DBT_NEWS;
	protected
		$defListLimit = 50, $maxListLimit = 150;


	public function __construct() {
		parent::__construct();
		$this->action = 'news';
		$this->title = 'Новини';
		$this->objId = $this->request->value('objId', 0, 1);
		$this->initPaginationFields();
		$this->dbkey = array();
	}


	public function buildContent() {
		$this->addRssLink();
		return $this->makeNews();
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


	public function makeNewsEntry($fields = array()) {
		extract($fields);
		$timev = '';
		if ( !isset($showtime) || $showtime ) { // show per default
			$time = strtr($time, array(' 00:00:00' => ''));
			$timev = "<em>$time:</em> ";
		}
		$text = wiki2html($text);
		return "<p id='e$id'>$timev$text</p>";
	}


	public function makeSqlQuery($limit = 0, $offset = 0, $order = null) {
		fillOnEmpty($limit, $this->llimit);
		fillOnEmpty($offset, $this->loffset);
		fillOnEmpty($order, 'DESC');
		if ( !empty($this->objId) && is_numeric($this->objId) ) {
			$this->dbkey = array('id' => $this->objId);
		}
		$qa = array(
			'SELECT' => 'm.*, u.username',
			'FROM' => self::DB_TABLE .' m',
			'LEFT JOIN' => array(User::DB_TABLE .' u' => 'm.user = u.id'),
			'WHERE' => $this->dbkey,
			'ORDER BY' => "`time` $order, id $order",
			'LIMIT' => array($offset, $limit)
		);
		return $this->db->extselectQ($qa);
	}
}
