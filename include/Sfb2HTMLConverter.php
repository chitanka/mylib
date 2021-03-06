<?php
class Sfb2HTMLConverter {

	const
		HEADER = '|',
		TITLE_1 = '>',
		TITLE_2 = '>>',
		TITLE_3 = '>>>',
		TITLE_4 = '>>>>',
		TITLE_5 = '>>>>>',
		ANNO_S = 'A>', ANNO_E = 'A$',
		INFO_S = 'I>', INFO_E = 'I$',
		DEDICATION_S = 'D>', DEDICATION_E = 'D$',
		EPIGRAPH_S = 'E>', EPIGRAPH_E = 'E$',
		LETTER_S = 'L>', LETTER_E = 'L$',
		SIGN_S = 'S>', SIGN_E = 'S$', SIGN_OL = 'S',
		NOTICE_S = 'N>', NOTICE_E = 'N$', NOTICE_OL = 'N',
		POEM_S = 'P>', POEM_E = 'P$',
		CITE_S = 'C>', CITE_E = 'C$',
		PREFORMATTED_S = 'F>', PREFORMATTED_E = 'F$', PREFORMATTED_OL = 'F',
		TABLE_S = 'T>', TABLE_E = 'T$', TABLE_HEADER = '#',
		TABLE_HCELL = '!', TABLE_CELL_SEP = '|',
		PARAGRAPH_OL = '',
		SUBHEADER = '#', SUBHEADER2 = '|',
		AUTHOR_OL = '@', PLACE_OL = '@@',
		SEPARATOR = '* * *', LINE = '--';

	protected static
		$objCount = 0;

	protected
		$blockStart = array(
			'epigraph' => '<blockquote class="epigraph">',
			'dedication' => '<blockquote class="dedication">',
			'letter' => '<blockquote class="letter">',
			'sign' => '<blockquote class="sign">',
			'notice' => '<div class="notice">',
			'anno' => '<fieldset class="annotation"><legend>Анотация</legend>',
			'info' => '<fieldset class="infobox"><legend>Допълнителна информация</legend>',
			'poem' => '<blockquote class="poem">',
			'cite' => '<blockquote class="cite">',
		),
		$blockEnd = array(
			'epigraph' => '</blockquote>',
			'dedication' => '</blockquote>',
			'letter' => '</blockquote>',
			'sign' => '</blockquote>',
			'notice' => '</div>',
			'anno' => '</fieldset>',
			'info' => '</fieldset>',
			'poem' => '</blockquote>',
			'cite' => '</blockquote>',
		),
		$titles = array(1 => 'h2', 'h3', 'h4', 'h5', 'h6'),
		$titMarks = array(1=>'>', '>>', '>>>', '>>>>', '>>>>>'),
		$debug = false, $i=0;

	public function __construct($file, $imgDir = 'img/') {
		self::$objCount++;
		$this->file = $file;
		$this->handle = fopen($this->file, 'r');
		$this->text = $this->footnotes = '';
		$this->curCont = &$this->text;
		$this->inFn = false; // in foot note state
		$this->autoNumNote = true; // auto number foot notes
		$this->curRef = $this->curFn = 0;
		$this->imgDir = $imgDir;
		$this->curHeader = 1;
		$this->startpos = 0;
		$this->linecnt = 0;
		$this->maxlinecnt = 100000;
		$this->hasNextLine = false;
		$this->acceptEmptyLine = true;
		$this->putLineId = false;
		$this->curTrClass = 'odd';
		$this->patterns = array(
		'/((?<=[\s([„>])__|^__)(.+)__(?![\w\d])/U' => '<strong>$2</strong>',
		'/((?<=[\s([„>])_|^_)(.+)_(?![\w\d])/U' => '<em>$2</em>',
		'/((?<=[\s([„>])-|^-)(.+)-(?![\w\dа-я])/U' => '<strike>$2</strike>',
		'/\[\[(.+)\|(.+)\]\]/U' => '<a href="$1" title="$1 — $2">$2</a>',
		'!(?<=[\s>])(http://[^])\s,;<]+)!' => '<a href="$1" title="$1">$1</a>',

		'/{img:([^}|]+)\|([^}]+)(\|([^}]+))?}/Ue' =>
			"'<a href=\"$this->imgDir$1\"><img src=\"$this->imgDir$1\" alt=\"$2\" title=\"$2\"'.('$3'==''?'':' class=\"float-$4\"').' /></a>'",
		'/{img:([^}]+)}/U' => '<a href="'.$this->imgDir.'$1" title="Преглед на картинката в отделен прозорец"><img src="'.$this->imgDir.'$1" alt="$1" /></a>',
		'/{img-thumb:([^}|]+)\|([^}]+)(\|([^}]+))?}/Ue' =>
			"'<div class=\"thumb\" '.('$3'==''?'':' style=\"width: $4px\"').'><a href=\"$this->imgDir$1\" title=\"Щракнете за увеличен размер\"><img src=\"{$this->imgDir}thumb/$1\" alt=\"$2\" /></a><p><a href=\"$this->imgDir$1\" title=\"Щракнете за увеличен размер\"><img src=\"{IMGDIR}viewmag.png\" /></a> $2</p></div>'",
		// foot notes
		'/(?<=[^\s(])(\*+)(\d*)/e' => "\$this->makeRef('$1', '$2')",
		);
		$this->replPairs = array(
			"\t" => '        ', // eight nbspaces
			'`' => '&#768;', // ударение
			'{sup}' => '<sup>', '{/sup}' => '</sup>',
			'{sub}' => '<sub>', '{/sub}' => '</sub>',
		);
		$this->lcmd = $this->ltext = '';
	}


