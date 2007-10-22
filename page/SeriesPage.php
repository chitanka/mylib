<?php

class SeriesPage extends ViewPage {

	protected
		$titles = array(
			'simple' => 'Поредици — ',
			'extended' => 'Поредици и заглавия — ');


	public function __construct() {
		parent::__construct();
		$this->action = 'series';
		$this->uCanEditObj = $this->user->canExecute('editSeries');
	}


	protected function makeSimpleList() {
		$this->curch = '';
		$items = $this->db->iterateOverResult($this->makeSimpleListQuery(),
			'makeSimpleListItem', $this);
		if ( empty($items) ) {
			return false;
		}
		if ( substr_count($items, '<li>') == 1 ) {
			$this->mode1 = 'extended';
			return $this->makeExtendedList(); // only one object
		}
		$o = '<ul style="display:none"><li></li>'. $items .'</ul>';
		return $o;
	}


	protected function makeSimpleListQuery() {
		$qa = array(
			'SELECT' => 's.id, s.name, s.orig_name, s.type,
				GROUP_CONCAT(a.name) author',
			'FROM' => DBT_SER_AUTHOR_OF .' isSA',
			'LEFT JOIN' => array(
				DBT_SERIES .' s' => 'isSA.series = s.id',
				DBT_PERSON .' a' => 'isSA.author = a.id',
			),
			'GROUP BY' => 's.id',
			'ORDER BY' => 's.name',
		);
		if ( !empty($this->startwith) ) {
			$qa['WHERE']['s.name'] = array('LIKE', $this->startwith .'%');
		}
		return $this->db->extselectQ($qa);
	}


	public function makeSimpleListItem($dbrow) {
		extract($dbrow);
		$o = '';
		$lcurch = $this->firstChar($name);
		if ($this->curch != $lcurch) {
			$this->curch = $lcurch;
			$o .= "</ul>\n<h2>$this->curch</h2>\n<ul>";
		}
		$editLink = $this->uCanEditObj ? $this->makeEditSeriesLink($id) : '';
		$author = $this->makeAuthorLink($author);
		$suff = seriesSuffix($type);
		if ( !empty($author) ) { $author = '— '.$author; }
		if ($orig_name == $name) { $orig_name = ''; }
		if ( !empty($orig_name) ) { $orig_name = " ($orig_name)"; }
		$query = array();
		if ($this->showDlForm) $query[self::FF_DLMODE] = $this->dlMode;
		if ($this->order != $this->defOrder) {
			$query[self::FF_ORDER] = $this->order;
		}
		$link = $this->makeSeriesLink($name, '', $suff, $query);
		$o .= <<<EOS

<li>
	$link
	<span class="extra">
	<em>$orig_name</em>
	$editLink
	</span>
	$author
</li>
EOS;
		return $o;
	}


	protected function makeExtendedList() {
		$this->curSeries = 0;
		$this->curAuthor = '';
		$this->displayAuthor = false;
		$this->data = $this->serTitles = array();
		$this->db->iterateOverResult($this->makeExtendedListQuery(),
			'makeExtendedListItem', $this);
		if ( empty($this->serTitles) ) {
			return false;
		}
		$this->data[$this->curSeries] = array(
			'titles' => $this->serTitles,
			'series' => $this->serTitles[0]['series'],
			'type' => $this->serTitles[0]['seriesType'],
			'author' => ($this->displayAuthor ? '' : $this->curAuthor),
		);

		$o = '';
		$toc = '<div id="toc"><h2>Съдържание</h2><ul>';
		$userCanEdit = $this->user->canExecute('edit');
		foreach ($this->data as $seriesId => $serData) {
			extract($serData);
			$fullSerName = ltrim("$author - $series", ' -');
			$seriesAnchor = md5($series);
			$suff = seriesSuffix($type);
			$toc .= "\n\t<li><a href='#$seriesAnchor'>$series$suff</a></li>";
			$displayAuthor = true;
			if ( !empty($author) ) {
				$author = ' — '. $this->makeAuthorLink($author);
				$displayAuthor = false;
			}
			$editLink = $this->uCanEditObj ? $this->makeEditSeriesLink($seriesId) : '';
			$o .= <<<EOS

<h2 id="$seriesAnchor">$series$suff $editLink $author</h2>

<ul>
EOS;
			$tids = array();
			foreach ($titles as $tData) {
				extract($tData);
				$tids[] = $textId;
				$dlLink = $this->makeDlLink($textId, $zsize);
				$extras = array();
				if ( !empty($orig_title) && $orig_lang != $lang ) {
					$extras[] = "<em>$orig_title</em>";
				}
				if ($sernr > 0) { $title = $sernr .'. '. $title; }
				$textLink = $this->makeTextLink(compact('textId', 'type', 'size', 'zsize', 'title', 'date', 'datestamp', 'reader'));
				if ($this->order == 'time') {
					$titleView = '<span class="extra"><tt>'.$this->makeYearView($year).
						'</tt> — </span>'.$textLink;
				} else {
					$titleView = $textLink;
					if ( !empty($year) ) { $extras[] = $year; }
				}
				if ($displayAuthor) {
					$author = $this->makeAuthorLink($author);
					if ( !empty($author) ) { $extras[] = $author; }
				}
				$extras = empty($extras) ? '' : '('. implode(', ', $extras) .')';
				$dlCheckbox = $this->makeDlCheckbox($textId);
				$editLink = $userCanEdit ? $this->makeEditTextLink($textId) : '';
				$title = workType($type);
				$o .= <<<EOS

<li class="$type" title="$title">
	$dlCheckbox
	$titleView
	<span class="extra">
	$extras
	— $dlLink $editLink
	</span>
</li>
EOS;
			}
			$o .= "</ul>\n";
			if (!$this->showDlForm && count($tids) > 1) {
				$o .= $this->makeDlSeriesForm($tids, $fullSerName);
			}
			$o .= $this->makeDlSubmit();
		}
		$toc .= "</ul></div><p style='clear:both'></p>\n";
		if (count($this->data) < 2) { $toc = ''; }
		if ($this->showDlForm) {
			$action = $this->out->hiddenField(self::FF_ACTION, 'download');
			$o = <<<EOS
<form action="$this->root" method="post">
	$action
$o
</form>
EOS;
		}
		$o .= $this->makeColorLegend();
		unset($this->data);
		unset($this->serTitles);
		return $toc . $o;
	}


