<?php

class HistoryPage extends Page {

	public $extQS = '', $extQF = '', $extQW = '';

	public function __construct() {
		parent::__construct();
		$this->action = 'history';
		$this->title = 'История';
		$this->date = $this->request->value('date', date('Y-n'));
		if ( preg_match('/[^\d-]/', $this->date) ) { $this->date = '0'; }
		$this->orderby = $this->request->value('orderby', 'date');
		$this->media = $this->request->value('media', 'screen');
	}


	protected function buildContent() {
		$inputContent = $this->makeMonthInput() . ' &nbsp; '.
			$this->makeOrderInput();
		$submit = $this->out->submitButton('Обновяване');
		$o = <<<EOS

<p>Тук можете да разгледате кои произведения са били добавени през даден месец.
Текстовете, въведени при създаването на <em>$this->sitename</em>, не са включени
в историята.</p>
<p>Можете да следите последно добавените произведения и чрез <acronym title="Really Simple Syndication">RSS</acronym>: последните
<a href="$this->root/feed/add-rss/10" title="RSS зоб за новинарски четци с последните 10 произведения">10</a>,
<a href="$this->root/feed/add-rss/25" title="RSS зоб за новинарски четци с последните 25 произведения">25</a>,
<a href="$this->root/feed/add-rss/50" title="RSS зоб за новинарски четци с последните 50 произведения">50</a>.</p>
<p class="non-graphic">Към <a href="#before-lists">списъка на произведенията</a></p>
<form action="{FACTION}" method="get">
<div style="text-align:center; margin:1em auto;">
$inputContent
	$submit
</div>
</form>
<p id="before-lists" class="non-graphic"><a name="before-lists"> </a></p>

EOS;
		$list = $this->orderby == 'date'
			? $this->makeListByDate() : $this->makeListByAuthor();
		if ( empty($list) ) {
			$list = '<p>През избрания месец не са добавени произведения.</p>';
		}
		$feedlink = "\t<link rel='alternate' type='application/rss+xml' title='RSS 2.0' href='$this->root/feed/add-rss' />";
		$this->addHeadContent($feedlink);
		return $o . $list;
	}


	public function makeListByDate($limit = 0, $showHeader = true) {
		$this->texts = array();
		$query = $this->makeDbQuery($limit);
		$this->db->iterateOverResult($query, 'addTextForListByDate', $this);
		$o = '';
		foreach ($this->texts as $datekey => $textsForDate) {
			if ($showHeader) {
				list($year, $month) = explode('-', $datekey);
				$monthName = monthName($month);
				$o .= "\n<h2>$monthName $year</h2>\n<ul>";
			}
			foreach ($textsForDate as $textId => $textData) {
				extract($textData);
				$readClass = empty($reader) ? 'unread' : 'read';
				$sauthor = $this->makeAuthorLink($author, 'first', '', '', '');
				if ( $lang != $orig_lang ) {
					$stranslator = (!empty($sauthor) ? ', ' : '').'превод: ';
					$stranslator .= empty($translator)
						? "<a href='$this->root/suggestTranslator/$textId'>неизвестен</a>"
						: $this->makeTranslatorLink($translator, 'first', '', '', '');
				} else { $stranslator = ''; }
				$o .= $this->media == 'screen'
					? "\n\t<li class='$type'><tt>$date</tt> <a class='$readClass' href='$this->root/text/$textId'><em>$title</em></a>".
					' — <span class="extra">'. $this->makeDlLink($textId, $zsize) .'</span>'
					: "\n\t<li>$title [http://purl.org/NET$this->root/text/$textId]";
				if ( !empty($sauthor) || !empty($stranslator) ) {
					$o .= ' — '. $sauthor . $stranslator;
				}
				$o .= '</li>';
			}
			if ($showHeader) {
				$o .= '</ul>';
			}
		}
		if (!$showHeader && !empty($o)) { $o = "<ul>$o</ul>"; }
		return $o;
	}


	public function addTextForListByDate($dbrow) {
		extract($dbrow);
		unset($dbrow['textId']);
		$this->texts[ substr($date, 0, 7) ][$textId] = $dbrow;
	}


