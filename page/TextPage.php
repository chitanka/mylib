<?php
class TextPage extends Page {

	const DB_TABLE = DBT_TEXT;
	/** minimal text size for annotation */
	protected $minTextSizeForAnno = 50000;


	public function __construct() {
		parent::__construct();
		$this->action = 'text';
		$this->ttitle = $this->textId = $this->request->value('textId', 0, 1);
		if ( empty($this->textId) ) {
			$this->ttitle = $this->request->value('title', '', 1);
		}
		$this->chunkId = $this->request->value('chunkId', 1, 2);
		$this->isRead = false;
		$this->hasAnno = $this->hasExtraInfo = false;
	}


	protected function buildContent() {
		if ( $this->textId == 'random' ) {
			return $this->makeRandomContent();
		}
		if ( empty($this->textId) && empty($this->ttitle) ) {
			$this->addMessage('Не е посочен нито номер, нито заглавие на текст.', true);
			return '';
		}
		if ( !$this->initData() ) { return ''; }
		$this->user->markTextAsRead($this->textId);
		if ( $this->chunkId == 'raw' ) {
			return $this->makeRawContent();
		}
		if ( !is_numeric($this->chunkId) || $this->chunkId < 0 ) $this->chunkId = 1;
		$this->title = '';
		if ( !$this->work->collection && !empty($this->work->author_name) ) {
			$this->title .= '<small>'.
				$this->makeAuthorLink($this->work->author_name) .'</small><br />';
		}
		$this->title .= "<span class='text-title'>{$this->work->title}</span>";
		if ( !empty($this->work->subtitle) ) {
			$this->title .= "<br /><small>{$this->work->subtitle}</small>";
		}

		$this->initJs();
		$editLink = $this->user->canExecute('editText')
			? $this->makeEditTextLink($this->textId, $this->chunkId,
				$this->user->canExecute('edit')) : '';
		$this->initExtraLinks(ltrim($editLink, '| '));
		$toc = $this->makeToc();
		if ( $this->chunkId == 0 ) { $this->hasNext = false; }
		if ( (int) $this->chunkId > 1 || $this->hasNext ) {
			$this->title .= ' ('. $this->chunkId .')';
		}
		$anno = $this->chunkId < 2 ? $this->makeAnnotation() : '';
		$extraInfo = $this->chunkId < 2 ? $this->makeExtraInfo() : '';
		return $this->makeInfo() . $extraInfo . $anno . $toc .
			$this->makeTextContent() . $this->makeEndMessage() .
			$this->makeCopyright() . $this->makeReadFooter();
	}


	protected function makeRandomContent() {
		$res = $this->db->select(Work::DB_TABLE, array(), array('MIN(id)', 'MAX(id)'));
		list($this->minTextId, $this->maxTextId) = $this->db->fetchRow($res);
		$c = '';
		while ( empty($c) ) {
			$this->textId = $this->getRandomTextId();
			$c = $this->buildContent();
		}
		$this->messages = '';
		return $c;
	}


	protected function makeRawContent() {
		$file = getContentFilePath('text', $this->textId);
		if ( !file_exists($file) ) {
			$this->addMessage("Няма текст с номер $this->textId.", true);
			return '';
		}
		if ( !$this->isValidEncoding($this->outencoding) ) {
			$this->addMessage("<strong>$this->outencoding</strong> не е валидно название на кодиране. Ето малко предложения: ".$this->makeEncodingSuggestions(), true);
			$this->outencoding = $this->inencoding;
			return;
		}
		$this->contentType = 'text/plain';
		$this->sendCommonHeaders();
		if ( !empty($this->work->author_name) ) {
			$this->encprint("|\t".$this->work->author_name."\n");
		}
		$this->encprint($this->work->getTitleAsSfb() ."\n\n\n");
		$anno = $this->work->getAnnotation();
		if ( !empty($anno) ) {
			$this->encprint("A>\n$anno\nA$\n\n");
		}
		if ( $this->outencoding == $this->inencoding ) {
			readfile($file);
		} else {
			$handle = fopen($file, 'r');
			if ($handle) {
				while ( !feof($handle) ) {
					$this->encprint( fgets($handle) );
				}
				fclose($handle);
			}
		}
		$extra = $this->work->getCopyright() ."\n\n".
			$this->work->getOrigTitleAsSfb() ."\n\n".
			$this->work->getExtraInfo() .
			"\n\n\tСвалено от „{$this->sitename}“ [$this->purl/text/$this->textId]";
		$extra = preg_replace('/\n\n+/', "\n\n", $extra);
		$this->encprint("\nI>$extra\nI$\n");
		$this->outputDone = true;
	}


