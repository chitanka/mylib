<?php
class Sfb2HTMLConverter {

	const
		TITLE_1 = '>',
		TITLE_2 = '>>',
		TITLE_3 = '>>>',
		TITLE_4 = '>>>>',
		TITLE_5 = '>>>>>',
		DEDICATION_S = 'D>', DEDICATION_E = 'D$',
		EPIGRAPH_S = 'E>', EPIGRAPH_E = 'E$',
		LETTER_S = 'L>', LETTER_E = 'L$',
		NOTICE_S = 'S>', NOTICE_E = 'S$', NOTICE_OL = 'S',
		POEM_S = 'P>', POEM_E = 'P$',
		CITE_S = 'C>', CITE_E = 'C$',
		PREFORMATTED_S = 'F>', PREFORMATTED_E = 'F$', PREFORMATTED_OL = 'F',
		TABLE_S = 'T>', TABLE_E = 'T$', TABLE_HEADER = '#',
		TABLE_HCELL = '!', TABLE_CELL_SEP = '|',
		PARAGRAPH_OL = '',
		SUBHEADER = '#', SUBHEADER2 = '|',
		AUTHOR_OL = '@',
		SEPARATOR = '* * *', LINE = '--';

	protected
		$titles = array(1 => 'h2', 'h3', 'h4', 'h5', 'h6'),
		$titMarks = array(1=>'>', '>>', '>>>', '>>>>', '>>>>>'),
		$debug = false;

	public function __construct($file, $imgDir = 'img/') {
		$this->handle = fopen($file, 'r');
		$this->text = '';
		$this->imgDir = $imgDir;
		$this->curHeader = 1;
		$this->startpos = 0;
		$this->linecnt = 0;
		$this->maxlinecnt = 100000;
		$this->hasNextLine = false;
		$this->acceptEmptyLine = true;
		$this->curTrClass = '';
		$this->patterns = array(
		'/((?<=[\s([„>])__|^__)(.+)__(?![\w\d])/U' => '<strong>$2</strong>',
		'/((?<=[\s([„>])_|^_)(.+)_(?![\w\d])/U' => '<em>$2</em>',
		'/((?<=[\s([„>])-|^-)(.+)-(?![\w\dа-я])/U' => '<strike>$2</strike>',
		'/\[\[(.+)\|(.+)\]\]/U' => '<a href="$1" title="$1 — $2">$2</a>',
		'!(?<=[\s>])(http://[^])\s,;<]+)!' => '<a href="$1" title="$1">$1</a>',

		'/{img:(.+)\|(.+)(\|(.+))?}/Ue' =>
			"'<img src=\"$this->imgDir$1\" alt=\"$2\" title=\"$2\"'.('$3'==''?'':' class=\"float-$4\"').' />'",
		'/{img:(.+)}/U' => '<img src="'.$this->imgDir.'$1" alt="$1" />',
		'/{img-thumb:(.+)\|(.+)}/U' =>
			"<div class='thumb'><a href='$this->imgDir$1' title='Щракнете за увеличен размер'><img src='{$this->imgDir}thumb/$1' alt='$2' /></a><p><a href='$this->imgDir$1' title='Щракнете за увеличен размер'><img src='{IMGDIR}viewmag.png' /></a> $2</p></div>",
		);
		$this->replPairs = array(
			"\t" => '        ', // eight nbspaces
			'`' => '&#768;', // ударение
		);
	}


	public function __destruct() {
		fclose($this->handle);
	}


	public function parse() {
		$this->kpatterns = array_keys($this->patterns);
		$this->vpatterns = array_values($this->patterns);
		fseek($this->handle, $this->startpos);
		while ( $this->nextLine() !== false ) {
			$this->doText();
		}
	}


	public function output() {
		echo $this->text;
	}


	protected function nextLine() {
		if ($this->hasNextLine) {
			$this->hasNextLine = false;
			return $this->line;
		}#echo "$this->linecnt: $this->line<hr/>";
		# с > се показва и следващото заглавие
		# с >= има някакъв проблем в края (напр. при blockquote)
		if ( ($this->linecnt >= $this->maxlinecnt || feof($this->handle) )
				/*&& !$this->hasNextLine*/ ) {
			$this->lcmd = $this->ltext = null;
			return false;
		}
		$this->linecnt++;
		$this->line = rtrim( fgets($this->handle) );
		$parts = explode("\t", $this->line, 2);
		$this->lcmd = $parts[0];
		$this->ltext = isset($parts[1]) ? $parts[1] : $this->line;
		return $this->line;
	}


