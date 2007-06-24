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
			array($this->out->link('Автори', 'author'), $this->getAuthorCount()),
			array($this->out->link('Преводачи', 'translator'), $this->getTranslatorCount()),
			array($this->out->link('Заглавия', 'title'), $this->getTextCount()),
			array($this->out->link('Поредици', 'series'), $this->getSeriesCount()),
			array($this->out->link('Етикети', 'label'), $this->getLabelCount()),
			array($this->out->link('Читателски мнения', 'comment'), $this->getCommentCount()),
			array($this->out->link('Литературни новини', 'liternews'), $this->getLiternewsCount()),
			array($this->out->link('Потребители', 'user'), $this->getUserCount()),
		);
		$o = '<div style="float:left; width:33%">'. $this->out->simpleTable('Основни данни', $tdata) .'</div>';
		$o .= '<div style="float:left; width:33%">'.$this->makePersonCountryStats(1) .'</div>';
		$o .= '<div style="float:left; width:33%">'.$this->makeTextTypeStats() .'</div>';
		return $o;
	}


	protected function makeTextTypeStats() {
		$q = $this->db->selectQ('text', array(), array('type', 'COUNT(*) count'),
			'count DESC',  0, 0, 'type');
		$this->textTypeItems = array();
		$this->db->iterateOverResult($q, 'getTextTypeStatsItem', $this);
		#ksort($this->textTypeItems);
		$l = $curRowClass = '';
		foreach ($this->textTypeItems as $name => $tc) {
			extract($tc);
			$qvars = array('type' => $type);
			if ($count < $this->maxCountToList) $qvars['mode'] = 'simple';
			$curRowClass = $this->out->nextRowClass($curRowClass);
			$link = $this->out->link($name, 'title', $qvars);
			$l .= "<tr class='$curRowClass'><td>$link</td><td>$count</td></tr>";
		}
		return "<div><table class='content'><caption>Произведения по форма</caption>$l</table></div>";
	}

	public function getTextTypeStatsItem($dbrow) {
		$this->textTypeItems[ $GLOBALS['typesPl'][ $dbrow['type'] ] ] = $dbrow;
	}

	protected function makePersonCountryStats($role) {
		$q = $this->db->selectQ('person', array("(role & $role)"),
			array('country', 'COUNT(*) count'), 'count DESC, country ASC',  0, 0, 'country');
		$this->personCountryItems = array();
		$this->db->iterateOverResult($q, 'getPersonCountryStatsItem', $this);
		$t = $this->out->simpleTable('Автори по държава', $this->personCountryItems);
		return $t;
	}

	public function getPersonCountryStatsItem($dbrow) {
		extract($dbrow);
		$link = $this->out->link(countryName($country, '(Невъведена)'), 'author',
			array('country' => $country, 'mode' => 'simple'));
		$this->personCountryItems[] = array($link, $count);
	}


	protected function getTextCount() {
		return $this->db->getCount('text');
	}

	protected function getSeriesCount() {
		return $this->db->getCount('series');
	}

	protected function getLabelCount() {
		return $this->db->getCount('label');
	}

	protected function getAuthorCount() {
		return $this->getPersonCount(1);
	}

	protected function getTranslatorCount() {
		return $this->getPersonCount(2);
	}

	protected function getPersonCount($role) {
		return $this->db->getCount('person', array("(role & $role)"));
	}

	protected function getCommentCount() {
		return $this->db->getCount('comment', array('`show`' => true));
	}

	protected function getLiternewsCount() {
		return $this->db->getCount('liternews', array('`show`' => true));
	}

	protected function getUserCount() {
		return $this->db->getCount('user');
	}

}
?>
