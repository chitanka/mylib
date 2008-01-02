<?php

class BookPage extends ViewPage {

	protected
		$titles = array(
			'simple' => 'Списък на книги — $1',
			'extended' => '$1 — Книги');


	public function __construct() {
		parent::__construct();
		$this->action = 'book';
		$this->uCanEditObj = $this->user->canExecute('editBook');
	}


	protected function makeSimpleList() {
		$this->curch = '';
		$items = $this->db->iterateOverResult($this->makeSimpleListQuery(),
			'makeSimpleListItem', $this);
		if ( empty($items) ) {
			return false;
		}
		if ( substr_count($items, '<li>') == 1 ) {
			#$this->mode1 = 'extended';
			#return $this->makeExtendedList(); // only one object
		}
		$o = '<ul style="display:none"><li></li>'. $items .'</ul>';
		return $o;
	}


	protected function makeSimpleListQuery() {
		$qa = array(
			'SELECT' => 'b.*, GROUP_CONCAT(DISTINCT a.name) author',
			'FROM' => DBT_BOOK_TEXT .' bt',
			'LEFT JOIN' => array(
				DBT_BOOK .' b' => 'bt.book = b.id',
				DBT_TEXT .' t' => 'bt.text = t.id',
				DBT_AUTHOR_OF .' aof' => 'aof.text = t.id',
				DBT_PERSON .' a' => 'a.id = aof.person',
			),
			'GROUP BY' => 'b.id',
			'ORDER BY' => 'b.title',
		);
		if ( !empty($this->startwith) ) {
			$qa['WHERE']['b.title'] = array('LIKE', $this->startwith .'%');
		}
		return $this->db->extselectQ($qa);
	}


	public function makeSimpleListItem($dbrow) {
		extract($dbrow);
		$o = '';
		$lcurch = $this->firstChar($title);
		if ($this->curch != $lcurch) {
			$this->curch = $lcurch;
			$o .= "</ul>\n<h2>$this->curch</h2>\n<ul>";
		}
		$editLink = $this->uCanEditObj ? $this->makeEditBookLink($id) : '';
		$author = $this->makeAuthorLink($author);
		if ( !empty($author) ) { $author = '— '.$author; }
		$query = array();
		if ($this->showDlForm) $query[self::FF_DLMODE] = $this->dlMode;
		if ($this->order != $this->defOrder) {
			$query[self::FF_ORDER] = $this->order;
		}
		$link = $this->makeBookLink($title, '', '', $query);
		$o .= <<<EOS

<li>
	$link
	<span class="extra">
	$editLink
	</span>
	$author
</li>
EOS;
		return $o;
	}


