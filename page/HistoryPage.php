<?php

class HistoryPage extends Page {

	public $extQS = '', $extQF = array(), $extQW = array();
	protected
		$allowViewAll = false,
		$validGetbys = array('entrydate', 'lastmod'),
		$defGetby = 'entrydate',
		$headerExts = array(
			'entrydate' => 'нови текстове',
			'lastmod' => 'редактирани текстове'),
		$viewTypes = array('plain', 'table'),
		$defViewType = 'plain'
	;


	public function __construct() {
		parent::__construct();
		$this->action = 'history';
		$this->title = 'История';
		$this->defDate = date('Y-n');
		$this->date = $this->request->value('date', $this->defDate);
		if ( preg_match('/[^\d-]/', $this->date) ) {
			$this->date = $this->defDate;
		}
		$this->getby = $this->request->value('getby', $this->defGetby);
		if ( !in_array($this->getby, $this->validGetbys) ) {
			$this->getby = $this->defGetby;
		}
		$this->orderby = $this->request->value('orderby', 'date');
		$this->viewType = normVal(
			$this->request->value('vt', $this->defViewType),
			$this->viewTypes,
			$this->defViewType);
		$this->media = $this->request->value('media', 'screen');
	}


	public function title() {
		return $this->title = 'История — '. $this->headerExts[$this->getby];
	}


	protected function buildContent() {
		$inputContent = $this->makeMonthInput() .' &nbsp; '.
			$this->makeGetbyInput() .' &nbsp; '. $this->makeOrderInput();
		$submit = $this->out->submitButton('Обновяване', 'Обновяване на страницата', 0, false);
		$limits = array(10, 25, 50);
		$feednew = $this->makeFeedLinks($limits, 'new');
		$feededit = $this->makeFeedLinks($limits, 'edit');
		$o = <<<EOS

<p>Тук можете да разгледате кои произведения са били добавени или редактирани през даден месец. Текстовете, въведени при създаването на <em>$this->sitename</em>, не се показват като добавени.</p>
<p>Можете да следите историята и чрез <acronym title="Really Simple Syndication">RSS</acronym>:</p>
<ul>
	<li>добавени — последните $feednew</li>
	<li>редактирани — последните $feededit</li>
</ul>
<p class="non-graphic">Към <a href="#before-lists">списъка на произведенията</a></p>
<form action="{FACTION}" method="get">
<div style="text-align:center; margin:1em auto;">
	{HIDDEN_ACTION}
$inputContent
	$submit
</div>
</form>
<p id="before-lists" class="non-graphic"><a name="before-lists"> </a></p>

EOS;
		$list = $this->orderby == 'date'
			? $this->makeListByDate() : $this->makeListByAuthor();
		if ( empty($list) ) {
			$keyword = $this->getby == 'entrydate' ? 'добавени' : 'редактирани';
			$list = "<p>През избрания месец не са $keyword произведения.</p>";
		}
		$this->addRssLink('добавени текстове', 'new');
		$this->addRssLink('редактирани текстове', 'edit');
		return $o . $list;
	}


