<?php

class LabelPage extends ViewPage {

	protected $titles = array(
		'simple' => 'Етикети — ',
		'extended' => 'Етикети и заглавия — ',
	);


	public function __construct() {
		parent::__construct();
		$this->action = 'label';
		$this->uCanEditObj = $this->user->canExecute('editLabel');
		$this->qstart = $this->request->value('start', 0);
		$this->qlimit = $this->request->value('limit', 100);
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
		$q = "SELECT id, name, COUNT(h.text) count FROM /*p*/label l
			LEFT JOIN /*p*/text_label h ON l.id = h.label";
		$qWheres = array();
		if ( !empty($this->startwith) ) {
			$this->startwith = $this->db->escape($this->startwith);
			$qWheres[] = "name LIKE '$this->startwith%'";
		}
		if ( !empty($qWheres) ) {
			$q .= ' WHERE '. implode(' AND ', $qWheres);
		}
		$q .= " GROUP BY id ORDER BY name";
		return $q;
	}


	public function makeSimpleListItem($dbrow) {
		extract($dbrow);
		$editLink = $this->uCanEditObj ? $this->makeEditLabelLink($id) : '';
		$q = $this->showDlForm ? "/$this->FF_DLMODE=$this->dlMode" : '';
		$q .= $this->order == 'time' ? "/$this->FF_ORDER=$this->order" : '';
		$labelLink = $this->makeLabelLink($name, $q);
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
		$this->qWheres = '';
		$items = $this->db->iterateOverResult($this->makeExtendedListQuery(),
			'makeExtendedListItem', $this);
		if ( empty($items) ) { return false; }
		$chunkLinks = $this->makeChunkLinks($this->qWheres);
		$o = $chunkLinks .'<ul style="display:none"><li></li>'. $items .'</ul>';
		if ($this->showDlForm) {
			$action = $this->out->hiddenField('action', 'download');
			$submit = $this->out->submitButton('Сваляне на избраните текстове');
			$o = <<<EOS

<form action="$this->root" method="post">
	$action
$o
	$submit
</form>
EOS;
		}
		$o .= $chunkLinks . $this->makeColorLegend();
		return $o;
	}


	protected function makeExtendedListQuery() {
		$qSelect = "SELECT l.id labelId, l.name label,
			t.id textId, t.title, t.lang, t.orig_title, t.orig_lang,
			t.year, t.type, t.sernr, t.size, t.zsize, UNIX_TIMESTAMP(t.date) date,
			GROUP_CONCAT(a.name) author";
		$qFrom = " FROM /*p*/text_label h
			LEFT JOIN /*p*/label l ON h.label = l.id
			LEFT JOIN /*p*/text t ON h.text = t.id
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id";
		if ($this->user->id > 0) {
			$qSelect .= ', r.user reader';
			$qFrom .= "
			LEFT JOIN /*p*/reader_of r ON t.id = r.text AND r.user={$this->user->id}";
		}
		$q = $qSelect . $qFrom;
		$qWhere = array();
		if ( !empty($this->startwith) ) {
			$this->startwith = $this->db->escape($this->startwith);
			$qWhere[] = "l.name LIKE '$this->startwith%'";
		}
		if ( !empty($qWhere) ) {
			$this->qWheres = ' WHERE '. implode(' AND ', $qWhere);
			$q .= $this->qWheres;
		}
		$chrono = $this->order == 'time' ? 't.year,' : '';
		$q .= " GROUP BY l.id, t.id ORDER BY l.name, $chrono t.title";
		$q .= " LIMIT $this->qstart, $this->qlimit";
		return $q;
	}


	public function makeExtendedListItem($dbrow) {
		extract($dbrow);
		$o = '';
		if ($this->curLabel != $label) {
			$this->curLabel = $label;
			$o .= "</ul>\n<h2>$this->curLabel</h2>\n<ul>";
		}
		$extras = array();
		if ( !empty($orig_title) && $orig_lang != $lang ) {
			$extras[] = "<em>$orig_title</em>";
		}
		$textLink = $this->makeTextLink(compact('textId', 'type', 'size', 'zsize', 'title', 'date', 'reader'));
		if ($this->order == 'time') {
			$titleView = '<span class="extra"><tt>'.$this->makeYearView($year).
				'</tt> — </span>'.$textLink;
		} else {
			$titleView = $textLink;
			if ( !empty($year) ) { $extras[] = $year; }
		}
		$extras = empty($extras) ? '' : '('. implode(', ', $extras) .')';
		$dlLink = $this->makeDlLink($textId, $zsize);
		$editLink = $this->userCanEdit ? $this->makeEditTextLink($textId) : '';
		$dlCheckbox = $this->makeDlCheckbox($textId);
		$author = $this->makeAuthorLink($author);
		if ( !empty($author) ) { $author = '— '.$author; }
		$o .= <<<EOS

<li class="$type" title="{$GLOBALS['types'][$type]}">
	$dlCheckbox
	$titleView
	<span class="extra">$extras — $dlLink$editLink</span>
	$author
</li>
EOS;
		return $o;
	}


	protected function makeChunkLinks($where = '') {
		$q = "SELECT COUNT(*) FROM /*p*/text_label h
			LEFT JOIN /*p*/label l ON h.label = l.id
			LEFT JOIN /*p*/text t ON h.text = t.id $where";
		list($count) = $this->db->fetchRow($this->db->query($q));
		if ( $count <= $this->qlimit ) { return ''; }
		$curCnt = $i = 0;
		$o = '';
		$urlq = $this->FF_QUERY.'='.urlencode($this->startwith)."/$this->FF_MODE=$this->mode";
		while ($curCnt < $count) {
			$i++;
			$o .= $this->qstart == $curCnt ? "· <strong>$i</strong> ·" : " <a href='$this->root/$this->action/$urlq/start=$curCnt/limit=$this->qlimit'>$i</a> ";
			$curCnt += $this->qlimit;
		}
		return '<div class="buttonlinks" style="text-align:center; margin-top:1em">Страници:'.
			trim($o, '·').'</div>';
	}


	protected function makeNavElements() {
		$toc = $this->makeNavButtons(array($this->FF_ORDER => '',
			$this->FF_DLMODE => 'one'));
		$modeInput = $this->makeModeInput();
		$orderInput = $this->makeOrderInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array($this->FF_MODE, $this->FF_ORDER, $this->FF_DLMODE));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p class="buttonlinks" style="margin-bottom:1em;"
	title="Това са препратки към списъци на етикети, започващи със съответната буква">
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
<p>Горните връзки водят към списъци на етикетите$extra
започващи със съответната буква. Чрез препратката „Всички“
можете да разгледате всички етикети наведнъж.</p>
$modeExpl
$dlModeExpl
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени етикети.', true);
	}
}
?>
