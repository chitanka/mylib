<?php

class AuthorPage extends ViewPage {

	const
		DB_TABLE = DBT_PERSON, FF_SORTBY = 'sortby';
	protected
		$titles = array('simple' => 'Списък на автори — $1',
			'extended' => '$1 — Автори'),
		$altTypes = array('p' => 'псевдоним', 'r' => 'истинско име',
			'a' => 'алтернативно изписване');


	public function __construct() {
		parent::__construct();
		$this->action = 'author';
		$this->sortby = $this->request->value(self::FF_SORTBY, 'first');
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
			if ( !empty($this->startwith) && $this->startwith{0} != '%' ) {
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
		$qa1 = array(
			'SELECT' => 'id, name, orig_name, last_name, "tp", country, name aname',
			'FROM' => self::DB_TABLE,
			'WHERE' => array('role & 1') // author’s bit,
		);
		$qa2 = array(
			'SELECT' => 'al.id, al.name, al.orig_name, al.last_name, al.type,
				a.country, a.name',
			'FROM' => DBT_PERSON_ALT .' al',
			'LEFT JOIN' => array(self::DB_TABLE .' a' => 'al.person = a.id'),
			'WHERE' => array('role & 1') // author’s bit,
		);
		if ( !empty($this->country) ) {
			$qa1['WHERE']['country'] =
			$qa2['WHERE']['a.country'] = $this->country;
		}
		if ( !empty($this->startwith) ) {
			$qa1['WHERE'][$this->dbsortby] =
			$qa2['WHERE']['al.'.$this->dbsortby] =
				array('LIKE', strtr($this->startwith, '.', '%') .'%');
		}
		$q1 = $this->db->extselectQ($qa1);
		$q2 = $this->db->extselectQ($qa2);
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
		$query = array();
		if ($this->showDlForm) $query[self::FF_DLMODE] = $this->dlMode;
		if ($this->order == 'time') $query[self::FF_ORDER] = $this->order;
		if ($tp != 'tp') {
			$author = $this->formatPersonName($name, $this->sortby);
			$this->ext = ' — '. $this->altTypes[$tp] . ', вижте '.
				$this->makeAuthorLink($aname, $this->sortby, '', '', $query);
			$editLink = $this->uCanEditObj ? ' '.$this->makeEditAltAuthorLink($id) : '';
		} else {
			$author = $this->makeAuthorLink($name, $this->sortby, '', '', $query);
			$editLink = $this->uCanEditObj ? $this->makeEditAuthorLink($id) : '';
			$this->ext = '';
		}
		$img = $this->makeCountryImage($country);
		$o .= "\n<li>$author <span class='extra'>($img $orig_name$editLink)</span>$this->ext</li>";
		return $o;
	}


	protected function makeExtendedList() {
		$this->authorsData = array();
		$reader = NULL;
		$this->db->iterateOverResult($this->makeExtendedListQuery(),
			'makeExtendedListItem', $this);
		if ( empty($this->authorsData) ) {
			if ( !empty($this->startwith) && $this->startwith{0} != '%' ) {
				$this->expandSearchString();
				return $this->makeExtendedList(true);
			}
			return false;
		}
		$ucvt = ucfirst($this->viewType);
		$makeStartFunc = 'makeExtendedListStart'. $ucvt;
		$makeEndFunc = 'makeExtendedListEnd'. $ucvt;
		$makeItemFunc = 'makeExtendedListItem'. $ucvt;
		$o = '';
		$toc = '<div id="toc"><h2>Съдържание</h2><ul>';
		$userCanEdit = $this->user->canExecute('edit');
		foreach ($this->authorsData as $authorId => $data) {
			extract($data);
			$anchor = 'a-'. md5($author);
			$showName = $this->formatPersonName($author, $this->sortby);
			$toc .= "\n\t<li><a href='#$anchor'>$showName</a></li>";
			$editLink = $this->uCanEditObj
				? $this->makeEditAuthorLink($authorId) : '';
			$origAuthorName = !empty($origAuthorName) && $author != $origAuthorName
				? '('.$this->formatPersonName($origAuthorName, $this->sortby).')'
				: '';
			$img = $this->makeCountryImage($country);
			$ainfo = array();
			if ( !empty($real_name) && $author != $real_name ) {
				$real_name = 'Пълно (истинско) име: '. $real_name;
				if ( !empty($oreal_name) ) {
					$real_name .= " ($oreal_name)";
				}
				$ainfo[] = $real_name;
			}
			if ($is_t) {
				$params = array(self::FF_ACTION=>'translator', 'q'=>$author);
				$ainfo[] = $this->out->internLink('Преводни заглавия',
					$params, 2, 'Преглед на преводните текстове на '.$author);
			}
			$ainfo[] = empty($info) ? $this->makeInfoLink($author)
				: $this->makeMwLink($author, $info);
			$ainfo = implode(', ', $ainfo);
			$o .= <<<EOS

<h2 id="$anchor">$showName $origAuthorName
	$img $editLink
</h2>
<p class="info">$ainfo</p>

EOS;
			$series = $this->authors_titles[$authorId];
			ksort($series);
			foreach ($series as $serName => $titles) {
				$isTrueSeries = $serName{0} == ' '; // false by novels, etc.
				list($orig, $seriesType) = $ser[$serName];
				$serName = trim($serName);
				$orig = !empty($orig) && $orig != $serName ? "($orig)" : '';
				$serLink = $isTrueSeries
					? $this->makeSeriesLink($serName) . seriesSuffix($seriesType)
					: $serName;
				$o .= <<<EOS
<fieldset class="titles">
	<legend>$serLink $orig</legend>
EOS;
				$o .= $this->$makeStartFunc();
				ksort($titles);
				$tids = array();
				foreach ($titles as $textId) {
					extract( $this->textsData[$textId] );
					$tids[] = $textId;
					if ( $sernr > 0 ) { $title = "$sernr. $title"; }
					$textLink = $this->makeTextLink(compact('textId', 'type', 'size', 'zsize', 'title', 'date', 'datestamp', 'reader'));
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
				$o .= "\n" . $this->$makeEndFunc();
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
			$action = $this->out->hiddenField(self::FF_ACTION, 'download');
			$o = <<<EOS

<form action="$this->root" method="post"><div>
	$action
$o
</div></form>
EOS;
		}
		unset($this->authorsData);
		unset($this->authors_titles);
		unset($this->textsData);
		$o .= $this->makeColorLegend();
		return $toc . $o;
	}

	protected function makeExtendedListStartPlain() {
		return '<ul>';
	}

	protected function makeExtendedListEndPlain() {
		return '</ul>';
	}

	protected function makeExtendedListItemPlain($data) {
		extract($data);
		return <<<EOS
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

	protected function makeExtendedListStartTable() {
		return <<<EOS
	<table class="content sortable" style="width:100%">
		<tr>
			<th>Заглавие</th>
			<th title="Големина на файла за сваляне">Голем.</th>
			<th>Автор</th>
		</tr>
EOS;
	}

	protected function makeExtendedListEndTable() {
		return '</table>';
	}

	protected function makeExtendedListItemTable($data) {
		extract($data);
		fillOnEmpty($edit_comment, '');
		$this->rowclass = $this->out->nextRowClass($this->rowclass);
		$tl = $this->makeSimpleTextLink($title, $textId, 1, '', array('class'=>$readClass));
		$dl = $this->makeDlLink($textId, $zsize);
		$i = <<<EOS
	<tr class="$this->rowclass">
		<td class="date"><tt title="$edit_comment">$vdate</tt></td>
		<td>$seriesLink <span class="$type">$tl</span></td>
		<td class="extra">$dl</td>
		<td>$from</td>
	</tr>
EOS;
		return $i;
	}


	protected function makeExtendedListQuery() {
		$qa = array(
			'SELECT' => 'a.name author, a.orig_name origAuthorName,
				a.id authorId, a.real_name, a.oreal_name,
				a.country, (a.role & 2) is_t, a.info, aof.year ayear,
				t.id textId, t.title, t.lang, t.orig_title,
				t.orig_lang, t.year, t.year2, t.type, t.sernr, t.size, t.zsize,
				t.entrydate date, UNIX_TIMESTAMP(t.entrydate) datestamp,
				s.name series, s.orig_name orig_series, s.type seriesType',
			'FROM' => DBT_AUTHOR_OF .' aof',
			'LEFT JOIN' => array(
				DBT_TEXT .' t' => 'aof.text = t.id',
				self::DB_TABLE .' a' => 'aof.person = a.id',
				DBT_SERIES .' s' => 't.series = s.id',
			),
			'ORDER BY' => "a.$this->dbsortby, a.name"
		);
		if ($this->user->id > 0) {
			$qa['SELECT'] .= ', r.user reader';
			$qa['LEFT JOIN'][DBT_READER_OF .' r'] =
				't.id = r.text AND r.user = '. $this->user->id;
		}
		if ( !empty($this->country) ) {
			$qa['WHERE']['country'] = $this->country;
		}
		if ( !empty($this->startwith) ) {
			$qa['WHERE']["a.$this->dbsortby"] =
				array('LIKE', strtr($this->startwith, '.', '%') .'%');
		}
		return $this->db->extselectQ($qa);
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
		$series = empty($series) ? workType($type, false) : ' '.$series;
		$this->authorsData[$authorId]['ser'][$series] = array($orig_series, $seriesType);
		$key = '';
		if ($this->order == 'time') {
			$key .= empty($ayear) ? $year : $ayear;
		}
		$key .= str_pad($sernr, 2, '0', STR_PAD_LEFT).$title . $textId;
		$this->authors_titles[$authorId][$series][$key] = $textId;
		return '';
	}


	protected function makeNavElements() {
		$extra = array(self::FF_SORTBY => '!first',
			self::FF_ORDER => $this->defOrder,
			self::FF_COUNTRY => $this->defCountry,
			self::FF_DLMODE => $this->defDlMode);
		$tocFirst = $this->makeNavButtons($extra, $this->sortby == 'first');
		$extra['sortby'] = '!last';
		$tocLast = $this->makeNavButtons($extra, $this->sortby == 'last');
		$modeInput = $this->makeModeInput();
		$orderInput = $this->makeOrderInput();
		$countryInput = $this->makeCountryInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array(self::FF_MODE, self::FF_ORDER, self::FF_DLMODE, self::FF_COUNTRY));
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
<p>Горните връзки водят към списъци на авторите$extra чиито имена (първо име или фамилия) започват със съответната буква. Чрез препратките „Всички“ можете да разгледате всички автори наведнъж.</p>
$modeExpl
$countryExpl
$dlModeExpl
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени автори.', true);
	}
}