	protected function doText() {
		#if ($this->debug) echo "in doText: '$this->line'\n";
		switch ($this->lcmd) {
		case self::DEDICATION_S: $this->doEpigraph('dedication', self::DEDICATION_E); break;
		case self::EPIGRAPH_S: $this->doEpigraph('epigraph', self::EPIGRAPH_E); break;
		case self::LETTER_S: $this->doEpigraph('letter', self::LETTER_E); break;
		case self::NOTICE_S: $this->doEpigraph('notice', self::NOTICE_E); break;
		case self::TITLE_1: $this->doTitle(1); break;
		case self::TITLE_2: $this->doTitle(2); break;
		case self::TITLE_3: $this->doTitle(3); break;
		case self::TITLE_4: $this->doTitle(4); break;
		case self::TITLE_5: $this->doTitle(5); break;
		case self::POEM_S: $this->doPoem(); break;
		case self::CITE_S: $this->doCite(); break;
		case self::PREFORMATTED_S: $this->doPreformatted(); break;
		case self::TABLE_S: $this->doTable(); break;
		case self::PARAGRAPH_OL: $this->doParagraph(); break;
		case self::NOTICE_OL: $this->doNotice(); break;
		case self::PREFORMATTED_OL: $this->doPreformattedOneLine(); break;
		case self::SUBHEADER: $this->doSubheader(); break;
		case self::AUTHOR_OL: $this->doAuthor(); break;
		default: $this->save($this->ltext);
		}
	}

	// epigraph ((p | poem | cite | empty-line)*, text-author*)
	protected function doEpigraph($class, $end) {
		#if ($this->debug) echo "in doEpigraph($class, $end)\n";
		$this->save('', "<blockquote class='$class'>\n");
		if ( $this->ltext != $this->lcmd ) {
			$this->doParagraph();
		}
		do {
			$this->nextLine();
			$this->inEpigraph($end);
		} while ( $this->lcmd != $end );
		$this->save('', "</blockquote>\n");
		$this->acceptEmptyLine = false;
	}


	protected function inEpigraph($end) {
		#if ($this->debug) echo "in inEpigraph()\n";
		switch ($this->lcmd) {
		case self::PARAGRAPH_OL: $this->doParagraph(); break;
		case self::POEM_S: $this->doPoem(); break;
		case self::CITE_S: $this->doCite(); break;
		case self::AUTHOR_OL: $this->doAuthor(); break;
		case self::SUBHEADER: $this->doSubheader(); break;
		case self::SUBHEADER2: $this->doSubheader(); break;
		case self::NOTICE_OL: $this->doNotice(); break;
		case self::PREFORMATTED_OL: $this->doPreformattedOneLine(); break;
		case $end: break;
		default: $this->save($this->line);
		}
	}


	// TODO да подобря четимостта на проверките за начало съхраняване/край четене
	protected function doTitle($level) {
		#if ($this->debug) echo "in doTitle($level)\n";
		if ( $this->ltext{0} != '>' ) {
			$id = str_replace(array('%', '+'), '', urlencode($this->ltext));
			$this->save($this->ltext, '<'.$this->titles[$level].' id="h'.$id.'">');
		}
		$this->nextLine();
		while ( $this->lcmd == $this->titMarks[$level] ) {
			$this->save($this->ltext, ' <br /> ');
			$this->nextLine();
		}
		$this->save('', '</'.$this->titles[$level].'>'."\n");
		$this->curHeader++;
		$this->acceptEmptyLine = false;
		$this->hasNextLine = true;
	}


	// poem (title?, epigraph*, stanza+, text-author*, date?)
	protected function doPoem() {
		#if ($this->debug) echo "in doPoem\n";
		$this->save('', "<blockquote class='poem'>\n");
		if ( $this->ltext != $this->lcmd ) {
			$this->doParagraph();
		}
		do {
			$this->nextLine();
			$this->inPoem();
		} while ( $this->lcmd != self::POEM_E );
		$this->save('', "</blockquote>\n");
		$this->acceptEmptyLine = false;
	}