	public function __destruct() {
		if ($this->handle) {
			fclose($this->handle);
		}
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
		echo $this->text .'<div class="footnotes">'. $this->footnotes .'</div>';
	}


	public function content($withNotes = false, $plainNotes = true) {
		return $this->text . ($withNotes ? $this->footnotes($plainNotes) : '');
	}

	public function footnotes($plain = true) {
		return $plain || empty($this->footnotes)
			? $this->footnotes
			: "<fieldset class='footnotes'>\n<legend>Бележки</legend>\n$this->footnotes</fieldset>";
	}

	public function addPattern($pattern, $repl) {
		$this->replPairs[$pattern] = $repl;
	}

	public function addRegExpPattern($pattern, $repl) {
		$this->patterns[$pattern] = $repl;
	}

	public function rmPattern($pattern) {
		unset($this->replPairs[$pattern]);
	}

	public function rmRegExpPattern($pattern) {
		unset($this->patterns[$pattern]);
	}

	// TODO catch infinite loops
	protected function nextLine() {
// 		if ($this->i++ > $this->maxlinecnt) {
// 			echo "Грешка при $this->file\n";
// 			dprbt();
// 			exit(-1);
// 		}
		if ($this->hasNextLine) {
			$this->hasNextLine = false;
			return $this->line;
		}
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
		#if ($this->debug) echo "$this->linecnt: $this->line\n";
		return $this->line;
	}


	protected function doText() {
		#if ($this->debug) echo "in doText: '$this->line'\n";
		switch ($this->lcmd) {
		case self::PARAGRAPH_OL: $this->doParagraph(); break;
		case self::TITLE_1: $this->doTitle(1); break;
		case self::TITLE_2: $this->doTitle(2); break;
		case self::TITLE_3: $this->doTitle(3); break;
		case self::TITLE_4: $this->doTitle(4); break;
		case self::TITLE_5: $this->doTitle(5); break;
		case self::DEDICATION_S: $this->doBlock('dedication', self::DEDICATION_E); break;
		case self::EPIGRAPH_S: $this->doBlock('epigraph', self::EPIGRAPH_E); break;
		case self::LETTER_S: $this->doBlock('letter', self::LETTER_E); break;
		case self::SIGN_S: $this->doBlock('sign', self::SIGN_E); break;
		case self::NOTICE_S: $this->doBlock('notice', self::NOTICE_E); break;
		case self::ANNO_S: $this->doBlock('anno', self::ANNO_E); break;
		case self::INFO_S: $this->doBlock('info', self::INFO_E); break;
		case self::POEM_S: $this->doPoem(); break;
		case self::CITE_S: $this->doCite(); break;
		case self::PREFORMATTED_S: $this->doPreformatted(); break;
		case self::TABLE_S: $this->doTable(); break;
		case self::SIGN_OL: $this->doSign(); break;
		case self::NOTICE_OL: $this->doNotice(); break;
		case self::PREFORMATTED_OL: $this->doPreformattedOneLine(); break;
		case self::SUBHEADER: $this->doSubheader(); break;
		case self::AUTHOR_OL: $this->doAuthor(); break;
		case self::PLACE_OL: $this->doPlace(); break;
		default: $this->save($this->ltext);
		}
	}

	// epigraph ((p | poem | cite | empty-line)*, text-author*)
	protected function doBlock($key, $end) {
		#if ($this->debug) echo "in doBlock($key, $end)\n";
		$this->simpleSave($this->blockStart[$key] . "\n");
		if ( $this->ltext != $this->lcmd ) {
			$this->doParagraph();
		}
		do {
			$this->nextLine();
			$this->inBlock($end);
		} while ( $this->lcmd != $end && !is_null($this->lcmd) );
		$this->lcmd = '';
		$this->simpleSave($this->blockEnd[$key] . "\n");
		$this->acceptEmptyLine = false;
	}


