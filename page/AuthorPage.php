<?php

class AuthorPage extends ViewPage {

	protected $FF_SORTBY = 'sortby';
	protected $titles = array(
		'simple' => 'Автори — ',
		'extended' => 'Автори и заглавия — ',
	);
	protected $altTypes = array('p' => 'псевдоним', 'r' => 'истинско име',
		'a' => 'алтернативно изписване');


	public function __construct() {
		parent::__construct();
		$this->action = 'author';
		$this->mainDbTable = 'person';
		$this->sortby = $this->request->value($this->FF_SORTBY, 'first');
		$this->dbsortby = $this->sortby == 'last' ? 'last_name' : 'name';
		$this->uCanEditObj = $this->user->canExecute('editPerson');

		global $countries;
		$countries = array_merge(array('-'=>'(Без посочена)'), $countries);
	}


	protected function makeSimpleList() {
		$this->curch = $this->ext = '';
		$items = $this->db->iterateOverResult($this->makeSimpleListQuery(),
			'makeSimpleListItem', $this);
		if ( empty($items) ) {
			if ( $this->startwith{0} != '%' ) {
				$this->addMessage("Не са намерени имена, започващи с „{$this->startwith}“. Показани са такива, съдържащи „{$this->startwith}“.");
				$this->expandSearchString();
				return $this->makeSimpleList();
			}
			return false;
		}
		if ( substr_count($items, '<li>') == 1 && empty($this->ext) ) {
			$this->mode1 = 'extended';
			return $this->makeExtendedList(); // only one object
		}
		$o = '<ul style="display:none"><li></li>'. $items .'</ul>';
		return $o;
	}


	protected function makeSimpleListQuery() {
		$q1 = "SELECT id, name, orig_name, last_name, 'tp', country, name aname
			FROM /*p*/$this->mainDbTable";
		$q2 = "SELECT al.id, al.name, al.orig_name, al.last_name, al.type, a.country, a.name
			FROM /*p*/person_alt al
			LEFT JOIN /*p*/$this->mainDbTable a ON al.person = a.id";
		$where1 = $where2 = array('role & 1'); // author’s bit
		if ( !empty($this->country) ) {
			$dbcountry = $this->db->escape($this->country);
			$where1[] = "country='$dbcountry'";
			$where2[] = "a.country='$dbcountry'";
		}
		if ( !empty($this->startwith) ) {
			$dbstartwith = strtr($this->startwith, '.', '%');
			$dbstartwith = $this->db->escape($dbstartwith);
			$where1[] = "$this->dbsortby LIKE '$dbstartwith%'";
				#OR orig_name LIKE '$dbstartwith%'";
			$where2[] = "al.$this->dbsortby LIKE '$dbstartwith%'";
		}
		if ( !empty($where1) ) {
			$q1 .= ' WHERE '. implode(' AND ', $where1);
			$q2 .= ' WHERE '. implode(' AND ', $where2);
		}
		return "$q1 UNION $q2 ORDER BY $this->dbsortby, name";
	}


	public function makeSimpleListItem($dbrow) {
		extract($dbrow);
		$o = '';
		$lcurch = $this->firstChar( $dbrow[$this->dbsortby] );
		if ($this->curch != $lcurch) {
			$this->curch = $lcurch;
			$o .= "</ul>\n<h2>$this->curch</h2>\n<ul>";
		}
		$orig_name = !empty($orig_name) && $orig_name != $name
			? $this->formatPersonName($orig_name, $this->sortby) .' ' : '';
		$query = $this->showDlForm ? "/$this->FF_DLMODE=$this->dlMode" : '';
		$query .= $this->order == 'time' ? "/$this->FF_ORDER=$this->order" : '';
		if ($tp != 'tp') {
			$author = $this->formatPersonName($name, $this->sortby);
			$this->ext = ' — '. $this->altTypes[$tp] . ', вижте '.
				$this->makeAuthorLink($aname, $this->sortby, '', '', $query);
			$editLink = $this->uCanEditObj ? $this->makeEditAltAuthorLink($id) : '';
		} else {
			$author = $this->makeAuthorLink($name, $this->sortby, '', '', $query);
			$editLink = $this->uCanEditObj ? $this->makeEditAuthorLink($id) : '';
			$this->ext = '';
		}
		$img = $this->makeCountryImage($country);
		$o .= "\n<li>$author <span class='extra'>($orig_name$img$editLink)</span>$this->ext</li>";
		return $o;
	}


