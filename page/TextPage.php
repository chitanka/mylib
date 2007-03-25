<?php
class TextPage extends Page {

	public function __construct() {
		// if "text/download/..." redirect to "download/..."
		$requrl = @$_SERVER['REQUEST_URI'];
		if ( strpos($requrl, '/download/') !== false ) {
			$requrl = str_replace('text/', '', $requrl);
			header("Location: $requrl");
			exit;
		}
		parent::__construct();
		$this->action = 'text';
		$this->mainDbTable = 'text';
		$this->textId = $this->request->value('textId', 0, 1);
		$this->ttitle = $this->request->value('title', '', 1);
		$this->chunkId = $this->request->value('chunkId', 1, 2);
		$this->isRead = false;
		$this->hasAnno = $this->hasExtraInfo = false;
	}


	protected function buildContent() {
		if ( $this->textId == 'random' ) {
			return $this->makeRandomContent();
		}
		if ( empty($this->textId) && empty($this->ttitle) ) {
			$this->addMessage('Не са посочени нито номер, нито заглавие на текст.', true);
			return '';
		}
		if ( !$this->initData() ) { return ''; }
		if ( $this->chunkId == 'raw' ) {
			return $this->makeRawContent();
		}
		if ( !is_numeric($this->chunkId) || $this->chunkId < 0 ) $this->chunkId = 1;
		$author = $this->makeAuthorLink($this->author);
		if ( !empty($author) ) { $author = "<small>$author</small><br />"; }
		$this->title = "$author<span class='text-title'>$this->ttitle</span>";
		if ( !empty($this->subtitle) ) {
			$this->title .= "<br /><small>$this->subtitle</small>";
		}

		$this->initJs();
		$editLink = $this->user->canExecute('editText')
			? $this->makeEditTextLink($this->textId, $this->chunkId,
				$this->user->canExecute('edit')) : '';
		$this->initExtraLinks(ltrim($editLink, '| '));
		$toc = $this->makeTOC();
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
		$res = $this->db->select($this->mainDbTable, array(), array('MIN(id)', 'MAX(id)'));
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
		$file = $GLOBALS['contentDirs']['text'].$this->textId;
		if ( !file_exists($file) ) {
			$this->addMessage("Няколко текста все още не са обърнати в новия формат на библиотеката. Този е един от тях и показването на суровия му текст не се поддържа.", true);
			#$this->addMessage("Няма текст с номер $this->textId.", true);
			return '';
		}
		$this->outencoding = $this->request->param(3, $this->outencoding);
		if ($this->outencoding == 'mik') {
			$this->outencoding = 'cp866';
			$encfilter = 'cp8662mik';
		} else {
			$encfilter = '';
		}
		if ( !$this->isValidEncoding($this->outencoding) ) {
			$this->addMessage("<strong>$this->outencoding</strong> не е валидно название на кодиране. Ето малко предложения: ".$this->makeEncodingSuggestions(), true);
			$this->outencoding = $this->inencoding;
			return;
		}
		header("Content-Type: text/plain; charset=$this->outencoding");
		header("Content-Language: $this->langCode");
		$this->author = str_replace(',', ', ', $this->author);
		$this->author = trim($this->author, ' ,');
		if ( !empty($this->author) ) { $this->encprint("|\t$this->author\n", $encfilter); }
		$title = $this->ttitle;
		if ( !empty($this->subtitle) ) {
			$title .= $this->subtitle{0} == '('
				? " $this->subtitle" : " ($this->subtitle)";
		}
		$this->encprint("|\t$title\n\n\n", $encfilter);
		$anno = $this->getAnnotation($this->textId);
		if ( !empty($anno) ) {
			$this->encprint("A>\n$anno\nA$\n\n", $encfilter);
		}
		if ( $this->outencoding == $this->inencoding ) {
			readfile($file);
		} else {
			$handle = fopen($file, 'r');
			if ($handle) {
				while ( !feof($handle) ) {
					$this->encprint( fgets($handle), $encfilter );
				}
				fclose($handle);
			}
		}
		$extra = '';
		if ( $this->orig_lang != $this->lang ) {
			$this->translator = trim(str_replace(',', ', ', $this->translator), ' ,');
			$extra .= "\tПревод от ". langName($this->orig_lang, false) .': ';
			$extra .= empty($this->translator)
				? 'Няма данни за преводача'
				: $this->translator . ( !empty($this->trans_year) ? ", $this->trans_year" : '');
			$extra .= "\n";
		}
		$extra .= $this->getExtraInfo($this->textId);
		if ( !empty($extra) ) {
			$this->encprint("\nI>\n$extra\nI$\n", $encfilter);
		}
		$this->outputDone = true;
	}


