<?php

class TranslatorPage extends ViewPage {

	const
		DB_TABLE = DBT_PERSON, FF_SORTBY = 'sortby';
	protected
		$titles = array(
			'simple' => 'Списък на преводачи — $1',
			'extended' => '$1 — Преводачи',
		);


	public function __construct() {
		parent::__construct();
		$this->action = 'translator';
		$this->sortby = $this->request->value('sortby', '');
		$this->dbsortby = $this->sortby == 'last' ? 'last_name' : 'name';
		$this->uCanEditObj = $this->user->canExecute('editPerson');
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
		$qa = array(
			'SELECT' => 'id, name, last_name, country',
			'FROM' => self::DB_TABLE,
			'WHERE' => array('role & 2'),
			'ORDER BY' => "$this->dbsortby, name",
		);
		if ( !empty($this->startwith) ) {
			$dbstartwith = strtr($this->startwith, '.', '%');
			$qa['WHERE'][$this->dbsortby] = array('LIKE', $dbstartwith .'%');
		}
		if ( !empty($this->country) ) {
			$qa['WHERE']['country'] = $this->country;
		}
		return $this->db->extselectQ($qa);
	}


	public function makeSimpleListItem($dbrow) {
		extract($dbrow);
		$o = '';
		$lcurch = $this->firstChar( $dbrow[$this->dbsortby] );
		if ($this->curch != $lcurch) {
			$this->curch = $lcurch;
			$o .= "</ul>\n<h2>$this->curch</h2>\n<ul>";
		}
		$editLink = $this->uCanEditObj
			? $this->makeEditTranslatorLink($id) : '';
		$query = array();
		if ($this->showDlForm) $query[self::FF_DLMODE] = $this->dlMode;
		if ($this->order == 'time') $query[self::FF_ORDER] = $this->order;
		$link = $this->makeTranslatorLink($name, $this->sortby, '', '', $query);
		$o .= <<<EOS

<li>
	$link
	<span class="extra">
	$editLink
	</span>
</li>
EOS;
		return $o;
	}