	protected function makeTextContent() {
		global $curImgDir;
		$file = getContentFilePath('text', $this->textId);
		if ( file_exists($file) ) {
			$parser = new Sfb2HTMLConverter($file, $this->getImgDir());
			$parser->startpos = $this->fpos;
			$parser->maxlinecnt = $this->linecnt;
			$parser->putLineId = true;
			if ($this->work->type == 'playbook') {
				// recognize section links
				$parser->patterns['/#(\d+)/'] = '<a href="#h$1" title="Към част $1"><strong>$1</strong></a>';
			}
			$parser->parse();
			$fn = empty($parser->footnotes) ? ''
				: "\n<fieldset class='footnotes'><legend>Бележки</legend>\n$parser->footnotes</fieldset>";
			return '<p id="textstart" style="clear:both"></p>'.
				"\n<div class='{$this->work->type}'>\n".$parser->text."\n</div>".$fn;
		}
		$tlink = $this->makeSimpleTextLink($this->ttitle, $this->textId, $this->chunkId);
		$this->addMessage("Текстът „{$tlink}“ е празен.", true);
		return '';
	}


	protected function makeInfo() {
		$extra = '';
		if ($this->work->collection) {
			$extra .= '<li>Автори: '.
				$this->makeAuthorLink($this->work->author_name). '</li>';
		}
		if ( !empty($this->work->series) ) {
			$ser = myucfirst(seriesType($this->work->seriesType)) .': '.
				$this->makeSeriesLink($this->work->series);
			if ($this->work->type == 'intro') {
				$ser .= ' (предговор)';
			} else if ( !empty($this->work->sernr) ) {
				$ser .= ' ('.$this->work->sernr.')';
			}
			$extra .= "\n<li>$ser</li>";
		}
		if ( !empty($this->work->books) ) {
			$item = ($this->work->type == 'intro' ? 'Предговор към' : 'Част от')
				.' '. (count($this->work->books) == 1 ? 'книгата' : 'книгите');
			foreach ($this->work->books as $id => $book) {
				$item .= ' „'. $this->makeBookLink($book['title']) .'“';
				$preface = $this->work->getPrefaceOfBook($id);
				if ($preface instanceof Work) {
					$l = $this->makeSimpleTextLink($preface->title, $preface->id, 1, 'предговор');
					$a = $this->makeAuthorLink($preface->author_name);
					$item .= " ($l от $a)";
				}
				$item .= ',';
			}
			$extra .= "\n<li>". rtrim($item, ',') .'</li>';
		}
		if ( $this->work->orig_lang == $this->work->lang ) {
			$extra .= "\n<li><span title='Година на написване или първа публикация'>Година</span>: ".
				$this->makeCustomYearView('orig', $this->work->getYear()) .' '.
				$this->makeLicenseView($this->work->lo_name, $this->work->lo_uri) .'</li>';
		} else {
			if ( empty($this->work->orig_title) ) {
				$params = array(self::FF_ACTION=>'suggestData', 'sa'=>'origTitle',
					'textId' => $this->textId, 'chunkId' => $this->chunkId);
				$link = $this->out->internLink('помогнете ми', $params, 4);
				$orig_title = "[не е въведено; $link да го добавя]";
			} else {
				$orig_title = $this->work->orig_title;
				if ( !empty($this->work->orig_subtitle) ) {
					$orig_title .= ' ('. trim($this->work->orig_subtitle, '()') .')';
				}
			}
			$extra .= "\n<li>Оригинално заглавие: <em>$orig_title</em>".
				', <span title="Година на написване или първа публикация">'.
				$this->makeCustomYearView('orig', $this->work->getYear()) .'</span> '.
				$this->makeLicenseView($this->work->lo_name, $this->work->lo_uri) .
				'</li>';
			$lang = langName($this->work->orig_lang, false);
			if ( !empty($lang) ) $lang = ' от '.$lang;
			$extra .= "\n<li>Превод$lang: ";
			if ( empty($this->work->translator_name) ) {
				$params = array(self::FF_ACTION=>'suggestData', 'sa'=>'translator',
					'textId' => $this->textId, 'chunkId' => $this->chunkId);
				$link = $this->out->internLink('помогнете ми', $params, 4);
				$extra .= "[Няма данни за преводача; $link да го добавя]";
			} else {
				$extra .= $this->makeTranslatorLink($this->work->translator_name, 'first');
			}
			$extra .= ', '.$this->makeCustomYearView('trans', $this->work->getTransYear()).' '.
				$this->makeLicenseView($this->work->lt_name, $this->work->lt_uri) .
				'</li>';
		}
		$extra .= "\n<li>Етикети: ". $this->makeLabelInfo() .'</li>';
		if ($this->work->isRead) {
			$extra .= "\n".'<li>Това произведение е отбелязано като прочетено.</li>';
		}
		$commCnt = $this->getReaderCommentCount();
		$extra .= "\n<li>";
		$extra .= $commCnt > 0 ? "Има <strong>$commCnt</strong>" : 'Все още не са дадени';
		$readCmnts = $commCnt == 1 ? 'читателско мнение' : 'читателски мнения';
		$params = array(self::FF_ACTION=>'comment', 'textId'=>$this->textId);
		$clink = $this->out->internLink("$readCmnts за произведението", $params,
			2, 'Мнения от читатели на произведението');
		$extra .= " $clink.</li>";
		if ( !empty($extra) ) {
			$extra = <<<EOS

<fieldset class="infobox">
	<legend>Информация <a href="#after-infobox" class="non-graphic">(Прескачане на информацията)</a></legend>
	<ul>
	$extra
	</ul>
</fieldset>
<p id="after-infobox" class="non-graphic"><a name="after-infobox"> </a></p>
EOS;
		}
		return $extra;
	}


