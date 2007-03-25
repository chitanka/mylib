<?php

class SeriesPage extends ViewPage {

	protected $titles = array(
		'simple' => 'Поредици — ',
		'extended' => 'Поредици и заглавия — ',
	);


	public function __construct() {
		parent::__construct();
		$this->action = 'series';
		$this->uCanEditObj = $this->user->canExecute('editSeries');
	}


	protected function makeSimpleList() {
		$this->curch = '';
		$items = $this->db->iterateOverResult($this->makeSimpleListQuery(),
			'makeSimpleListItem', $this);
		if ( empty($items) ) { return false; }
		if ( substr_count($items, '<li>') == 1 ) {
			$this->mode1 = 'extended';
			return $this->makeExtendedList(); // only one object
		}
		$o = '<ul style="display:none"><li></li>'. $items .'</ul>';
		return $o;
	}


	protected function makeSimpleListQuery() {
		$query = "SELECT s.id, s.name, s.orig_name, GROUP_CONCAT(a.name) author
			FROM /*p*/ser_author_of isSA
			LEFT JOIN /*p*/series s ON isSA.series = s.id
			LEFT JOIN /*p*/person a ON isSA.author = a.id";
		$qWheres = array();
		if ( !empty($this->startwith) ) {
			$this->startwith = $this->db->escape($this->startwith);
			$qWheres[] = "s.name LIKE '$this->startwith%'";
				#OR s.orig_name LIKE '$this->startwith%'";
		}
		if ( !empty($qWheres) ) {
			$query .= ' WHERE '. implode(' AND ', $qWheres);
		}
		$query .= " GROUP BY s.id ORDER BY s.name";
		return $query;
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
		$nameEnc = $this->urlencode($name);
		$author = $this->makeAuthorLink($author);
		if ( !empty($author) ) { $author = '— '.$author; }
		if ($orig_name == $name) { $orig_name = ''; }
		if ( !empty($orig_name) ) { $orig_name = " ($orig_name)"; }
		$query = $this->showDlForm ? "/$this->FF_DLMODE=$this->dlMode" : '';
		$query .= $this->order == 'time' ? "/$this->FF_ORDER=$this->order" : '';
		$o .= <<<EOS

<li>
	<a href="$this->root/series/$nameEnc$query"><em>$name</em></a>
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
		global $types, $typesPl;

		$this->curSeries = 0;
		$this->curAuthor = '';
		$this->displayAuthor = false;
		$this->data = $this->serTitles = array();
		$this->db->iterateOverResult($this->makeExtendedListQuery(),
			'makeExtendedListItem', $this);
		if ( empty($this->serTitles) ) { return false; }
		$this->data[$this->curSeries] = array(
			'titles' => $this->serTitles, 'series' => $this->serTitles[0]['series'],
			'author' => ($this->displayAuthor ? '' : $this->curAuthor),
		);

		$o = '';
		$toc = '<div id="toc"><h2>Съдържание</h2><ul>';
		$userCanEdit = $this->user->canExecute('edit');
		foreach ($this->data as $seriesId => $serData) {
			extract($serData);
			$fullSerName = ltrim("$author - $series", ' -');
			$seriesEnc = $this->urlencode($series);
			$seriesAnchor = strtr($seriesEnc, '%+', '__');
			$toc .= "\n\t<li><a href=\"#$seriesAnchor\">$series</a></li>";
			$displayAuthor = true;
			if ( !empty($author) ) {
				$author = ' — '. $this->makeAuthorLink($author);
				$displayAuthor = false;
			}
			$editLink = $this->uCanEditObj ? $this->makeEditSeriesLink($seriesId) : '';
			$o .= <<<EOS

<h2 id="$seriesAnchor">$series $editLink $author</h2>

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
				$textLink = $this->makeTextLink(compact('textId', 'type', 'size', 'zsize', 'title', 'date', 'reader'));
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
				$o .= <<<EOS

<li class="$type" title="$types[$type]">
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
			$action = $this->out->hiddenField('action', 'download');
			$o = <<<EOS
<form action="$this->root" method="post">
	$action
$o
</form>
EOS;
		}
		$o .= $this->makeColorLegend();
		return $toc . $o;
	}


	protected function makeExtendedListQuery() {
		$qSelect = "SELECT s.id seriesId, s.name series, s.orig_name orig_series,
			t.id textId, t.title, t.lang, t.orig_title, t.orig_lang,
			t.year, t.type, t.sernr, t.size, t.zsize, UNIX_TIMESTAMP(t.date) date,
			GROUP_CONCAT(a.name) author";
		$qFrom = " FROM /*p*/series s
			LEFT JOIN /*p*/text t ON s.id = t.series
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id";
		if ($this->user->id > 0) {
			$qSelect .= ', r.user reader';
			$qFrom .= "
			LEFT JOIN /*p*/reader_of r ON t.id = r.text AND r.user={$this->user->id}";
		}
		$query = $qSelect . $qFrom;
		$qWhere = array();
		if ( !empty($this->startwith) ) {
			$this->startwith = $this->db->escape($this->startwith);
			$qWhere[] = "s.name LIKE '$this->startwith%'";
				#OR s.orig_name LIKE '$this->startwith%'";
		}
		if ( !empty($qWhere) ) {
			$query .= ' WHERE '. implode(' AND ', $qWhere);
		}
		$torderby = $this->order == 'time' ? 't.year,' : '';
		$query .= " GROUP BY s.id, t.id ORDER BY s.name, $torderby t.sernr, t.title";
		return $query;
	}


	public function makeExtendedListItem($dbrow) {
		extract($dbrow);
		if ( empty($textId) ) { return; }
		if ($this->curSeries != $seriesId) {
			if ( !empty($this->curSeries) ) {
				$this->data[$this->curSeries] = array(
					'titles' => $this->serTitles, 'series' => $this->serTitles[0]['series'],
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
		$toc = $this->makeNavButtons(array($this->FF_ORDER => '',
			$this->FF_DLMODE=>'one'));
		$modeInput = $this->makeModeInput();
		$orderInput = $this->makeOrderInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array($this->FF_MODE, $this->FF_ORDER, $this->FF_DLMODE));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p class="buttonlinks" style="margin-bottom:1em;"
	title="Това са препратки към списъци на поредиците, започващи със съответната буква">
$toc
</p>
<form action="$this->root" style="text-align:center">
<div>
	$inputFields
$modeInput &nbsp;
$orderInput &nbsp;
$dlModeInput
	<noscript><div style="display:inline">$submit</div></noscript>
</div>
</form>
EOS;
	}


	protected function makeExplanations() {
		$extra = $this->mode1 == 'extended' ? ', заедно със заглавията,' : '';
		$modeExpl = $this->makeModeExplanation();
		$dlModeExpl = $this->makeDlModeExplanation();
		return <<<EOS
<p>Горните връзки водят към списъци на поредиците$extra
започващи със съответната буква. Чрез препратката „Всички“
можете да разгледате всички поредици наведнъж.</p>
$modeExpl
$dlModeExpl
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени поредици.', true);
	}
}
?>