	public function makeListByDate($limit = 0, $showHeader = true) {
		$this->texts = array();
		$query = $this->makeSqlQuery($limit);
		$this->db->iterateOverResult($query, 'addTextForListByDate', $this);
		$o = $edit_comment = '';
		$mark = ' <span class="newmark">Н</span>';
		if ($this->isEditMode()) {
			$o .= "\n<p><em>Легенда:</em> $mark — новодобавен текст (първа редакция)</p>";
		}
		foreach ($this->texts as $datekey => $textsForDate) {
			if ($showHeader) {
				list($year, $month) = explode('-', $datekey);
				$monthName = monthName($month);
				$o .= "\n<h2>$monthName $year</h2>\n<ul>";
			}
			foreach ($textsForDate as $textId => $textData) {
				extract($textData);
				$readClass = empty($reader) ? 'unread' : 'read';
				$sauthor = $collection == 'true' ? ''
					: $this->makeAuthorLink($author, 'first');
				if ( $lang != $orig_lang ) {
					$stranslator = (!empty($sauthor) ? ', ' : '').'превод: ';
					$stranslator .= empty($translator)
						? $this->out->internLink('неизвестен', array(self::FF_ACTION=>'suggestData', 'sa'=>'translator', 'textId'=>$textId), 3)
						: $this->makeTranslatorLink($translator, 'first');
				} else { $stranslator = ''; }
				$vdate = $textData[$this->getby];
				if ($this->isEditMode()) {
					$vdate .= $entrydate >= substr($lastmod, 0, 10) ? $mark : ' &nbsp;';
				}
				$seriesLink = empty($series) ? ''
					: '<span class="extra">'.$this->makeSeriesLink($series) .':</span> ';
				$o .= $this->media == 'screen'
					? "\n\t<li class='$type'><tt title='$edit_comment'>$vdate</tt> $seriesLink".
						$this->makeSimpleTextLink($title, $textId, 1, '', array('class'=>$readClass)).
					' — <span class="extra">'. $this->makeDlLink($textId, $zsize) .'</span>'
					: "\n\t<li>$seriesLink$title [$this->purl/text/$textId]";
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


	protected function makeFeedLinks($limits = array(), $type = 'new', $ftype = 'rss') {
		$l = '';
		$ext = array('new' => 'добавени', 'edit' => 'редактирани');
		foreach ($limits as $limit) {
			$title = "RSS зоб за новинарски четци с последните $limit $ext[$type] произведения";
			$l .= ', '.$this->out->internLink($limit, array(self::FF_ACTION=>'feed', 'obj'=>$type, 'limit'=>$limit), 3);
		}
		return ltrim($l, ', ');
	}


	public function addTextForListByDate($dbrow) {
		extract($dbrow);
		unset($dbrow['textId']);
		$this->texts[ substr($dbrow[$this->getby], 0, 7) ][$textId] = $dbrow;
	}


	public function makeListByAuthor($limit = 0) {
		$this->texts = array();
		$query = $this->makeSqlQuery($limit);
		$this->db->iterateOverResult($query, 'addTextForListByAuthor', $this);
		$o = '';
		$translator = '';
		foreach ($this->texts as $date => $dtexts) {
			ksort($dtexts);
			list($year, $month) = explode('-', $date);
			$monthName = monthName($month);
			$o .= "\n<h2>$monthName $year</h2>\n<ul>";
			foreach ($dtexts as $author => $atexts) {
				$author = $this->makeAuthorLink($author, 'first');
				$o .= "\n<li>$author\n<ul>";
				ksort($atexts);
				foreach ($atexts as $atext) {
					extract($atext);
					if ( $lang != $orig_lang ) {
						$stranslator = ', превод: ';
						$stranslator .= empty($translator)
							? $this->out->internLink('неизвестен', array(self::FF_ACTION=>'suggestData', 'sa'=>'translator', 'textId'=>$textId), 3)
							: $this->makeTranslatorLink($translator, 'first');
					} else { $stranslator = ''; }
					$readClass = empty($reader) ? 'unread' : 'read';
					$dl = $this->makeDlLink($textId, $zsize);
					$seriesLink = empty($series) ? ''
						: '<span class="extra">'.$this->makeSeriesLink($series) .':</span> ';
					$o .= $this->media == 'screen'
						? "\n\t<li class='$type'>$seriesLink".
						$this->makeSimpleTextLink($title, $textId, 1, '', array('class'=>$readClass)).
						' — <span class="extra">'. $this->makeDlLink($textId, $zsize) .'</span>'.$stranslator
						: "\n\t<li>$seriesLink$title [$this->purl/text/$textId]</li>";
				}
				$o .= '</ul></li>';
			}
			$o .= '</ul>';
		}
		return $o;
	}


	public function addTextForListByAuthor($dbrow) {
		extract($dbrow);
		$date = substr($dbrow[$this->getby], 0, 7);
		if ($collection == 'true') { $author = ''; }
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
		if ($this->allowViewAll) {
			$opts[0] = '(Всички месеци)';
		}
		$date = $this->out->selectBox('date', '', $opts, $this->date);
		return '<label for="date">През:</label>&nbsp;'. $date;
	}


	protected function makeGetbyInput() {
		$opts = array('entrydate'=>'Дата на добавяне', 'lastmod'=>'Дата на посл. редакция');
		$box = $this->out->selectBox('getby', '', $opts, $this->getby);
		return '<label for="getby">Търсене по:</label>&nbsp;'. $box;
	}


	protected function makeOrderInput() {
		$opts = array('date'=>'Дата', 'author'=>'Автор');
		$box = $this->out->selectBox('orderby', '', $opts, $this->orderby);
		return '<label for="orderby">Подредба по:</label>&nbsp;'. $box;
	}


	public function makeSqlQuery($limit = 0, $offset = 0, $order = null) {
		$col = $this->getby == 'entrydate' ? 't.entrydate' : 'h.date';
		$qa = array(
			'SELECT' => 'GROUP_CONCAT(DISTINCT a.name ORDER BY aof.pos) author,
				t.id textId, t.title, t.lang, t.orig_lang, t.type, t.collection,
				t.entrydate, t.size, t.zsize,
				GROUP_CONCAT(DISTINCT tr.name ORDER BY tof.pos) translator,
				s.name series, s.orig_name orig_series' . $this->extQS,
			'FROM' => DBT_TEXT .' t',
			'LEFT JOIN' => array(
				DBT_AUTHOR_OF .' aof' => 't.id = aof.text',
				DBT_PERSON .' a' => 'aof.author = a.id',
				DBT_TRANSLATOR_OF .' tof' => 't.id = tof.text',
				DBT_PERSON .' tr' => 'tof.translator = tr.id',
				DBT_SERIES .' s' => 't.series = s.id',
			) + $this->extQF,
			'WHERE' => array(),
			'GROUP BY' => 't.id',
			'ORDER BY' => "$col DESC, t.id DESC",
			'LIMIT' => array($offset, $limit)
		);
		if ($this->getby == 'lastmod') {
			$qa['SELECT'] .= ', h.user editor, h.date lastmod, h.comment edit_comment';
			$qa['LEFT JOIN'][DBT_EDIT_HISTORY .' h'] = 't.lastedit = h.id';
		}
		if ($this->user->id > 0) {
			$qa['SELECT'] .= ', r.user reader';
			$qa['LEFT JOIN'][DBT_READER_OF .' r'] =
				't.id = r.text AND r.user = '. $this->user->id;
		}
		if ($this->date === '0' && $this->allowViewAll) {
			$qa['WHERE'][$col] = array('!=', '0000-00-00');
		} else if ($this->date !== -1) {
			if ( strpos($this->date, '-') === false ) {
				$this->date = $this->defDate;
			}
			$end = $this->monthEndDate();
			$qa['WHERE'][] = "$col BETWEEN '$this->date-1' AND '$end'";
		}
		if ( !empty($this->extQW) ) $qa['WHERE'] += $this->extQW;
		return $this->db->extselectQ($qa);
	}


	protected function getStartDate() {
		$key = array('`entrydate`' => array('!=', '0000-00-00'));
		$res = $this->db->select(DBT_TEXT, $key, 'MIN(entrydate)');
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
		return $date . ' 23:59:59';
	}


	protected function isEditMode() {
		return $this->getby == 'lastmod';
	}

}