	protected function makeLabelInfo() {
		$edit = '';
		if ( $this->user->canExecute('editTextLabels') ) {
			$params = array(self::FF_ACTION => 'editTextLabels',
				'textId' => $this->textId, 'chunkId' => $this->chunkId);
			$link = $this->out->internLink('промяна', $params, 3,
				'Възможност за промяна на етикетите на произведението');
			$edit = " &nbsp; [$link]";
		}
		$qa = array(
			'SELECT' => 'name',
			'FROM' => DBT_TEXT_LABEL .' h',
			'LEFT JOIN' => array(DBT_LABEL .' l' => 'h.label = l.id'),
			'WHERE' => array('h.text' => $this->textId),
		);
		$res = $this->db->extselect($qa);
		if ( $this->db->numRows($res) == 0 ) { return 'Няма'.$edit; }
		$o = '';
		while ( $row = $this->db->fetchRow($res) ) {
			$o .= ' '. $this->makeLabelLink($row[0]) .',';
		}
		return rtrim($o, ','). $edit;
	}


	protected function makeAnnotation() {
		$file = getContentFilePath('text-anno', $this->textId);
		if ( $this->chunkId > 1 || !file_exists($file) ) {
			if ($this->work->size < $this->minTextSizeForAnno) {
				return '';
			}
			$params = array(self::FF_ACTION => 'suggestData',
				'sa' => 'annotation', 'textId' => $this->textId);
			$link = $this->out->internLink('Предложете анотация на произведението!',
				$params, 3);
			$anno = "<p style='text-align:center; margin-top:1em'>$link</p>";
		} else {
			$this->hasAnno = true;
			$parser = new Sfb2HTMLConverter($file, $this->getImgDir());
			$parser->parse();
			$anno = $parser->text;
		}
		return <<<EOS

<fieldset id="annotation">
<legend>Анотация</legend>
$anno
</fieldset>
EOS;
	}