	protected function makeTextContent() {
		global $contentDirs, $curImgDir;
		$file = $contentDirs['text'].$this->textId;
		if ( file_exists($file) ) {
			$parser = new Sfb2XConverter($file, $this->rootd.'/content/img/'.$this->textId.'/');
			$parser->startpos = $this->fpos;
			$parser->maxlinecnt = $this->linecnt;
			if ($this->textType == 'playbook') {
				$parser->patterns['/#(\d+)/'] = '<a href="#h$1" title="Към част $1"><strong>$1</strong></a>';
			}
			$parser->parse();
			return '<p id="textstart" style="clear:both"></p>'.
				"\n<div class='$this->textType'>\n".$parser->text."\n</div>";
		}
		$this->addMessage("Текстът „<a href='$this->root/text/".
			"$this->textId/$this->chunkId'>$this->ttitle</a>“ е празен.", true);
		return '';
	}


	protected function makeInfo() {
		$extra = '';
		if ( !empty($this->series) ) {
			if ( strpos($this->series, ' (сборник') !== false ) {
				$start = $this->textType == 'intro' ? 'Предговор към' : 'Включено в';
				$ser = $start .' сборника „'.
					$this->makeSeriesLink($this->series, true) .'“';
			} elseif ( strpos($this->series, ' (книга)') !== false ) {
				$ser = 'Част от книгата „'.
					$this->makeSeriesLink($this->series, true) .'“';
// 			} elseif ( strpos($this->series, ' цикъл') !== false ) {
// 				$ser = $this->makeSeriesLink($this->series);
			} else {
				$ser = 'Поредица: '. $this->makeSeriesLink($this->series);
				if ( !empty($this->sernr) ) $ser .= " ($this->sernr)";
			}
			$extra .= "\n<li>$ser</li>";
		}
		if ( $this->orig_lang != $this->lang ) {
			if ( empty($this->orig_title) ) {
				$this->orig_title = "[не е въведено; <a href='$this->root/suggestOrigTitle/$this->textId/$this->chunkId'>помогнете ми</a> да го добавя]";
			} elseif ( !empty($this->orig_subtitle) ) {
				$this->orig_title .= ' ('. trim($this->orig_subtitle, '()') .')';
			}
			$extra .= "\n<li>Оригинално заглавие: <em>$this->orig_title</em>".
				( !empty($this->year)
					? ', <span title="Година на написване или първа публикация">'.
					($this->year) .
					'</span>' : '' ).
				'</li>';
			$extra .= "\n".'<li>Превод от '. langName($this->orig_lang, false) .': ';
			$extra .= empty($this->translator)
				? "[Няма данни за преводача; <a href='$this->root/suggestTranslator/$this->textId/$this->chunkId'>помогнете ми</a> да го добавя]"
				: $this->makeTranslatorLink($this->translator, 'first') .
					( !empty($this->trans_year) ? ", $this->trans_year" : '') .'</li>';
		}
		$extra .= "\n<li>Етикети: ". $this->makeLabelInfo() .'</li>';
		if ($this->isRead) {
			$extra .= "\n".'<li>Това произведение е отбелязано като прочетено.</li>';
		}
		$commCnt = $this->getReaderCommentCount();
		$extra .= "\n<li>";
		$extra .= $commCnt > 0 ? "Има <strong>$commCnt</strong>" : 'Няма';
		$readCmnts = $commCnt == 1 ? 'читателско мнение' : 'читателски мнения';
		$extra .= " <a href='$this->root/comment/$this->textId' title='Мнения от читатели на произведението'>$readCmnts за произведението</a>.</li>";
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
		$edit = !$this->user->canExecute('editTextLabels') ? ''
			: " &nbsp; [<a href='$this->root/editTextLabels/$this->textId/$this->chunkId' title='Възможност за промяна на етикетите на произведението'>промяна</a>]";
		$res = $this->db->query("SELECT name FROM /*p*/text_label h
			LEFT JOIN /*p*/label l ON (h.label = l.id)
			WHERE h.text=$this->textId");
		if ( $this->db->numRows($res) == 0 ) { return 'Няма'.$edit; }
		$o = '';
		while ( $row = $this->db->fetchRow($res) ) {
			$o .= ' '. $this->makeLabelLink($row[0]) .',';
		}
		return rtrim($o, ','). $edit;
	}


	protected function makeAnnotation() {
		global $contentDirs;
		$file = $contentDirs['text-anno'] . $this->textId;
		if ( $this->chunkId > 1 || !file_exists($file) ) { return ''; }
		$this->hasAnno = true;
		$parser = new Sfb2XConverter($file, $this->getImgDir());
		$parser->parse();
		return "<div id='annotation'>\n$parser->text</div>";
	}


	protected function makeExtraInfo() {
		if ( isset($this->extraInfo) ) return $this->extraInfo;
		$file = $GLOBALS['contentDirs']['text-info'] . $this->textId;
		if ( !file_exists($file) ) { return ''; }
		$this->hasExtraInfo = true;
		$parser = new Sfb2XConverter($file, $this->getImgDir());
		$parser->parse();
		$cover = $this->makeCoverImage();
		$this->extraInfo = <<<EOS

<fieldset class="infobox">
	<legend>Допълнителна информация <a href="#after-extrainfobox" class="non-graphic">(Прескачане на допълнителната информация)</a></legend>
$cover
$parser->text
</fieldset>
<p id="after-extrainfobox" class="non-graphic"><a name="after-extrainfobox"> </a></p>
EOS;
		return $this->extraInfo;
	}


	protected function makeCoverImage() {
		$covdir = $GLOBALS['contentDirs']['cover'];
		$bases = array($covdir . $this->textId);
		if ( !empty($this->cover) ) $bases[] = $covdir . $this->cover;
		$exts = array('.jpg', '.png');
		$coverFiles = cartesian_product($bases, $exts);
		$cover = '';
		foreach ($coverFiles as $file) {
			if ( file_exists( $file ) ) {
				$covurl = $this->rootd .'/'. $file;
				$cover = "<span style='float:right; margin:0 0 1em 1em'><a href='$covurl'><img src='$covurl' width='200' /></a></span>";
				break;
			}
		}
		return $cover;
	}


	protected function makeTOC() {
		$this->hasNext = false;
		$this->nextChunkId = 1;
		$this->prevlev = 0;
		$sel = array('name', 'nr', 'level');
		$key = array('text' => $this->textId);
		$q = $this->db->selectQ('header', $key, $sel, 'nr');
		$toc = $this->db->iterateOverResult($q, 'makeTOCItem', $this);
		if ( substr_count($toc, '<li>') < 2 ) { return ''; }
		$toc .= '</li>'.str_repeat("\n</ul>\n</li>", $this->prevlev-1)."\n</ul>";
		return <<<EOS
<div style="float:right"><a href="$this->root/$this->action/$this->textId/0">Показване на цялото произведение</a></div>
<div id="toc">
<div id="toctitle"><h2>Съдържание</h2> <a href="#after-toc" class="non-graphic">(Прескачане на съдържанието)</a></div>
$toc
</div>
<script type="text/javascript">
if (window.showTocToggle) {
	var tocShowText = "показване";
	var tocHideText = "скриване";
	showTocToggle();
	toggleToc();
}
</script>
<p id="after-toc" class="non-graphic"><a name="after-toc"> </a></p>

EOS;
	}


	public function makeTOCItem($dbrow) {
		extract($dbrow);
		if ( !$this->hasNext && $this->chunkId < $nr ) {
			$this->nextChunkId = $nr;
			$this->hasNext = true;
		}
		$toci = '';
		$title = $this->chunkId == $nr
			? "<strong>$name</strong>"
			: "<a href='$this->root/text/$this->textId/$nr#textstart'>$name</a>";
		if ($this->prevlev < $level) {
			$toci .= "\n<ul>";
		} elseif ($this->prevlev > $level) {
			$toci .= '</li>'.str_repeat("\n</ul>\n</li>", $this->prevlev - $level);
		} else $toci .= '</li>';
		$toci .= "\n<li>$title";
		$this->prevlev = $level;
		return $toci;
	}


	protected function makeEndMessage() {
		$markRead = $this->user->canExecute('markRead')
			? $this->makeMarkReadLink() : '';
		$endMsg = $this->hasNext && $this->chunkId > 0
			? "Към <a href=\"$this->root/text/$this->textId/".
				$this->nextChunkId.'#textstart">следващата част</a> &rarr;'
			: 'Край &nbsp; '. $markRead;
		return "\n".'<p id="text-end-msg">'.$endMsg.'</p>';
	}


	protected function makeMarkReadLink() {
		if ($this->isRead) return '';
		return "<a class='ok' href='$this->root/markRead/$this->textId'
			title='Отбелязване като прочетено'>Прочетено</a>";
	}


	protected function makeCopyright() {
		$o = '';
		if ($this->copy) {
			$authors = explode(',', $this->author);
			$years = explode(',', $this->ayears);
			if ( count($authors) > 1 ) {
				foreach ($authors as $i => $author) {
					$year = empty($years[$i]) ? $this->year : $years[$i];
					$o .= "\n\t<li>© $year " .
						$this->makeAuthorLink($author, 'first') . '</li>';
				}
			} else {
				$o .= $this->makeAuthorLink($this->author, 'first',
					"\n\t<li>© $this->year ", '</li>');
			}
		}
		if ( !empty($this->translator) ) {
			$lang = langName($this->orig_lang, false);
			$o .= $this->makeTranslatorLink($this->translator, 'first',
				"\n\t<li>© $this->trans_year ", ", превод от $lang</li>");
		}
		if ( empty($o) ) return '';
		// rm comma separators
		$o = str_replace('li>,', 'li>', $o);
		return <<<EOS

<fieldset id="copyright">
<legend>Авторско право</legend>
<ul>
$o
</ul>
</fieldset>
EOS;
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
			'onchange="javascript:setFontSize(this.value)"');
		$rawLink = $this->makeRawTextLink($this->textId);
		$rawLinkCp1251 = $this->makeRawTextLink($this->textId, 'Суров текст (win)', 'Преглед на суровия текст в кодиране „Windows-1251“', 'cp1251');
		$rawLinkCp866 = $this->makeRawTextLink($this->textId, 'Суров текст (dos)', 'Преглед на суровия текст в кодиране „IBM866“ — руска досовска кирилица', 'cp866');
		$rawLinkMik = $this->makeRawTextLink($this->textId, 'Суров текст (mik)', 'Преглед на суровия текст в нестандартно кодиране „MIK“ — българска досовска кирилица', 'mik');
		$dlLink = $this->makeDlLink($this->textId, $this->zsize, 'Суров текст (zip)');
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
		$q = "SELECT t.id textId, t.title ttitle, t.subtitle,
			t.trans_year, t.lang, t.orig_title, t.orig_subtitle,
			t.orig_lang, t.year, t.type textType, t.copy, t.zsize, t.sernr, t.cover,
			s.name series, r.user isRead,
			GROUP_CONCAT(DISTINCT a.name) author,
			GROUP_CONCAT(aof.year) ayears,
			GROUP_CONCAT(DISTINCT tr.name) translator
			FROM /*p*/text t
			LEFT JOIN /*p*/author_of aof ON t.id = aof.text
			LEFT JOIN /*p*/person a ON aof.author = a.id
			LEFT JOIN /*p*/translator_of tof ON t.id = tof.text
			LEFT JOIN /*p*/person tr ON tof.translator = tr.id
			LEFT JOIN /*p*/series s ON t.series = s.id
			LEFT JOIN /*p*/reader_of r ON (t.id = r.text AND r.user = {$this->user->id})";
		if ( empty($this->textId) || !is_numeric($this->textId) ) {
			$dbTextTitle = $this->db->escape($this->ttitle);
			$q .= " WHERE title = '$dbTextTitle'";
			$err = "със заглавие <strong>„{$this->ttitle}“</strong>";
		} else {
			$q .= " WHERE t.id = '$this->textId'";
			$err = "с номер <strong>{$this->textId}</strong>";
		}
		$q .= ' GROUP BY t.id LIMIT 1';
		$data = $this->db->fetchAssoc( $this->db->query($q) );
		if ( empty($data) ) {
			$this->addMessage("Не съществува текст $err.", true);
			return false;
		}
		extract2object($data, $this);
		if ( empty($this->year) ) { $this->year = ''; }
		$this->ayears = ltrim($this->ayears, '0,');
		if ( empty($this->year) && !empty($this->ayears) ) {
			$this->year = strtr($this->ayears, array(','=>', '));
		}
		if ( empty($this->trans_year) ) { $this->trans_year = ''; }

		$sel = array('fpos', 'linecnt');
		$key = array('text' => $this->textId, 'nr' => $this->chunkId);
		$res = $this->db->select('header', $key, $sel);
		$data = $this->db->fetchAssoc($res);
		if ( !empty($data) ) {
			extract2object($data, $this);
			$this->subtitle = str_replace('\n', '<br />', $this->subtitle);
		} else {
			$this->fpos = 0;
			$this->linecnt = 100000;
		}
		return true;
	}