	protected function makeExtendedList() {
		$this->authorsData = array();
		$reader = NULL;
		$this->db->iterateOverResult($this->makeExtendedListQuery(),
			'makeExtendedListItem', $this);
		if ( empty($this->authorsData) ) {
			if ( $this->startwith{0} != '%' ) {
				$this->expandSearchString();
				return $this->makeExtendedList(true);
			}
			return false;
		}
		$o = '';
		$toc = '<div id="toc"><h2>Съдържание</h2><ul>';
		$userCanEdit = $this->user->canExecute('edit');
		foreach ($this->authorsData as $authorId => $data) {
			extract($data);
			$authorEnc = $this->urlencode($author);
			$anchor = strtr($authorEnc, '%+', '__');
			$showName = $this->formatPersonName($author, $this->sortby);
			$toc .= "\n\t<li><a href=\"#$anchor\">$showName</a></li>";
			$editLink = $this->uCanEditObj
				? $this->makeEditAuthorLink($authorId) : '';
			$origAuthorName = !empty($origAuthorName) && $author != $origAuthorName
				? '('.$this->formatPersonName($origAuthorName, $this->sortby).')'
				: '';
			$infoLink = empty($info) ? $this->makeInfoLink($author)
				: $this->makeMwLink($author, $info);
			$translatorLink = $is_t
				? "<a href='$this->root/translator/$authorEnc' title='Преглед на преводните текстове на $author'>Преводни заглавия</a>, "
				: '';
			$img = $this->makeCountryImage($country);
			if ( !empty($real_name) && $author != $real_name ) {
				$real_name = "Пълно (истинско) име: $real_name";
				if ( !empty($oreal_name) ) {
					$real_name .= " ($oreal_name)";
				}
				$real_name .= ', ';
			} else {
				$real_name = '';
			}
			$o .= <<<EOS

<h2 id="$anchor">$showName $origAuthorName
	$img $editLink
</h2>
<p class="info">$real_name $translatorLink $infoLink</p>

EOS;
			$series = $this->authors_titles[$authorId];
			ksort($series);
			foreach ($series as $serName => $titles) {
				$isTrueSeries = $serName{0} == ' '; // false by novels, etc.
				$orig = $ser[$serName];
				$serName = trim($serName);
				$orig = !empty($orig) && $orig != $serName ? "($orig)" : '';
				$serLink = $isTrueSeries ? $this->makeSeriesLink($serName) : $serName;
				$o .= <<<EOS
<fieldset class="titles">
	<legend>$serLink $orig</legend>
	<ul>
EOS;
				ksort($titles);
				$tids = array();
				foreach ($titles as $textId) {
					extract( $this->textsData[$textId] );
					$tids[] = $textId;
					if ( $sernr > 0 ) { $title = "$sernr. $title"; }
					$textLink = $this->makeTextLink(compact('textId', 'type', 'size', 'zsize', 'title', 'date', 'reader'));
					$extras = array();
					if ( !empty($orig_title) && $orig_lang != $lang ) {
						$extras[] = "<em>$orig_title</em>";
					}
					if ($this->order == 'time') {
						$titleView = '<span class="extra"><tt>'.
							$this->makeYearView($year, $ayear, $year2).
							'</tt> — </span>'.$textLink;
					} else {
						$titleView = $textLink;
						$extras[] = $this->makeYearView($year, $ayear, $year2);
					}
					$extras = empty($extras) ? '' : '('. implode(', ', $extras) .')';
					$dlCheckbox = $this->makeDlCheckbox($textId);
					$dlLink = $this->makeDlLink($textId, $zsize);
					$editLink = $userCanEdit ? $this->makeEditTextLink($textId) : '';
					$o .= <<<EOS

<li class="$type" title="{$GLOBALS['types'][$type]}">
	$dlCheckbox
	$titleView
	<span class="extra">
	$extras
	— $dlLink $editLink
	</span>
</li>
EOS;
				}
				$o .= "\n</ul>";
				if (!$this->showDlForm && count($tids) > 1) {
					$o .= $this->makeDlSeriesForm($tids, "$author - $serName",
						$isTrueSeries ? '' : "всички от „{$serName}“");
				}
				$o .= "\n</fieldset>\n";
			}
			$o .= $this->makeDlSubmit();
		}
		$toc .= "</ul>\n</div>\n<p style='clear:both'></p>\n";
		if (count($this->authorsData) < 2) { $toc = ''; }
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
		$qSelect = "SELECT a.name author, a.orig_name origAuthorName,
		a.id authorId, a.real_name, a.oreal_name,
		a.country, (a.role & 2) is_t, a.info, at.year ayear,
		t.id textId, t.title, t.lang, t.orig_title,
		t.orig_lang, t.year, t.year2, t.type, t.sernr, t.size, t.zsize,
		UNIX_TIMESTAMP(t.entrydate) date,
		s.name series, s.orig_name orig_series";
		$qFrom = " FROM /*p*/author_of at
		LEFT JOIN /*p*/text t ON at.text = t.id
		LEFT JOIN /*p*/$this->mainDbTable a ON at.author = a.id
		LEFT JOIN /*p*/series s ON t.series = s.id";
		if ($this->user->id > 0) {
			$qSelect .= ', r.user reader';
			$qFrom .= "
			LEFT JOIN /*p*/reader_of r ON t.id = r.text AND r.user = {$this->user->id}";
		}
		$q = $qSelect . $qFrom;

		$qWheres = array();
		if ( !empty($this->country) ) {
			$dbcountry = $this->db->escape($this->country);
			$qWheres[] = "country='$dbcountry'";
		}
		if ( !empty($this->startwith) ) {
			$dbstartwith = strtr($this->startwith, '.', '%');
			$dbstartwith = $this->db->escape($dbstartwith);
			$qWheres[] = "a.$this->dbsortby LIKE '$dbstartwith%'";
				#OR a.orig_name LIKE '$dbstartwith%'";
		}
		if ( !empty($qWheres) ) {
			$q .= ' WHERE '. implode(' AND ', $qWheres);
		}

		$q .= " ORDER BY a.$this->dbsortby, a.name";
		return $q;
	}