	protected function inBlock($end) {
		#if ($this->debug) echo "in inBlock()\n";
		switch ($this->lcmd) {
		case self::PARAGRAPH_OL: $this->doParagraph(); break;
		case self::POEM_S: $this->doPoem(); break;
		case self::CITE_S: $this->doCite(); break;
		case self::AUTHOR_OL: $this->doAuthor(); break;
		case self::SUBHEADER: $this->doSubheader(); break;
		case self::SUBHEADER2: $this->doSubheader(); break;
		case self::SIGN_S: $this->doBlock('sign', self::SIGN_E); break;
		case self::SIGN_OL: $this->doSign(); break;
		case self::NOTICE_S: $this->doBlock('notice', self::NOTICE_E); break;
		case self::NOTICE_OL: $this->doNotice(); break;
		case self::PREFORMATTED_OL: $this->doPreformattedOneLine(); break;
		case self::PLACE_OL: $this->doPlace(); break;
		case $end: break;
		default: $this->save($this->line);
		}
	}


	// TODO да подобря четимостта на проверките за начало съхраняване/край четене
	protected function doTitle($level) {
		#if ($this->debug) echo "in doTitle($level)\n";
		if ( $this->ltext{0} != '>' ) { // non-empty header
			$id = str_replace(array('%', '+'), '', urlencode($this->ltext));
			$this->simpleSave('<'.$this->titles[$level].' id="h'.$id.'">' . $this->wiki2html($this->ltext));
		}
		$this->nextLine();
		while ( $this->lcmd == $this->titMarks[$level] ) {
			$this->save($this->ltext{0} == '>' ? '' : $this->ltext, ' <br /> ');
			$this->nextLine();
		}
		$this->simpleSave('</'.$this->titles[$level].'>'."\n");
		$this->curHeader++;
		$this->acceptEmptyLine = false;
		$this->hasNextLine = true;
	}