	protected function inPoem() {
		#if ($this->debug) echo "in inPoem()\n";
		switch ($this->lcmd) {
		case self::PARAGRAPH_OL: $this->doParagraph(); break;
		case self::CITE_S: $this->doPoem(); break;
		case self::AUTHOR_OL: $this->doAuthor(); break;
		case self::SUBHEADER:
		case self::SUBHEADER2: $this->doSubheader(); break;
		case self::POEM_E: break;
		default: $this->save($this->line);
		}
	}


	protected function doCite() {
		#if ($this->debug) echo "in doCite\n";
		$this->save('', "<blockquote class='cite'>\n");
		if ( $this->ltext != $this->lcmd ) {
			$this->doParagraph();
		}
		do {
			$this->nextLine();
			$this->inCite();
		} while ( $this->lcmd != self::CITE_E );
		$this->save('', "</blockquote>\n");
		$this->acceptEmptyLine = false;
	}


	// cite ((p | poem | empty-line)*, text-author*)
	protected function inCite() {
		#if ($this->debug) echo "in inCite()\n";
		switch ($this->lcmd) {
		case self::PARAGRAPH_OL: $this->doParagraph(); break;
		case self::POEM_S: $this->doPoem(); break;
		case self::AUTHOR_OL: $this->doAuthor(); break;
		case self::SUBHEADER: $this->doSubheader(); break;
		case self::CITE_E: break;
		default: $this->save($this->line);
		}
	}


	protected function doPreformatted() {
		$this->save('', "<pre>\n");
		do {
			$this->nextLine();
			$this->inPreformatted();
		} while ( $this->lcmd != self::PREFORMATTED_E );
		$this->save('', "</pre>\n");
		$this->acceptEmptyLine = false;
	}


	protected function inPreformatted() {
		switch ($this->lcmd) {
		case self::PREFORMATTED_E: break;
		default: $this->save($this->line."\n");
		}
	}


	protected function doPreformattedOneLine() {
		$this->save($this->ltext, '<pre>'."\t", '</pre>'."\n");
	}


	protected function doTable() {
		$this->save('', "<table class='content' rules='all'>\n");
		do {
			$this->nextLine();
			$this->inTable();
		} while ( $this->lcmd != self::TABLE_E );
		$this->save('', "</table>\n");
		$this->acceptEmptyLine = false;
	}

	protected function inTable() {
		$ctag = 'td';
		switch ($this->lcmd) {
		case self::TABLE_HEADER: $this->save("<caption>$this->ltext</caption>\n"); break;
		case self::TABLE_E: break;
		case self::TABLE_HCELL:
			$ctag = 'th';
			$this->curTrClass = '';
			// go to default
		default:
			$l = strtr($this->ltext, array(self::TABLE_CELL_SEP => "</$ctag><$ctag>"));
			$this->curTrClass = $this->curTrClass == 'odd' ? 'even' : 'odd';
			$this->save("<tr class='$this->curTrClass'><$ctag>$l</$ctag></tr>\n");
		}
	}


	// p (#PCDATA | strong | emphasis | style | a | strikethrough | sub | sup | code | image)*
	protected function doParagraph() {
		#if ($this->debug) echo "in doParagraph\n";
		if ( empty($this->ltext) ) {
			if ($this->acceptEmptyLine) $this->save('', '<p><br /></p>'."\n");
		} elseif ($this->ltext == self::SEPARATOR) {
			$this->save($this->ltext, '<p class="separator">', "</p>\n");
		} elseif (strpos($this->ltext, self::LINE) === 0) {
			$this->save('', "<hr />\n");
		} else {
			$this->save($this->ltext, '<p>', "</p>\n");
		}
		$this->acceptEmptyLine = true;
	}


	protected function doAuthor() {
		$this->save($this->ltext, '<p class="author">', '</p>'."\n");
	}


	protected function doSubheader() {
		$this->save($this->ltext, '<p class="subheader">', '</p>'."\n");
	}


	protected function doNotice() {
		$this->save($this->ltext, '<p class="notice">', '</p>'."\n");
	}


	protected function save($cont, $pref = '', $suf = '') {
		$this->text .= $pref. $this->wiki2html($cont) .$suf;
	}


	protected function wiki2html($s) {
		$s = preg_replace($this->kpatterns, $this->vpatterns, $s);
		$s = strtr($s, $this->replPairs);
		return $s;
	}
}
?>
