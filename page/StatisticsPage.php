<?php

class StatisticsPage extends Page {

	protected $maxCountToList = 200;

	public function __construct() {
		parent::__construct();
		$this->action = 'statistics';
		$this->title = 'Статистика';
	}


	protected function buildContent() {
		$tdata = array(
			array($this->out->internLink('Автори', 'author'),
				$this->getAuthorCount()),
			array($this->out->internLink('Преводачи', 'translator'),
				$this->getTranslatorCount()),
			array($this->out->internLink('Заглавия', 'title'),
				$this->getTextCount()),
			array($this->out->internLink('Поредици', 'series'),
				$this->getSeriesCount()),
			array($this->out->internLink('Етикети', 'label'),
				$this->getLabelCount()),
			array($this->out->internLink('Читателски мнения', 'comment'),
				$this->getCommentCount()),
			array($this->out->internLink('Литературни новини', 'liternews'),
				$this->getLiternewsCount()),
			array($this->out->internLink('Потребители', 'user'),
				$this->getUserCount()),
		);
		$o = '<div style="float:left; width:33%">'. $this->out->simpleTable('Основни данни', $tdata) .'</div>';
		$o .= '<div style="float:left; width:33%">'.$this->makePersonCountryStats(1) .'</div>';
		$o .= '<div style="float:left; width:33%">'.$this->makeTextTypeStats() .'</div>';
		return $o;
	}


	protected function makeTextTypeStats() {
		$q = $this->db->selectQ(DBT_TEXT, array(), array('type', 'COUNT(*) count'),
			'count DESC',  0, 0, 'type');
		$this->textTypeItems = array();
		$this->db->iterateOverResult($q, 'getTextTypeStatsItem', $this);
		#ksort($this->textTypeItems);
		$l = $curRowClass = '';
		foreach ($this->textTypeItems as $name => $tc) {
			extract($tc);
			$qvars = array(self::FF_ACTION=>'title', 'type' => $type);
			if ($count < $this->maxCountToList) $qvars['mode'] = 'simple';
			$curRowClass = $this->out->nextRowClass($curRowClass);
			$link = $this->out->internLink($name, $qvars, 1);
			$l .= "<tr class='$curRowClass'><td>$link</td><td>$count</td></tr>";
		}
		return "<div><table class='content'><caption>Произведения по форма</caption>$l</table></div>";
	}

	public function getTextTypeStatsItem($dbrow) {
		$this->textTypeItems[ workType($dbrow['type']) ] = $dbrow;
	}

	protected function makePersonCountryStats($role) {
		$q = $this->db->selectQ(DBT_PERSON, array("(role & $role)"),
			array('country', 'COUNT(*) count'), 'count DESC, country ASC',  0, 0, 'country');
		$this->personCountryItems = array();
		$this->db->iterateOverResult($q, 'getPersonCountryStatsItem', $this);
		$t = $this->out->simpleTable('Автори по държава', $this->personCountryItems);
		return $t;
	}

	public function getPersonCountryStatsItem($dbrow) {
		extract($dbrow);
		$p = array(self::FF_ACTION=>'author', 'country'=>$country, 'mode'=>'simple');
		$link = $this->out->internLink(countryName($country, '(Невъведена)'), $p, 1);
		$this->personCountryItems[] = array($link, $count);
	}


	protected function getTextCount() {
		return $this->db->getCount(DBT_TEXT);
	}

	protected function getSeriesCount() {
		return $this->db->getCount(DBT_SERIES);
	}

	protected function getLabelCount() {
		return $this->db->getCount(DBT_LABEL);
	}

	protected function getAuthorCount() {
		return $this->getPersonCount(1);
	}

	protected function getTranslatorCount() {
		return $this->getPersonCount(2);
	}

	protected function getPersonCount($role) {
		return $this->db->getCount(DBT_PERSON, array("(role & $role)"));
	}

	protected function getCommentCount() {
		return $this->db->getCount(DBT_COMMENT, array('`show`' => true));
	}

	protected function getLiternewsCount() {
		return $this->db->getCount(DBT_LITERNEWS, array('`show`' => true));
	}

	protected function getUserCount() {
		return $this->db->getCount(User::DB_TABLE);
	}

}
