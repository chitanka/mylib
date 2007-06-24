<?php
class TextPage extends Page {

	/** minimal text size for annotation */
	protected $minTextSizeForAnno = 50000;


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
			$this->addMessage('Не е посочен нито номер, нито заглавие на текст.', true);
			return '';
		}
		if ( !$this->initData() ) { return ''; }
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
			$this->addMessage("Няма текст с номер $this->textId.", true);
			return '';
		}
		$this->setOutEncoding($this->request->param(3));
		if ( !$this->isValidEncoding($this->outencoding) ) {
			$this->addMessage("<strong>$this->outencoding</strong> не е валидно название на кодиране. Ето малко предложения: ".$this->makeEncodingSuggestions(), true);
			$this->outencoding = $this->inencoding;
			return;
		}
		header("Content-Type: text/plain; charset=$this->outencoding");
		header("Content-Language: $this->langCode");
		if ( !empty($this->work->author_name) ) {
			$this->encprint("|\t".$this->work->author_name."\n");
		}
		$this->encprint($this->work->getTitleAsSfb() ."\n\n\n");
		$anno = $this->getAnnotation($this->textId);
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
			$this->getExtraInfo($this->textId) .
			"\n\n\tСвалено от „{$this->sitename}“ [$this->purl/text/$this->textId]";
		$extra = preg_replace('/\n\n+/', "\n\n", $extra);
		$this->encprint("\nI>\n$extra\nI$\n");
		$this->outputDone = true;
	}


	protected function makeTextContent() {
		global $contentDirs, $curImgDir;
		$file = $contentDirs['text'].$this->textId;
		if ( file_exists($file) ) {
			$parser = new Sfb2HTMLConverter($file, $this->rootd.'/content/img/'.$this->textId.'/');
			$parser->startpos = $this->fpos;
			$parser->maxlinecnt = $this->linecnt;
			if ($this->work->type == 'playbook') {
				// recognize section links
				$parser->patterns['/#(\d+)/'] = '<a href="#h$1" title="Към част $1"><strong>$1</strong></a>';
			}
			$parser->parse();
			return '<p id="textstart" style="clear:both"></p>'.
				"\n<div class='{$this->work->type}'>\n".$parser->text."\n</div>";
		}
		$this->addMessage("Текстът „<a href='$this->root/text/".
			"$this->textId/$this->chunkId'>$this->ttitle</a>“ е празен.", true);
		return '';
	}


	protected function makeInfo() {
		$extra = '';
		if ($this->work->collection) {
			$extra .= '<li>Автори: '.
				$this->makeAuthorLink($this->work->author_name). '</li>';
		}
		if ( !empty($this->work->series) ) {
			if ( strpos($this->work->series, ' (сборник') !== false ) {
				$start = $this->work->type == 'intro' ? 'Предговор към' : 'Включено в';
				$ser = $start .' сборника „'.
					$this->makeSeriesLink($this->work->series, true) .'“';
				if ( !empty($this->work->sernr) )
					$ser .= ' ('.$this->work->sernr.')';
			} elseif ( strpos($this->work->series, ' (книга)') !== false ) {
				$ser = 'Част от книгата „'.
					$this->makeSeriesLink($this->work->series, true) .'“';
// 			} elseif ( strpos($this->work->series, ' цикъл') !== false ) {
// 				$ser = $this->makeSeriesLink($this->work->series);
			} else {
				$ser = 'Поредица: '. $this->makeSeriesLink($this->work->series);
				if ( !empty($this->work->sernr) )
					$ser .= ' ('.$this->work->sernr.')';
			}
			$extra .= "\n<li>$ser</li>";
		}
		if ( $this->work->orig_lang == $this->work->lang ) {
			$extra .= "\n<li><span title='Година на написване или първа публикация'>Година</span>: ".
				$this->makeCustomYearView('orig', $this->work->getYear()) .'</li>';
		} else {
			if ( empty($this->work->orig_title) ) {
				$orig_title = "[не е въведено; <a href='$this->root/suggestData/origTitle/$this->textId/$this->chunkId'>помогнете ми</a> да го добавя]";
			} else {
				$orig_title = $this->work->orig_title;
				if ( !empty($this->work->orig_subtitle) ) {
					$orig_title .= ' ('. trim($this->work->orig_subtitle, '()') .')';
				}
			}
			$extra .= "\n<li>Оригинално заглавие: <em>$orig_title</em>".
				', <span title="Година на написване или първа публикация">'.
				$this->makeCustomYearView('orig', $this->work->getYear()) .'</span></li>';
			$lang = langName($this->work->orig_lang, false);
			if ( !empty($lang) ) $lang = ' от '.$lang;
			$extra .= "\n<li>Превод$lang: ";
			$extra .= empty($this->work->translator_name)
				? "[Няма данни за преводача; <a href='$this->root/suggestData/translator/$this->textId/$this->chunkId'>помогнете ми</a> да го добавя]"
				: $this->makeTranslatorLink($this->work->translator_name, 'first');
			$extra .= ', '.$this->makeCustomYearView('trans', $this->work->getTransYear()).'</li>';
		}
		$extra .= "\n<li>Етикети: ". $this->makeLabelInfo() .'</li>';
		if ($this->work->isRead) {
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
		if ( $this->chunkId > 1 || !file_exists($file) ) {
			if ($this->work->size < $this->minTextSizeForAnno) {
				return '';
			}
			$anno = "<p style='text-align:center'><a href='$this->root/suggestData/annotation/$this->textId'><strong>Предложете анотация на произведението!</strong></a></p>";
		} else {
			$this->hasAnno = true;
			$parser = new Sfb2HTMLConverter($file, $this->getImgDir());
			$parser->parse();
			$anno = $parser->text;
		}
		return "<div id='annotation'>\n$anno</div>";
	}


	protected function makeExtraInfo() {
		if ( isset($this->extraInfo) ) return $this->extraInfo;
		$file = $GLOBALS['contentDirs']['text-info'] . $this->textId;
		if ( !file_exists($file) ) { return ''; }
		$this->hasExtraInfo = true;
		$parser = new Sfb2HTMLConverter($file, $this->getImgDir());
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
		if ( !empty($this->work->cover) ) $bases[] = $covdir . $this->work->cover;
		$exts = array('.jpg', '.png');
		$coverFiles = cartesian_product($bases, $exts);
		$cover = '';
		foreach ($coverFiles as $file) {
			if ( file_exists( $file ) ) {
				$img = $this->makeCoverImageView($file);
				// search for more images of the form "TEXTID-DIGIT.EXT"
				for ($i = 2; /* infinite */; $i++) {
					$efile = strtr($file, array('.'=>"-$i."));
					if ( file_exists( $efile ) ) {
						$img .= ' '.$this->makeCoverImageView($efile);
					} else {
						break;
					}
				}
				$cover = "<span style='float:right; margin:0 0 1em 1em'>$img</span>";
				break;
			}
		}
		return $cover;
	}

	protected function makeCoverImageView($file) {
		$covurl = $this->rootd .'/'. $file;
		return "<a href='$covurl'>".$this->out->image($covurl, 'Корица', '', 'width="200"').'</a>';
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
<div id="fulltext-link"><a href="$this->root/$this->action/$this->textId/0">Показване на цялото произведение</a></div>
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
		if ($this->work->isRead) return '';
		return "<a class='ok' href='$this->root/markRead/$this->textId'
			title='Отбелязване като прочетено'>Прочетено</a>";
	}


	protected function makeCopyright() {
		$o = $this->work->getCopyright($this);
		if ( empty($o) ) return '';
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
		$res = $this->db->select('header', $key, $sel);
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


	protected function makeCustomYearView($type, $year, $yearAlt = 0, $year2 = 0) {
		$actions = array(
			'orig' => array('year', 'написване или първа публикация'),
			'trans' => array('transYear', 'превод')
		);
		$yearview = $this->makeYearView($year, $yearAlt, $year2);
		if ($yearview{0} == '?') {
			return "<a href='$this->root/suggestData/{$actions[$type][0]}/$this->textId/$this->chunkId' title='Даване на информация за година на {$actions[$type][1]}'>$yearview</a>";
		}
		return $yearview;
	}

}

?>