	protected function makeExtendedList() {
		$this->translators = array();
		$reader = NULL;
		$this->db->iterateOverResult($this->makeExtendedListQuery(),
			'makeExtendedListItem', $this);
		if ( empty($this->translators) ) {
			return false;
		}
		$o = '';
		$toc = '<div id="toc"><h2>Съдържание</h2><ul>';
		$userCanEdit = $this->user->canExecute('edit');
		foreach ($this->translators as $translatorId) {
			extract( $this->translatorsData[$translatorId] );
			$anchor = md5($translator);
			$showName = $this->formatPersonName($translator, $this->sortby);
			$toc .= "\n\t<li><a href='#$anchor'>$showName</a></li>";
			$editLink = $this->uCanEditObj
				? $this->makeEditTranslatorLink($translatorId) : '';
			$ainfo = array();
			if ( !empty($real_name) && $translator != $real_name ) {
				$ainfo[] = 'Пълно име: '.$real_name;
			}
			if ($is_a) {
				$params = array(self::FF_ACTION=>'author', 'q'=>$translator);
				$ainfo[] = $this->out->internLink('Авторски произведения',
					$params, 2, 'Преглед на авторските произведения на '.$translator);
			}
			$ainfo[] = empty($info) ? $this->makeInfoLink($translator)
				: $this->makeMwLink($translator, $info);
			$ainfo = implode(', ', $ainfo);
			$o .= <<<EOS

<h2 id="$anchor">$showName $editLink</h2>
<p class="info">$ainfo</p>

EOS;
			$series = $this->translators_titles[$translatorId];
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
	<ul>
EOS;
				ksort($titles);
				foreach ($titles as $textId) {
					extract( $this->textsData[$textId] );
					$dlLink = $this->makeDlLink($textId, $zsize);
					$extras = array();
					if ( !empty($orig_title) && $orig_lang != $lang ) {
						$extras[] = "<em>$orig_title</em>";
					}
					if ( !empty($year) ) {
						$extras[] = $this->makeYearView($year, 0, $year2);
					}
					$textLink = $this->makeTextLink(compact('textId', 'type', 'size', 'zsize', 'title', 'date', 'datestamp', 'reader'));
					if ($this->order == 'time') {
						$titleView = '<span class="extra"><tt>'.
							$this->makeYearView($trans_year, $tr_trans_year, $trans_year2).
							'</tt> — </span>'.$textLink;
					} else {
						$titleView = $textLink;
					}
					$extras = empty($extras) ? '': '('. implode(', ', $extras) .')';
					$dlCheckbox = $this->makeDlCheckbox($textId);
					$editLink = $userCanEdit ? $this->makeEditTextLink($textId) : '';
					if ( $sernr > 0 ) { $title = "$sernr. $title"; }
					$author = $collection == 'true' ? '' : $this->makeAuthorLink($author);
					if ( !empty($author) ) { $author = 'от '.$author; }
					$title = workType($type);
					$o .=<<<EOS

<li class="$type" title="$title">
	$dlCheckbox
	$titleView
	$author
	<span class="extra">
	$extras
	— $dlLink $editLink
	</span>
</li>
EOS;
				}
				$o .= "</ul>\n</fieldset>\n";
			}
			$o .= "</ul>\n" . $this->makeDlSubmit();
		}
		$toc .= "</ul></div><p style='clear:both'></p>\n";
		if (count($this->translators) < 2) { $toc = ''; }
		if ($this->showDlForm) {
			$action = $this->out->hiddenField(self::FF_ACTION, 'download');
			$o = <<<EOS
<form action="$this->root" method="post"><div>
	$action
$o
</div></form>
EOS;
		}
		$o .= $this->makeColorLegend();
		unset($this->translatorsData);
		unset($this->translators_titles);
		unset($this->textsData);
		return $toc . $o;
	}


	protected function makeExtendedListQuery() {
		$qa = array(
			'SELECT' => 'tr.id translatorId, tr.name translator,
				tr.real_name, tr.country, (tr.role & 1) is_a, tr.info,
				tof.year tr_trans_year,
				t.id textId, t.title, t.lang, t.orig_title, t.collection,
				t.orig_lang, t.year, t.year2, t.trans_year, t.trans_year2, t.type,
				t.sernr, t.size, t.zsize, t.entrydate date,
				UNIX_TIMESTAMP(t.entrydate) datestamp,
				GROUP_CONCAT(a.name ORDER BY aof.pos) author,
				s.name series, s.orig_name orig_series, s.type seriesType',
			'FROM' => DBT_TRANSLATOR_OF .' tof',
			'LEFT JOIN' => array(
				DBT_TEXT .' t' => 'tof.text = t.id',
				self::DB_TABLE .' tr' => 'tof.person = tr.id',
				DBT_AUTHOR_OF .' aof' => 'aof.text = tof.text',
				self::DB_TABLE .' a' => 'aof.person = a.id',
				DBT_SERIES .' s' => 't.series = s.id',
			),
			'GROUP BY' => 'tr.id, t.id',
			'ORDER BY' => "tr.$this->dbsortby, tr.name",
// 			'LIMIT' => array($this->qstart, $this->qlimit)
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
			$dbstartwith = strtr($this->startwith, '.', '%');
			$qa['WHERE']["tr.$this->dbsortby"] = array('LIKE', $dbstartwith .'%');
		}
		return $this->db->extselectQ($qa);
	}


	public function makeExtendedListItem($dbrow) {
		extract($dbrow);
		if ( empty($textId) ) {
			return; // invalid translator-text relation
		}
		if ( !isset($this->translatorsData[$translatorId]) ) {
			$this->translatorsData[$translatorId] =
				compact('translator', 'real_name', 'country', 'is_a', 'info');
			$this->translators[] = $translatorId;
		}
		$this->textsData[$textId] = $dbrow;
		$series = empty($series) ? workType($type, false) : ' '.$series;
		$this->translatorsData[$translatorId]['ser'][$series] = array($orig_series, $seriesType);
		$key = '';
		if ($this->order == 'time') {
			$key .= empty($tr_trans_year) ? $trans_year : $tr_trans_year;
		}
		$key .= str_pad($sernr, 2, '0', STR_PAD_LEFT).$title . $textId;
		$this->translators_titles[$translatorId][$series][$key] = $textId;
		return '';
	}


	protected function makeNavElements() {
		$extra = array(self::FF_SORTBY=>'!first',
			self::FF_ORDER => $this->defOrder,
			self::FF_DLMODE => $this->defDlMode);
		$tocFirst = $this->makeNavButtons($extra, $this->sortby == 'first');
		$extra[self::FF_SORTBY] = '!last';
		$tocLast = $this->makeNavButtons($extra, $this->sortby == 'last');
		$modeInput = $this->makeModeInput();
		$orderInput = $this->makeOrderInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array(self::FF_MODE, self::FF_ORDER, self::FF_DLMODE));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p>Преглед на преводачите по:</p>
<ul class="buttonlinks" style="line-height:1.8em">
	<li title="Това са препратки към списъци на преводачите, подредени по първо име"><em>Първо име</em> — $tocFirst</li>
	<li title="Това са препратки към списъци на преводачите, подредени по фамилия"><em>Фамилия</em> — $tocLast</li>
</ul>
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
		$params = array(self::FF_ACTION=>'title', 'mode'=>'simple', 'woTransl'=>1);
		$notrans = $this->out->internLink('списък на тези произведения', $params, 1);
		return <<<EOS
<p>Горните връзки водят към списъци на преводачите$extra чиито имена (първо име или фамилия) започват със съответната буква. Чрез препратките „Всички“ (такива има и в навигационното меню) можете да разгледате всички преводачи наведнъж.</p>
$modeExpl
$dlModeExpl

<p>Не са въведени преводачите на много преводни текстове. Ето $notrans. Всяка помощ за попълване на лиспващата информация е добре дошла.</p>
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени преводачи.', true);
	}
}
