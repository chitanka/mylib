<?php

class LabelPage extends ViewPage {

	protected
		$titles = array(
			'simple' => 'Етикети — ',
			'extended' => 'Етикети и заглавия — ',
		),
		$defListLimit = 100, $maxListLimit = 500;


	public function __construct() {
		parent::__construct();
		$this->action = 'label';
		$this->uCanEditObj = $this->user->canExecute('editLabel');
		$this->initPaginationFields();
	}


	protected function makeSimpleList() {
		$items = $this->db->iterateOverResult($this->makeSimpleListQuery(),
			'makeSimpleListItem', $this);
		if ( empty($items) ) { return false; }
		if ( substr_count($items, '<li>') == 1 ) {
			$this->mode1 = 'extended';
			return $this->makeExtendedList(); // only one object
		}
		$o = '<p style="margin-top:1em">В скоби е посочен броят на произведенията със съответния етикет.</p><ul>'. $items .'</ul>';
		return $o;
	}


	protected function makeSimpleListQuery() {
		$qa = array(
			'SELECT' => 'id, name, COUNT(h.text) count',
			'FROM' => DBT_LABEL .' l',
			'LEFT JOIN' => array(DBT_TEXT_LABEL .' h' => 'l.id = h.label'),
			'GROUP BY' => 'id',
			'ORDER BY' => 'name',
		);
		if ( !empty($this->startwith) ) {
			$qa['WHERE']['name'] = array('LIKE', $this->startwith .'%');
		}
		return $this->db->extselectQ($qa);
	}


	public function makeSimpleListItem($dbrow) {
		extract($dbrow);
		$editLink = $this->uCanEditObj ? $this->makeEditLabelLink($id) : '';
		$query = array();
		if ($this->showDlForm) $query[self::FF_DLMODE] = $this->dlMode;
		if ($this->order == 'time') $query[self::FF_ORDER] = $this->order;
		$labelLink = $this->makeLabelLink($name, $query);
		$o = <<<EOS

<li>
	$labelLink ($count)
	<span class="extra">$editLink</span>
</li>
EOS;
		return $o;
	}


	protected function makeExtendedList() {
		$reader = NULL;
		$this->curLabel = '';
		$this->userCanEdit = $this->user->canExecute('edit');
		$this->qWhere = array(); // save here the WHERE clause of the query
		$q = $this->makeExtendedListQuery();
		$items = $this->db->iterateOverResult($q, 'makeExtendedListItem', $this);
		if ( empty($items) ) { return false; }
		$chunkLinks = $this->makeChunkLinks();
		$o = $chunkLinks .'<ul style="display:none"><li></li>'. $items .'</ul>';
		if ($this->showDlForm) {
			$action = $this->out->hiddenField(self::FF_ACTION, 'download');
			$submit = $this->out->submitButton('Сваляне на избраните текстове');
			$o = <<<EOS

<form action="$this->root" method="post"><div>
	$action
$o
	$submit
</div></form>
EOS;
		}
		$o .= $chunkLinks . $this->makeColorLegend();
		return $o;
	}


	/**
	Side effect: initializes $this->qWhere with the “WHERE” clause
	*/
	protected function makeExtendedListQuery() {
		$chrono = $this->order == 'time' ? 't.year,' : '';
		$qa = array(
			'SELECT' => 'l.id labelId, l.name label,
				t.id textId, t.title, t.lang, t.orig_title, t.orig_lang,
				t.collection, t.year, t.type, t.sernr, t.size, t.zsize,
				t.entrydate date, UNIX_TIMESTAMP(t.entrydate) datestamp,
				GROUP_CONCAT(a.name) author,
				s.name series, s.orig_name orig_series',
			'FROM' => DBT_TEXT_LABEL .' h',
			'LEFT JOIN' => array(
				DBT_LABEL .' l' => 'h.label = l.id',
				DBT_TEXT .' t' => 'h.text = t.id',
				DBT_AUTHOR_OF .' aof' => 't.id = aof.text',
				DBT_PERSON .' a' => 'aof.author = a.id',
				DBT_SERIES .' s' => 't.series = s.id',
			),
			'WHERE' => array(),
			'GROUP BY' => 'l.id, t.id',
			'ORDER BY' => "l.name, $chrono t.title",
			'LIMIT' => array($this->loffset, $this->llimit)
		);
		if ($this->user->id > 0) {
			$qa['SELECT'] .= ', r.user reader';
			$qa['LEFT JOIN'][DBT_READER_OF .' r'] =
				't.id = r.text AND r.user = '. $this->user->id;
		}
		if ( !empty($this->startwith) ) {
			$qa['WHERE']['l.name'] = array('LIKE', $this->startwith .'%');
		}
		$this->qWhere = $qa['WHERE'];
		return $this->db->extselectQ($qa);
	}


	public function makeExtendedListItem($dbrow) {
		extract($dbrow);
		$o = '';
		if ($this->curLabel != $dbrow['label']) {
			$this->curLabel = $dbrow['label'];
			$o .= "</ul>\n<h2>$this->curLabel</h2>\n<ul>";
		}
		$o .= $this->makeListItemForTitle($dbrow);
		return $o;
	}


	protected function makeChunkLinks() {
		$qa = array(
			'SELECT' => 'COUNT(*)',
			'FROM' => DBT_TEXT_LABEL .' h',
			'LEFT JOIN' => array(
				DBT_LABEL .' l' => 'h.label = l.id',
				DBT_TEXT .' t' => 'h.text = t.id',
			),
			'WHERE' => $this->qWhere,
		);
		list($count) = $this->db->fetchRow($this->db->extselect($qa));
		$urlq = array(self::FF_QUERY => $this->startwith,
			self::FF_MODE => $this->mode);
		return $this->makePageLinks($count, $this->llimit, $this->loffset, $urlq);
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
	title="Това са препратки към списъци на етикети, започващи със съответната буква">
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
<p>Горните връзки водят към списъци на етикетите$extra започващи със съответната буква. Чрез препратката „Всички“ можете да разгледате всички етикети наведнъж.</p>
$modeExpl
$dlModeExpl
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени етикети.', true);
	}
}