	public function makeListByAuthor($limit = 0) {
		$this->texts = array();
		$query = $this->makeDbQuery($limit);
		$this->db->iterateOverResult($query, 'addTextForListByAuthor', $this);
		$o = '';
		$translator = '';
		foreach ($this->texts as $date => $dtexts) {
			ksort($dtexts);
			list($year, $month) = explode('-', $date);
			$monthName = monthName($month);
			$o .= "\n<h2>$monthName $year</h2>\n<ul>";
			foreach ($dtexts as $author => $atexts) {
				$author = $this->makeAuthorLink($author, 'first', '', '', '');
				$o .= "\n<li>$author\n<ul>";
				ksort($atexts);
				foreach ($atexts as $atext) {
					extract($atext);
					if ( $lang != $orig_lang ) {
						$stranslator = ', превод: ';
						$stranslator .= empty($translator)
							? "<a href='$this->root/suggestTranslator/$textId'>неизвестен</a>"
							: $this->makeTranslatorLink($translator, 'first', '', '', '');
					} else { $stranslator = ''; }
					$readClass = empty($reader) ? 'unread' : 'read';
					$dl = $this->makeDlLink($textId, $zsize);
					$o .= $this->media == 'screen'
						? "\n\t<li class='$type'><a class='$readClass' href='$this->root/text/$textId'><em>$title</em></a>".
						' — <span class="extra">'. $this->makeDlLink($textId, $zsize) .'</span>'.$stranslator
						: "\n\t<li>$title [http://purl.org/NET$this->root/text/$textId]</li>";
				}
				$o .= '</ul></li>';
			}
			$o .= '</ul>';
		}
		return $o;
	}


	public function addTextForListByAuthor($dbrow) {
		extract($dbrow);
		$date = substr($date, 0, 7);
		foreach (explode(',', $author) as $lauthor) {
			$this->texts[$date][$lauthor][$title.$textId] = $dbrow;
		}
	}


	protected function makeMonthInput() {
		list($startYear, $startMonth,) = explode('-', $this->getStartDate());
		list($curYear, $curMonth) = explode('-', date('Y-n'));
		if ($startYear < $curYear) {
			$dates[$curYear] = range($curMonth, 1);
			$dates[$startYear] = range(12, $startMonth);
			for ($i = $startYear+1; $i < $curYear; $i++) {
				$dates[$i] = range(12, 1);
			}
		} else {
			$dates[$curYear] = range($curMonth, $startMonth);
		}
		krsort($dates);
		$opts = array();
		foreach ($dates as $year => $months) {
			foreach ($months as $month) {
				$opts["$year-$month"] = monthName($month) .' '. $year;
			}
		}
		$opts[0] = '(Всички месеци)';
		$date = $this->out->selectBox('date', '', $opts, $this->date);
		return '<label for="date">През:</label>&nbsp;'. $date;
	}


	protected function makeOrderInput() {
		$opts = array('date'=>'Дата', 'author'=>'Автор');
		$orderby = $this->out->selectBox('orderby', '', $opts, $this->orderby);
		return '<label for="orderby">Подредба по:</label>&nbsp;'. $orderby;
	}


	protected function makeDbQuery($limit = 0) {
		$qSelect = "SELECT GROUP_CONCAT(DISTINCT a.name) author,
			t.id textId, t.title, t.lang, t.orig_lang, t.type, t.date, t.size, t.zsize,
			GROUP_CONCAT(DISTINCT tr.name) translator $this->extQS";
		$qFrom = " FROM /*p*/author_of aof
			LEFT JOIN /*p*/text t ON aof.text = t.id
			LEFT JOIN /*p*/person a ON aof.author = a.id
			LEFT JOIN /*p*/translator_of tof ON t.id = tof.text
			LEFT JOIN /*p*/person tr ON tof.translator = tr.id
			$this->extQF";
		$qGroup = ' GROUP BY t.id';
		if ($this->user->id > 0) {
			$qSelect .= ', r.user reader';
			$qFrom .= " LEFT JOIN /*p*/reader_of r ON t.id = r.text AND r.user = {$this->user->id}";
		}
		$qW = array();
		if ($this->date != -1) {
			$qW[] = $this->date === '0' ? "t.date != '0000-00-00'"
				: "t.date BETWEEN '$this->date-1' AND '".$this->monthEndDate()."'";
		}
		if ( !empty($this->extQW) ) $qW[] = $this->extQW;
		$q = $qSelect.$qFrom.(empty($qW) ? '' :
		' WHERE '.implode(' AND ', $qW));
		$q .= $qGroup .' ORDER BY t.date DESC, t.id DESC';
		if ($limit > 0) $q .= " LIMIT $limit";
		return $q;
	}


	protected function getStartDate() {
		$key = array('`date`' => array('!=', '0000-00-00'));
		$res = $this->db->select('text', $key, 'MIN(date)');
		list($minDate) = $this->db->fetchRow($res);
		if ( is_null($minDate) ) {
			// no matching entries: the DBMS returns NULL
			return date('Y-n-d');
		}
		return $minDate;
	}


	protected function nextMonthDate() {
		list($y, $m) = explode('-', $this->date);
		if ($m == 12)  { $m = 1; $y++; } else { $m++; }
		return "$y-$m";
	}


	// by David Bindel
	protected function monthEndDate() {
		list($y, $m) = explode('-', $this->date);
		$date = $this->date.'-';
		$date .= $m == 2
			? ($y % 4 ? 28 : ($y % 100 ? 29 : ($y % 400 ? 28 : 29)))
			: (($m - 1) % 7 % 2 ? 30 : 31);
		return $date;
	}
}
?>