	protected function getAnnotation($textId) {
		$file = $GLOBALS['contentDirs']['text-anno'] . $textId;
		if ( !file_exists($file) ) { return ''; }
		return file_get_contents($file);
	}


	protected function getExtraInfo($textId) {
		$file = $GLOBALS['contentDirs']['text-info'] . $textId;
		if ( !file_exists($file) ) { return ''; }
		return file_get_contents($file);
	}


	protected function getRandomTextId() {
		return rand($this->minTextId, $this->maxTextId);
	}


	protected function getReaderCommentCount($textId = NULL) {
		if ( empty($textId) ) $textId = $this->textId;
		$key = array('text' => $textId, '`show`' => 'true');
		return $this->db->getCount('comment', $key);
	}


	protected function getImgDir() {
		return $this->rootd.'/'.$GLOBALS['contentDirs']['img'].$this->textId.'/';
	}


	protected function makeEncodingSuggestions() {
		$encs = array('cp1251', 'windows-1251', 'cp866', 'ibm866', 'ibm855',
			'koi8-r', 'iso-ir-111', 'mik', 'utf-8');
		$l = '';
		foreach ($encs as $enc) {
			$l .= "\n<li><a href='$this->root/text/$this->textId/raw/$enc' title='Преглед на текста в кодиране „{$enc}“'>$enc</a></li>";
		}
		return "<ul>$l\n</ul>";
	}
}

?>