	protected function makeExtendedListQuery() {
		$chrono = $this->order == 'time' ? 't.year,' : '';
		$qa = array(
			'SELECT' => 's.id seriesId, s.name series, s.orig_name orig_series,
				s.type seriesType,
				t.id textId, t.title, t.lang, t.orig_title, t.orig_lang,
				t.year, t.type, t.sernr, t.size, t.zsize, t.entrydate date,
				UNIX_TIMESTAMP(t.entrydate) datestamp,
				GROUP_CONCAT(a.name ORDER BY aof.pos) author',
			'FROM' => DBT_SERIES .' s',
			'LEFT JOIN' => array(
				DBT_TEXT .' t' => 's.id = t.series',
				DBT_AUTHOR_OF .' aof' => 't.id = aof.text',
				DBT_PERSON .' a' => 'aof.author = a.id',
			),
			'WHERE' => array(),
			'GROUP BY' => 's.id, t.id',
			'ORDER BY' => "s.name, $chrono t.sernr, t.title",
// 			'LIMIT' => array($this->qstart, $this->qlimit)
		);
		if ($this->user->id > 0) {
			$qa['SELECT'] .= ', r.user reader';
			$qa['LEFT JOIN'][DBT_READER_OF .' r'] =
				't.id = r.text AND r.user = '. $this->user->id;
		}
		if ( !empty($this->startwith) ) {
			$qa['WHERE']['s.name'] = array('LIKE', $this->startwith .'%');
		}
		return $this->db->extselectQ($qa);
	}


	public function makeExtendedListItem($dbrow) {
		extract($dbrow);
		if ( empty($textId) ) {
			return;
		}
		if ($this->curSeries != $seriesId) {
			if ( !empty($this->curSeries) ) {
				$this->data[$this->curSeries] = array(
					'titles' => $this->serTitles,
					'series' => $this->serTitles[0]['series'],
					'type' => $this->serTitles[0]['seriesType'],
					'author' => ($this->displayAuthor ? '' : $this->curAuthor),
				);
				$this->curAuthor = '';
				$this->displayAuthor = false;
				$this->serTitles = array();
			}
			$this->curSeries = $seriesId;
		}
		if ($this->curAuthor != $author) {
			if ( !empty($this->curAuthor) ) { $this->displayAuthor = true; }
			$this->curAuthor = $author;
		}
		unset($dbrow['seriesId']);
		$this->serTitles[] = $dbrow;
		return '';
	}


	protected function makeNavElements() {
		$toc = $this->makeNavButtons(array(self::FF_ORDER => $this->defOrder,
			self::FF_DLMODE => $this->defDlMode));
		$modeInput = $this->makeModeInput();
		$orderInput = $this->makeOrderInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array(self::FF_MODE, self::FF_ORDER, self::FF_DLMODE));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p class="buttonlinks" style="margin-bottom:1em;"
	title="Това са препратки към списъци на поредиците, започващи със съответната буква">
$toc
</p>
<form action="$this->root" style="text-align:center"><div>
	$inputFields
$modeInput &nbsp;
$orderInput &nbsp;
$dlModeInput
	<noscript><div style="display:inline">$submit</div></noscript>
</div></form>
EOS;
	}


	protected function makeExplanations() {
		$extra = $this->mode1 == 'extended' ? ', заедно със заглавията,' : '';
		$modeExpl = $this->makeModeExplanation();
		$dlModeExpl = $this->makeDlModeExplanation();
		return <<<EOS
<p>Горните връзки водят към списъци на поредиците$extra започващи със съответната буква. Чрез препратката „Всички“ можете да разгледате всички поредици наведнъж.</p>
$modeExpl
$dlModeExpl
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени поредици.', true);
	}
}
