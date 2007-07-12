<?php

class NewsPage extends Page {

	protected $defListLimit = 50, $maxListLimit = 150;


	public function __construct() {
		parent::__construct();
		$this->action = 'news';
		$this->title = 'Новини';
		$this->mainDbTable = 'news';
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
		$count = $this->db->getCount($this->mainDbTable, $this->dbkey);
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
		if (empty($limit)) $limit = $this->llimit;
		if (empty($offset)) $offset = $this->loffset;
		if ( is_null($order) ) $order = 'DESC';
		if ( !empty($this->objId) && is_numeric($this->objId) ) {
			$this->dbkey = array('id' => $this->objId);
		}
		$wh = $this->db->makeWhereClause($this->dbkey);
		return "SELECT m.*, u.username FROM /*p*/$this->mainDbTable m
			LEFT JOIN /*p*/user u ON m.user = u.id
			$wh
			ORDER BY `time` $order, id $order LIMIT $offset, $limit";
	}
}
