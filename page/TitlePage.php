<?php

class TitlePage extends ViewPage {

	protected $FF_TYPE = 'type', $FF_ORIGLANG = 'orig_lang';
	protected $titles = array(
		'simple' => 'Заглавия — ',
		'extended' => 'Заглавия — ',
	);


	public function __construct() {
		parent::__construct();
		$this->action = 'title';
		$this->type = $this->request->value($this->FF_TYPE, '');
		$this->orig_lang = $this->request->value($this->FF_ORIGLANG, '');
		$this->woTransl = $this->request->value('woTransl', 0);
		$this->woLabel = $this->request->value('woLabel', 0);
		if ($this->order == 'time' && empty($this->startwith)) {
			$this->showHeaders = false;
		}
	}


	protected function makeSimpleList() {
		$this->userCanEdit = $this->user->canExecute('edit');
		$this->curch = '';
		$items = $this->db->iterateOverResult($this->makeSimpleListQuery(),
			'makeSimpleListItem', $this);
		if ( empty($items) ) { return false; }
		$listHack = $this->showHeaders ? ' style="display:none"><li></li>' : '>';
		$o = '<ul'.$listHack. $items .'</ul>';
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
		$o .= $this->makeColorLegend();
		return $o;
	}


	protected function makeSimpleListQuery() {
		$qSelect = "SELECT GROUP_CONCAT(a.name ORDER BY aof.pos) author,
			t.id textId, t.title, t.orig_title, t.year, t.type, t.size, t.zsize,
			t.lang, t.orig_lang, t.collection, UNIX_TIMESTAMP(t.entrydate) date";
		$qFrom = " FROM /*p*/author_of aof
			LEFT JOIN /*p*/text t ON aof.text = t.id
			LEFT JOIN /*p*/person a ON aof.author = a.id";
		if ($this->user->id > 0) {
			$qSelect .= ', r.user reader';
			$qFrom .= "
			LEFT JOIN /*p*/reader_of r ON t.id = r.text AND r.user = {$this->user->id}";
		}
		$qWheres = array();
		if ($this->woTransl) {
			$qFrom .= "\nLEFT JOIN /*p*/translator_of tof ON t.id = tof.text";
			$qWheres[] = "t.orig_lang != 'bg' AND tof.text IS NULL";
			$this->addMessage('Това е списък на произведенията, за които липсва
			информация за преводача. Ще е чудесно, ако можете да ми помогнете
			да я добавя!');
		}
		if ($this->woLabel) {
			$qFrom .= "\nLEFT JOIN /*p*/text_label h ON h.text = t.id";
			$qWheres[] = "h.label is null";
		}
		if ( !empty($this->startwith) ) {
			$this->startwith = $this->db->escape($this->startwith);
			$qWheres[] = "t.title LIKE '$this->startwith%'";
				#OR t.orig_title LIKE '$this->startwith%'";
		}
		if ( !empty($this->type) ) {
			$qWheres[] = "t.type = '$this->type'";
		}
		if ( !empty($this->orig_lang) ) {
			$qWheres[] = "t.orig_lang = '$this->orig_lang'";
		}
		$query = $qSelect . $qFrom;
		if ( !empty($qWheres) ) {
			$query .= ' WHERE '. implode(' AND ', $qWheres);
		}
		$chrono = $this->order == 'time' ? 't.year,' : '';
		$query .= " GROUP BY t.id ORDER BY $chrono t.title";
		return $query;
	}


	public function makeSimpleListItem($dbrow) {
		extract($dbrow);
		$o = '';
		$lcurch = $this->firstChar($title);
		if ($this->showHeaders && $this->curch != $lcurch) {
			$this->curch = $lcurch;
			$o .= "</ul>\n<h2>$this->curch</h2>\n<ul>";
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
		$author = $collection == 'true' ? '' : $this->makeAuthorLink($author);
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


	protected function makeNavElements() {
		$toc = $this->makeNavButtons(array($this->FF_ORDER=>'',
			$this->FF_TYPE=>'', $this->FF_DLMODE=>'one', $this->FF_ORIGLANG=>''));
		$orderInput = $this->makeOrderInput();
		$typeInput = $this->makeTypeInput();
		$origLangInput = $this->makeOrigLangInput();
		$dlModeInput = $this->makeDlModeInput();
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array($this->FF_ORDER, $this->FF_TYPE, $this->FF_DLMODE, $this->FF_ORIGLANG));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p class="buttonlinks" style="margin-bottom:1em"
	title="Това са препратки към списъци на заглавията, започващи със съответната буква">
$toc
</p>
<form action="$this->root" style="text-align:center">
<div>
	$inputFields
$orderInput &nbsp;
$typeInput &nbsp;
$origLangInput &nbsp;
$dlModeInput
	<noscript><div style="display:inline">$submit</div></noscript>
</div>
</form>
EOS;
	}


	protected function makeExplanations() {
		$typeExpl = $this->makeTypeExplanation();
		$origLangExpl = $this->makeOrigLangExplanation();
		$dlModeExpl = $this->makeDlModeExplanation();
		return <<<EOS
<p>Горните връзки водят към списъци на заглавията, които започват със
съответната буква. Чрез препратката „Всички“ (такава има и в навигационното
меню) можете да разгледате всички заглавия наведнъж. Страницата обаче е
големичка.</p>

<p>Ако нямате представа какво ви се чете, можете да изпробвате късмета си с
някое <a href="$this->root/text/random">случайно заглавие</a>.</p>

$typeExpl

$origLangExpl

$dlModeExpl
EOS;
	}


	protected function makeTypeExplanation() {
		return <<<EOS
<p>От падащото меню „Форма“ можете да изберете формата на показваните произведения.
По подразбиране се показват текстовете от всички форми.</p>
EOS;
	}


	protected function makeOrigLangExplanation() {
		return <<<EOS
<p>Чрез менюто „Оригинален език“ се указва показването само на тези произведения,
чийто оригинален език е избраният.</p>
EOS;
	}


	protected function makeTypeInput() {
		$opts = array_merge(array('' => '(Всички)'), $GLOBALS['typesPl']);
		$type = $this->out->selectBox($this->FF_TYPE, '', $opts, $this->type, 0,
			'onchange="this.form.submit()"');
		return "<label for='$this->FF_TYPE'>Форма:</label>&nbsp;".$type;

	}


	protected function makeOrigLangInput() {
		$opts = array_merge(array('' => '(Всички)'), $GLOBALS['langs']);
		$lang = $this->out->selectBox($this->FF_ORIGLANG, '', $opts, $this->orig_lang,
			0, 'onchange="this.form.submit()"');
		return "<label for=$this->FF_ORIGLANG>Оригинален език:</label>&nbsp;".$lang;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени заглавия.', true);
	}
}
?>