	protected function makeExtraInfo() {
		if ( isset($this->extraInfo) ) return $this->extraInfo;
		$file = getContentFilePath('text-info', $this->textId);
		$text = '';
		if ( file_exists($file) ) {
			$parser = new Sfb2HTMLConverter($file, $this->getImgDir());
			$parser->parse();
			$text .= $parser->text;
		}
		foreach ($this->work->books as $id => $book) {
			$file = getContentFilePath('book', $id);
			if ( !file_exists($file) ) { continue; }
			$parser = new Sfb2HTMLConverter($file);
			$parser->parse();
			$text .= '<p><br /></p>' . $parser->text;
		}
		$cover = $this->makeCoverImage();
		if ( empty($text) && empty($cover) ) {
			return '';
		}
		$this->hasExtraInfo = true;
		return $this->extraInfo = <<<EOS

<fieldset class="infobox">
	<legend>Допълнителна информация <a href="#after-extrainfobox" class="non-graphic">(Прескачане на допълнителната информация)</a></legend>
$cover
$text
</fieldset>
<p id="after-extrainfobox" class="non-graphic"><a name="after-extrainfobox"> </a></p>
EOS;
	}


	protected function makeCoverImage() {
		$cnt = 0;
		$cover = '';
		foreach (Work::getCovers($this->textId, $this->work->cover) as $file) {
			$delim = $cnt++ % 2 == 0 ? '<br />' : ' ';
			$cover .= $delim . $this->makeCoverImageView($file);
		}
		return empty($cover) ? '' : "<span style='float:right; margin:0 0 1em 1em'>$cover</span>";
	}

	protected function makeCoverImageView($file) {
		$covurl = $this->rootd .'/'. $file;
		$img = $this->out->image($covurl, 'Корица', '', array('width'=>'200'));
		return $this->out->link($covurl, $img);
	}


	protected function makeToc() {
		$this->hasNext = false;
		$this->nextChunkId = 1;
		$this->prevlev = 0;
		$sel = array('name', 'nr', 'level');
		$key = array('text' => $this->textId);
		$q = $this->db->selectQ(DBT_HEADER, $key, $sel, 'nr');
		$toc = $this->db->iterateOverResult($q, 'makeTocItem', $this);
		if ( substr_count($toc, '<li>') < 2 ) { return ''; }
		$toc .= '</li>'.str_repeat("\n</ul>\n</li>", $this->prevlev-1)."\n</ul>";
		$fulllink = $this->makeSimpleTextLink($this->work->title, $this->textId,
			0, 'Показване на цялото произведение');
		return <<<EOS
<div id="fulltext-link">$fulllink</div>
<div id="toc">
<div id="toctitle"><h2>Съдържание</h2> <a href="#after-toc" class="non-graphic">(Прескачане на съдържанието)</a></div>
$toc
</div>
$this->scriptStart
if (window.showTocToggle) {
	var tocShowText = "показване";
	var tocHideText = "скриване";
	showTocToggle();
	toggleToc();
}
$this->scriptEnd
<p id="after-toc" class="non-graphic"><a name="after-toc"> </a></p>

EOS;
	}


	public function makeTocItem($dbrow) {
		extract($dbrow);
		if ( !$this->hasNext && $this->chunkId < $nr ) {
			$this->nextChunkId = $nr;
			$this->hasNext = true;
		}
		$toci = '';
		if ($this->prevlev < $level) {
			$toci .= "\n<ul>";
		} elseif ($this->prevlev > $level) {
			$toci .= '</li>'.str_repeat("\n</ul>\n</li>", $this->prevlev - $level);
		} else $toci .= '</li>';
		$toci .= "\n<li>";
		if ($this->chunkId == $nr) {
			$toci .= "<strong>$name</strong>";
		} else {
			$p = array(self::FF_ACTION=>$this->action, 'textId'=>$this->textId,
				'chunkId'=>$nr);
			$toci .= $this->out->internLink($name, $p, 3, '', array(), 'textstart');
		}
		$this->prevlev = $level;
		return $toci;
	}