	public function makeExtendedListItem($dbrow) {
		extract($dbrow);
		if ( empty($textId) ) {
			return; // invalid author-text relation
		}
		if ( !isset($this->authorsData[$authorId]) ) {
			$this->authorsData[$authorId] = compact('author', 'origAuthorName',
				'real_name', 'oreal_name', 'country', 'is_t', 'info');
		}
		$this->textsData[$textId] = $dbrow;
		$series = empty($series) ? $GLOBALS['typesPl'][$type] : ' '.$series;
		$this->authorsData[$authorId]['ser'][$series] = $orig_series;
		$key = '';
		if ($this->order == 'time') {
			$key .= empty($ayear) ? $year : $ayear;
		}
		$key .= str_pad($sernr, 2, '0', STR_PAD_LEFT).$title . $textId;
		$this->authors_titles[$authorId][$series][$key] = $textId;
		return '';
	}


	protected function makeNavElements() {
		$extra = array($this->FF_SORTBY => '!first',
			$this->FF_ORDER => '', $this->FF_COUNTRY => '',
			$this->FF_DLMODE => 'one');
		$tocFirst = $this->makeNavButtons($extra, $this->sortby == 'first');
		$extra['sortby'] = '!last';
		$tocLast = $this->makeNavButtons($extra, $this->sortby == 'last');
		$modeInput = $this->makeModeInput();
		$orderInput = $this->makeOrderInput();
		$countryInput = $this->makeCountryInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array($this->FF_MODE, $this->FF_ORDER, $this->FF_DLMODE, $this->FF_COUNTRY));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p>Преглед на авторите по:</p>
<ul class="buttonlinks" style="line-height:1.8em">
	<li title="Това са препратки към списъци на авторите, подредени по първо име"><em>Първо име</em> — $tocFirst</li>
	<li title="Това са препратки към списъци на авторите, подредени по фамилия"><em>Фамилия</em> — $tocLast</li>
</ul>
<form action="$this->root" style="text-align:center">
<div>
	$inputFields
$modeInput &nbsp;
$orderInput &nbsp;
$countryInput &nbsp;
$dlModeInput
	<noscript><div style="display:inline">$submit</div></noscript>
</div>
</form>
EOS;
	}


	protected function makeExplanations() {
		$extra = $this->mode1 == 'extended' ? ', заедно със заглавията,' : '';
		$modeExpl = $this->makeModeExplanation();
		$countryExpl = $this->makeCountryExplanation();
		$dlModeExpl = $this->makeDlModeExplanation();
		return <<<EOS
<p>Горните връзки водят към списъци на авторите$extra
чиито имена (първо име или фамилия) започват със съответната буква.
Чрез препратките „Всички“ можете да разгледате всички автори наведнъж.</p>
$modeExpl
$countryExpl
$dlModeExpl
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени автори.', true);
	}
}
?>