	protected function makeExtendedList() {
		$this->curObj = 0;
		$this->curPart = '';
		$this->curAuthor = '';
		$this->displayAuthor = false;
		$this->data = $this->objTitles = array();
		$this->db->iterateOverResult($this->makeExtendedListQuery(),
			'makeExtendedListItem', $this);
		if ( empty($this->objTitles) ) {
			return false;
		}
		$this->data[$this->curObj]['book'] = $this->objTitles[0]['book'];
		$this->data[$this->curObj]['type'] = $this->objTitles[0]['bookType'];
		$this->data[$this->curObj]['author'] = ($this->displayAuthor ? '' : $this->curAuthor);

		$o = '';
		$toc = '<div id="toc"><h2>Съдържание</h2><ul>';
		$userCanEdit = $this->user->canExecute('edit');
		#dpr($this->data);
		foreach ($this->data as $objId => $objData) {
			extract($objData);
			$fullObjName = ltrim("$author - $book", ' -');
			$bookAnchor = '_'.md5($book);
			$toc .= "\n\t<li><a href='#$bookAnchor'>$book</a></li>";
			$displayAuthor = true;
			if ( !empty($author) ) {
				$author = ' — '. $this->makeAuthorLink($author);
				$displayAuthor = false;
			}
			$editLink = $this->uCanEditObj ? $this->makeEditBookLink($objId) : '';
			$o .= <<<EOS

<h2 id="$bookAnchor">$book $editLink $author</h2>

EOS;
			$annoFile = getContentFilePath('book-anno', $objId);
			if ( file_exists($annoFile) ) {
				$parser = new Sfb2HTMLConverter($annoFile);
				$parser->parse();
				$o .= <<<EOS
<fieldset id="annotation">
<legend>Анотация</legend>
$parser->text
</fieldset>
EOS;
			}
			$tids = array();
			foreach ($titles as $part => $ptitles) {
				if ( !empty($part) ) {
					$o .= "\n<h3>$part</h3>";
				}
				$o .= '<ul>';
				foreach ($ptitles as $tData) {
					extract($tData);
					$tids[] = $textId;
					$dlLink = $this->makeDlLink($textId, $zsize);
					$extras = array();
					if ( !empty($orig_title) && $orig_lang != $lang ) {
						$extras[] = "<em>$orig_title</em>";
					}
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
			}
			if (!$this->showDlForm && count($tids) > 1) {
				$o .= $this->makeDlSeriesForm($tids, $fullObjName, 'цялата книга');
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
		unset($this->objTitles);
		return $toc . $o;
	}


	protected function makeExtendedListQuery() {
		$chrono = $this->order == 'time' ? 't.year,' : '';
		$qa = array(
			'SELECT' => 'b.id objId, b.title book, b.type bookType, bp.name part,
				t.id textId, t.title, t.lang, t.orig_title, t.orig_lang,
				t.year, t.type, t.sernr, t.size, t.zsize, t.entrydate date,
				UNIX_TIMESTAMP(t.entrydate) datestamp,
				GROUP_CONCAT(a.name ORDER BY aof.pos) author',
			'FROM' => DBT_BOOK_TEXT .' bt',
			'LEFT JOIN' => array(
				DBT_BOOK .' b' => 'bt.book = b.id',
				DBT_BOOK_PART .' bp' => 'bt.part = bp.id',
				DBT_TEXT .' t' => 'bt.text = t.id',
				DBT_AUTHOR_OF .' aof' => 'aof.text = t.id',
				DBT_PERSON .' a' => 'a.id = aof.person',
			),
			'WHERE' => array(),
			'GROUP BY' => 'b.id, t.id',
			'ORDER BY' => "b.title, $chrono bt.pos",
// 			'LIMIT' => array($this->qstart, $this->qlimit)
		);
		if ($this->user->id > 0) {
			$qa['SELECT'] .= ', r.user reader';
			$qa['LEFT JOIN'][DBT_READER_OF .' r'] =
				't.id = r.text AND r.user = '. $this->user->id;
		}
		if ( !empty($this->startwith) ) {
			$qa['WHERE']['b.title'] = array('LIKE', $this->startwith .'%');
		}
		return $this->db->extselectQ($qa);
	}


	public function makeExtendedListItem($dbrow) {
		extract($dbrow);
		if ( empty($textId) ) {
			return;
		}
		$this->curPart = $part;
		if ($this->curObj != $objId) {
			if ( !empty($this->curObj) ) {
				$this->data[$this->curObj]['book'] = $this->objTitles[0]['book'];
				$this->data[$this->curObj]['type'] = $this->objTitles[0]['bookType'];
				$this->data[$this->curObj]['author'] = ($this->displayAuthor ? '' : $this->curAuthor);
				$this->curAuthor = '';
				$this->displayAuthor = false;
				$this->objTitles = array();
			}
			$this->curObj = $objId;
		}
		if ($this->curAuthor != $author) {
			if ( !empty($this->curAuthor) ) { $this->displayAuthor = true; }
			$this->curAuthor = $author;
		}
		unset($dbrow['objId']);
		$this->data[$this->curObj]['titles'][$this->curPart][] = $dbrow;
		$this->objTitles[] = $dbrow;
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
	title="Това са препратки към списъци на книгите, започващи със съответната буква">
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
<p>Горните връзки водят към списъци на книгите$extra започващи със съответната буква. Чрез препратката „Всички“ можете да разгледате всички книги наведнъж.</p>
$modeExpl
$dlModeExpl
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени книги.', true);
	}
}
