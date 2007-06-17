<?php

class TranslatorPage extends ViewPage {

	protected $FF_SORTBY = 'sortby';
	protected $titles = array(
		'simple' => 'Преводачи — ',
		'extended' => 'Преводачи и заглавия — ',
	);


	public function __construct() {
		parent::__construct();
		$this->action = 'translator';
		$this->mainDbTable = 'person';
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
		$query = "SELECT id, name, last_name, country
			FROM /*p*/$this->mainDbTable";
		$qWheres = array('role & 2');
		if ( !empty($this->country) ) {
			$dbcountry = $this->db->escape($this->country);
			$qWheres[] = "country='$dbcountry'";
		}
		if ( !empty($this->startwith) ) {
			$dbstartwith = strtr($this->startwith, '.', '%');
			$dbstartwith = $this->db->escape($dbstartwith);
			$qWheres[] = "$this->dbsortby LIKE '$dbstartwith%'";
		}
		if ( !empty($qWheres) ) {
			$query .= ' WHERE '. implode(' AND ', $qWheres);
		}
		$query .= " ORDER BY $this->dbsortby, name";
		return $query;
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
		$query = $this->showDlForm ? "/$this->FF_DLMODE=$this->dlMode" : '';
		$query .= $this->order == 'time' ? "/$this->FF_ORDER=$this->order" : '';
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
		if ( empty($this->translators) ) { return false; }

		$o = '';
		$toc = '<div id="toc"><h2>Съдържание</h2><ul>';
		$userCanEdit = $this->user->canExecute('edit');
		foreach ($this->translators as $translatorId) {
			extract( $this->translatorsData[$translatorId] );
			$translatorEnc = $this->urlencode($translator);
			$anchor = strtr($translatorEnc, '%+', '__');
			$showName = $this->formatPersonName($translator, $this->sortby);
			$toc .= "\n\t<li><a href=\"#$anchor\">$showName</a></li>";
			$editLink = $this->uCanEditObj
				? $this->makeEditTranslatorLink($translatorId) : '';
			$infoLink = empty($info) ? $this->makeInfoLink($translator)
				: $this->makeMwLink($translator, $info);
			$authorLink = $is_a
				? "<a href='$this->root/author/$translatorEnc' title='Преглед на авторските произведения на $translator'>Авторски произведения</a>, "
				: '';
			$real_name = empty($real_name) || $translator == $real_name ? ''
				: "Пълно име: $real_name, ";
			$o .= <<<EOS

<h2 id="$anchor">$showName $editLink</h2>
<p class="info">$real_name $authorLink $infoLink</p>

EOS;
			$series = $this->translators_titles[$translatorId];
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
					$textLink = $this->makeTextLink(compact('textId', 'type', 'size', 'zsize', 'title', 'date', 'reader'));
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
					$o .=<<<EOS

<li class="$type" title="{$GLOBALS['types'][$type]}">
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
		$qSelect = "SELECT tr.id translatorId, tr.name translator,
		tr.real_name, tr.country,
		(tr.role & 1) is_a, tr.info, tof.year tr_trans_year,
		t.id textId, t.title, t.lang, t.orig_title, t.collection,
		t.orig_lang, t.year, t.year2, t.trans_year, t.trans_year2, t.type,
		t.sernr, t.size, t.zsize, UNIX_TIMESTAMP(t.entrydate) date,
		GROUP_CONCAT(a.name ORDER BY aof.pos) author,
		s.name series, s.orig_name orig_series";
		$qFrom = " FROM /*p*/translator_of tof
		LEFT JOIN /*p*/text t ON tof.text = t.id
		LEFT JOIN /*p*/$this->mainDbTable tr ON tof.translator = tr.id
		LEFT JOIN /*p*/author_of aof ON aof.text = tof.text
		LEFT JOIN /*p*/$this->mainDbTable a ON aof.author = a.id
		LEFT JOIN /*p*/series s ON t.series = s.id";
		if ($this->user->id > 0) {
			$qSelect .= ', r.user reader';
			$qFrom .= "\nLEFT JOIN /*p*/reader_of r ON t.id = r.text AND r.user = {$this->user->id}";
		}
		$query = $qSelect . $qFrom;

		$qWheres = array();
		if ( !empty($this->country) ) {
			$dbcountry = $this->db->escape($this->country);
			$qWheres[] = "country='$dbcountry'";
		}
		if ( !empty($this->startwith) ) {
			$dbstartwith = strtr($this->startwith, '.', '%');
			$dbstartwith = $this->db->escape($dbstartwith);
			$qWheres[] = "tr.$this->dbsortby LIKE '$dbstartwith%'";
		}
		if ( !empty($qWheres) ) {
			$query .= ' WHERE '. implode(' AND ', $qWheres);
		}
		$query .= " GROUP BY tr.id, t.id ORDER BY tr.$this->dbsortby, tr.name";
		return $query;
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
		$series = empty($series) ? $GLOBALS['typesPl'][$type] : ' '.$series;
		$this->translatorsData[$translatorId]['ser'][$series] = $orig_series;
		$key = '';
		if ($this->order == 'time') {
			$key .= empty($tr_trans_year) ? $trans_year : $tr_trans_year;
		}
		$key .= str_pad($sernr, 2, '0', STR_PAD_LEFT).$title . $textId;
		$this->translators_titles[$translatorId][$series][$key] = $textId;
		return '';
	}


	protected function makeNavElements() {
		$extra = array($this->FF_SORTBY=>'!first', $this->FF_ORDER => '',
			$this->FF_DLMODE => 'one');
		$tocFirst = $this->makeNavButtons($extra, $this->sortby == 'first');
		$extra[$this->FF_SORTBY] = '!last';
		$tocLast = $this->makeNavButtons($extra, $this->sortby == 'last');
		$modeInput = $this->makeModeInput();
		$orderInput = $this->makeOrderInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array($this->FF_MODE, $this->FF_ORDER, $this->FF_DLMODE));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p>Преглед на преводачите по:</p>
<ul class="buttonlinks" style="line-height:1.8em">
	<li title="Това са препратки към списъци на преводачите, подредени по първо име"><em>Първо име</em> — $tocFirst</li>
	<li title="Това са препратки към списъци на преводачите, подредени по фамилия"><em>Фамилия</em> — $tocLast</li>
</ul>
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
<p>Горните връзки водят към списъци на преводачите$extra
чиито имена (първо име или фамилия) започват със съответната буква.
Чрез препратките „Всички“ (такива има и в навигационното меню) можете
да разгледате всички преводачи наведнъж.</p>
$modeExpl
$dlModeExpl

<p>Не са въведени преводачите на около половината преводни текстове.
Ето <a href="$this->root/title/mode=simple/woTransl=1">списък на тези
произведения</a>. Всяка помощ за попълване на лиспващата информация е добре дошла.</p>
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени преводачи.', true);
	}
}
?>