	protected function makeEndMessage() {
		$nextLinks = '';
		if ($this->hasNext && $this->chunkId > 0) {
			$p = array(self::FF_ACTION=>$this->action, 'textId'=>$this->textId,
				'chunkId' => $this->nextChunkId);
			$link = $this->out->internLink('следващата част', $p, 3, '', array(), 'textstart');
			$endMsg = 'Към '. $link .' &rarr;';
		} else {
			$endMsg = 'Край &nbsp; '. ($this->user->canExecute('markRead')
				? $this->makeMarkReadLink() : '');
			$nextLinks = $this->makeNextSeriesWorkLink(true) .
				$this->makeNextBookWorkLink(true);
		}
		return "\n<p id='text-end-msg'>$endMsg</p>$nextLinks";
	}


	protected function makeMarkReadLink() {
		if ($this->work->isRead) {
			return '';
		}
		$params = array(self::FF_ACTION=>'markRead', 'textId'=>$this->textId);
		return $this->out->internLink('Прочетено', $params, 2,
			'Отбелязване като прочетено', array('class' => 'ok'));
	}


	protected function makeCopyright() {
		$o = $this->work->getCopyright($this);
		if ( empty($o) ) {
			return '';
		}
		$o = str_replace('li>,', 'li>', $o); // rm comma separators
		return <<<EOS

<fieldset id="copyright">
<legend>Авторско право</legend>
<ul>
$o
</ul>
</fieldset>
EOS;
	}

	protected function makeNextSeriesWorkLink($separate = false) {
		$nextWork = $this->work->getNextFromSeries();
		$o = '';
		if ( is_object($nextWork) ) {
			$sl = $this->makeSeriesLink($this->work->series);
			$tl = $this->makeSimpleTextLink($nextWork->title, $nextWork->id);
			$type = workTypeArticle($nextWork->type);
			$sep = $separate ? '<hr />' : '';
			$stype = seriesTypeArticle($this->work->seriesType);
			$o = "$sep<p>Към следващото произведение от $stype $sl: $type $tl</p>";
		}
		return $o;
	}

	protected function makeNextBookWorkLink($separate = false) {
		$o = '';
		foreach ($this->work->getNextFromBooks() as $book => $nextWork) {
			if ( is_object($nextWork) ) {
				$sl = $this->makeBookLink($this->work->books[$book]['title']);
				$tl = $this->makeSimpleTextLink($nextWork->title, $nextWork->id);
				$type = workTypeArticle($nextWork->type);
				$o .= "\n<p>Към следващото произведение от книгата $sl: $type $tl</p>";
			}
		}
		$sep = !empty($o) && $separate ? '<hr />' : '';
		return $sep . $o;
	}


	protected function makeReadFooter() {
		return <<<EOS
EOS;
	}


	protected function initJs() {
		$js = <<<EOS
		function setFontSize(size) {
			document.getElementsByTagName("body")[0].style.fontSize = size + "px";
		}
EOS;
		$this->addJs($js);
	}