	// poem (title?, epigraph*, stanza+, text-author*, date?)
	protected function doPoem() {
		#if ($this->debug) echo "in doPoem\n";
		$this->simpleSave($this->blockStart['poem'] . "\n");
		if ( $this->ltext != $this->lcmd ) {
			$this->doParagraph();
		}
		do {
			$this->nextLine();
			$this->inPoem();
		} while ( $this->lcmd != self::POEM_E && !is_null($this->lcmd) );
		$this->simpleSave($this->blockEnd['poem'] . "\n");
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
		case self::PLACE_OL: $this->doPlace(); break;
		default: $this->save($this->line);
		}
	}


	protected function doCite() {
		#if ($this->debug) echo "in doCite()\n";
		$this->simpleSave($this->blockStart['cite'] . "\n");
		if ( $this->ltext != $this->lcmd ) {
			$this->doParagraph();
		}
		do {
			$this->nextLine();
			$this->inCite();
		} while ( $this->lcmd != self::CITE_E && !is_null($this->lcmd) );
		$this->lcmd = '';
		$this->simpleSave($this->blockEnd['cite'] . "\n");
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
		case self::PREFORMATTED_S: $this->doPreformatted(); break;
		case self::PREFORMATTED_OL: $this->doPreformattedOneLine(); break;
		case self::SIGN_S: $this->doBlock('sign', self::SIGN_E); break;
		case self::SIGN_OL: $this->doSign(); break;
		case self::NOTICE_S: $this->doBlock('notice', self::NOTICE_E); break;
		case self::NOTICE_OL: $this->doNotice(); break;
		case self::CITE_S: $this->doCite(); break;
		case self::CITE_E: break;
		case self::PLACE_OL: $this->doPlace(); break;
		case self::TABLE_S: $this->doTable(); break;
		default: $this->save($this->line);
		}
	}


	protected function doPreformatted() {
		$this->simpleSave("<pre>\n");
		do {
			$this->nextLine();
			$this->inPreformatted();
		} while ( $this->lcmd != self::PREFORMATTED_E && !is_null($this->lcmd) );
		$this->simpleSave("</pre>\n");
		$this->acceptEmptyLine = false;
	}


	protected function inPreformatted() {
		switch ($this->lcmd) {
		case self::PREFORMATTED_E: break;
		default: $this->simpleSave($this->line."\n");
		}
	}


	protected function doPreformattedOneLine() {
		$this->simpleSave('<pre>'."\t" . $this->ltext . '</pre>'."\n");
	}


	protected function doTable() {
		$this->simpleSave('<table class="content">'."\n");
		$this->curTrClass = 'odd';
		do {
			$this->nextLine();
			$this->inTable();
		} while ( $this->lcmd != self::TABLE_E && !is_null($this->lcmd) );
		$this->simpleSave("</table>\n");
		$this->acceptEmptyLine = false;
	}

	protected function inTable() {
		$ctag = 'td';
		switch ($this->lcmd) {
		case self::TABLE_HEADER:
			$this->save($this->ltext, '<caption>', "</caption>\n"); break;
		case self::TABLE_E: break;
		case self::TABLE_HCELL:
			$ctag = 'th';
			$this->curTrClass = 'odd';
			// go to default
		default:
			$l = strtr($this->wiki2html($this->ltext),
				array(self::TABLE_CELL_SEP => "</$ctag><$ctag>"));
			$this->curTrClass = $this->curTrClass == 'odd' ? 'even' : 'odd';
			$this->simpleSave("<tr class='$this->curTrClass'><$ctag>$l</$ctag></tr>\n");
		}
	}


	// p (#PCDATA | strong | emphasis | style | a | strikethrough | sub | sup | code | image)*
	protected function doParagraph() {
		#if ($this->debug) echo "in doParagraph\n";
		if ( empty($this->ltext) ) {
			if ($this->acceptEmptyLine) $this->simpleSave('<p>&nbsp;</p>'."\n");
		} elseif ($this->ltext == self::SEPARATOR) {
			$this->simpleSave("<p class='separator'>$this->ltext</p>\n");
		} elseif (strpos($this->ltext, self::LINE) === 0) {
			$this->simpleSave("<hr />\n");
		} elseif ( preg_match('/\[\*+(\d*)( (.+)(\]?))?$/U', $this->ltext, $m)  ) {
			// here starts a foot note
			if ( empty($m[1]) || $this->curFn >= $m[1] ) {
				$this->curFn++;
			} else {
				$this->curFn = $m[1];
			}
			$rid = '-' . self::$objCount .'-'. $this->curFn;
			$back = "<a href='#_ref$rid' title='Обратно'>[$this->curFn]</a>";
			if ( empty($m[4]) ) { // no “]” at the end; more than one paragraph in the note
				$this->inFn = true;
				$this->curCont = &$this->footnotes;
				$pref = "<div id='_note$rid'><p>";
				$suf = '';
			} else {
				$pref = "<p id='_note$rid'>";
				$suf = "<a href='#_ref$rid' title='Обратно'>↑</a>";
			}
			$line = empty($m[3]) ? '' : $this->wiki2html($m[3]);
			$this->footnotes .= "$pref$back $line $suf</p>\n";
		} elseif ($this->inFn) { // we are in a foot note
			if ( preg_match('/(.*)\]$/', $this->ltext, $m) ) { // this is the end, my friend
				$this->inFn = false;
				$this->curCont = &$this->text;
				$line = $m[1];
				$rid = '-' . self::$objCount .'-'. $this->curFn;
				$suf = " <a href='#_ref$rid' title='Обратно'>↑</a></p>\n</div>\n";
			} else { // just another paragraph in the foot note
				$line = $this->ltext;
				$suf = '</p>';
			}
			$this->footnotes .= '<p>'. $this->wiki2html($line) . $suf;
		} elseif (strpos($this->ltext, '{img:') === 0) {
			$this->savePar($this->ltext, 'image');
		} else {
			$this->savePar($this->ltext);
		}
		$this->acceptEmptyLine = true;
	}


	protected function doAuthor() {
		$this->savePar($this->ltext, 'author');
		$this->acceptEmptyLine = true;
	}

	protected function doPlace() {
		$this->savePar($this->ltext, 'placeyear');
		$this->acceptEmptyLine = true;
	}

	protected function doSubheader() {
		$this->savePar($this->ltext, 'subheader');
		$this->acceptEmptyLine = true;
	}

	protected function doSign() {
		$this->savePar($this->ltext, 'sign');
		$this->acceptEmptyLine = true;
	}

	protected function doNotice() {
		$this->savePar($this->ltext, 'notice');
		$this->acceptEmptyLine = true;
	}

	protected function savePar($content, $class = '') {
		if ( !empty($class) ) $class = " class='$class'";
		$id = $this->putLineId ? " id='p$this->linecnt'" : '';
		$this->save($this->ltext, "<p$id$class>", '</p>'."\n");
	}

	protected function save($cont, $pref = '', $suf = '') {
		$this->curCont .= $pref . $this->wiki2html($cont) . $suf;
	}

	protected function simpleSave($cont) {
		$this->curCont .= $cont;
	}

	protected function wiki2html($s) {
		$s = preg_replace($this->kpatterns, $this->vpatterns, $s);
		$s = strtr($s, $this->replPairs);
		return $s;
	}

	protected function makeRef($stars, $num) {
		if ( empty($num) || $this->curRef >= $num ) {
			$this->curRef++;
		} else {
			$this->curRef = $num;
		}
		$id = '-' . self::$objCount .'-'. $this->curRef;
		return "<sup id='_ref$id' class='ref'><a href='#_note$id' title='Към бележката'>[$this->curRef]</a></sup>";
	}

}