	protected function initExtraLinks($actions) {
		$sizes = array('10', '11', '12', '13', '14', '15', '16', '17', '18',
			'19', '20', '21', '22', '24', '26', '28', '30', '32', '34', '36',
			'38', '40');
		$opts = array('' => '(Избор)');
		foreach ($sizes as $size) { $opts[$size] = $size .' пиксела'; }
		$fontsize = $this->out->selectBox('fontsize', '', $opts, '', 0,
			array('onchange'=>'javascript:setFontSize(this.value)'));
		$rawLink = $this->makeRawTextLink($this->textId);
		$rawLinkCp1251 = $this->makeRawTextLink($this->textId, 'Суров текст (win)', 'Преглед на суровия текст в кодиране „Windows-1251“', 'cp1251');
		$rawLinkCp866 = $this->makeRawTextLink($this->textId, 'Суров текст (dos)', 'Преглед на суровия текст в кодиране „IBM866“ — руска досовска кирилица', 'cp866');
		$rawLinkMik = $this->makeRawTextLink($this->textId, 'Суров текст (mik)', 'Преглед на суровия текст в нестандартно кодиране „MIK“ — българска досовска кирилица', 'mik');
		$dlLink = $this->makeDlLink($this->textId, $this->work->zsize, 'Суров текст (zip)');
		$sactions = empty($actions) ? '' : "<dt>Действия</dt>\n<dd>$actions</dd>";
		$extraLinks = <<<EOS
<dt><label for="fontsize">Размер на шрифта</label></dt>
<dd style="padding: .3em 0">
	$fontsize
</dd>
<dt>Прегледи</dt>
<dd>$rawLink</dd>
<dd>$dlLink</dd>
<dd>$rawLinkCp1251</dd>
<dd>$rawLinkMik</dd>
<dd>$rawLinkCp866</dd>
$sactions
EOS;
		$this->addExtraLinks($extraLinks);
	}


	protected function initData() {
		if ( empty($this->textId) || !is_numeric($this->textId) ) {
			$this->work = Work::newFromTitle($this->ttitle, $this->user->id);
			$err = "със заглавие <strong>„{$this->ttitle}“</strong>";
		} else {
			$this->work = Work::newFromId($this->textId, $this->user->id);
			$err = "с номер <strong>{$this->textId}</strong>";
		}
		if ( is_null($this->work) ) {
			$this->addMessage("Не съществува текст $err.", true);
			return false;
		}
		$this->textId = $this->work->id;
		$sel = array('fpos', 'linecnt');
		$key = array('text' => $this->textId, 'nr' => $this->chunkId);
		$res = $this->db->select(DBT_HEADER, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		if ( !empty($data) ) {
			extract2object($data, $this);
			#$this->subtitle = str_replace('\n', '<br />', $this->subtitle);
		} else {
			$this->fpos = 0;
			$this->linecnt = 100000;
		}
		return true;
	}


	protected function getRandomTextId() {
		return rand($this->minTextId, $this->maxTextId);
	}


	protected function getReaderCommentCount($textId = NULL) {
		fillOnEmpty($textId, $this->textId);
		$key = array('text' => $textId, '`show`' => 'true');
		return $this->db->getCount(DBT_COMMENT, $key);
	}


	protected function getImgDir() {
		return $this->rootd.'/'. getContentFilePath('img', $this->textId) .'/';
	}


	protected function makeEncodingSuggestions() {
		$encs = array('cp1251', 'windows-1251', 'cp866', 'ibm866', 'ibm855',
			'koi8-r', 'iso-ir-111', 'mik', 'utf-8');
		$l = '';
		foreach ($encs as $enc) {
			$link = $this->makeRawTextLink($this->textId, $enc,
				"Преглед на текста в кодиране „{$enc}“", $enc);
			$l .= "\n<li>$link</li>";
		}
		return "<ul>$l\n</ul>";
	}


	protected function makeCustomYearView($type, $year, $yearAlt = 0, $year2 = 0) {
		$actions = array(
			'orig' => array('year', 'написване или първа публикация'),
			'trans' => array('transYear', 'превод')
		);
		$yearview = $this->makeYearView($year, $yearAlt, $year2);
		if ($yearview{0} == '?') {
			$params = array(self::FF_ACTION=>'suggestData', 'sa'=>$actions[$type][0],
				'textId' => $this->textId, 'chunkId' => $this->chunkId);
			return $this->out->internLink($yearview, $params, 4,
				'Даване на информация за година на '. $actions[$type][1]);
		}
		return $yearview;
	}


	protected function makeLicenseView($name, $uri = '') {
		if ( empty($uri) ) {
			return "($name)";
		}
		return "(<a href='$uri'>$name</a>)";
	}
}
