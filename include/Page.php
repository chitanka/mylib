<?php

class Page {

	const
		FF_ACTION = 'action', FF_QUERY = 'q',
		FF_LIMIT = 'limit', FF_OFFSET = 'offset',
		FF_CQUESTION = 'captchaQuestion', FF_CQUESTION_T = 'captchaQuestionT',
		FF_CANSWER = 'captchaAnswer', FF_CTRIES = 'captchaTries';
	protected
		$action, $title, $head, $langCode, $outencoding, $contentType,
		$request, $user, $db, $content, $messages, $jsContent, $style,
		$scriptStart, $scriptEnd, $styleStart, $styleEnd,
		$fullContent, $outputLength, $allowCaching,
		/** extern javascripts */
		$scripts = array(),
		$maxCaptchaTries = 2, $defListLimit = 10, $maxListLimit = 50;


	public function __construct($action = '') {
		$this->request = Setup::request();
		$this->db = Setup::db();
		$this->user = Setup::user();
		$this->skin = Setup::skin();
		$this->out = Setup::outputMaker();
		$this->langCode = Setup::setting('lang_code');
		$this->out->inencoding = $this->inencoding = Setup::IN_ENCODING;
		$this->doIconv = true;
		$this->allowCaching = true;
		$this->encfilter = '';
		$this->setOutEncoding($this->request->outputEncoding);
		$this->root = Setup::setting('webroot');
		$this->rootd = Setup::setting('docroot');
		$this->forum_root = Setup::setting('forum_root');
		$this->sitename = Setup::setting('sitename');
		$this->purl = Setup::setting('purl');
		$this->infoSrcs = Setup::setting('info');

		$this->action = $action;
		$this->messages = $this->content = $this->fullContent =
		$this->style = $this->jsContent = $this->extra = '';
		$this->contentType = 'text/html';

		$httpAccept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
		if ( strpos($httpAccept, 'application/xhtml+xml') !== false ) {
			#$this->contentType = 'application/xhtml+xml';
			$this->scriptStart = '<script type="text/javascript">//<![CDATA[';
			$this->scriptEnd = '//]]></script>';
			$this->styleStart = '<style type="text/css">';
			$this->styleEnd = '</style>';
		} else {
			$this->scriptStart = '<script type="text/javascript"><!--';
			$this->scriptEnd = '//--></script>';
			$this->styleStart = '<style type="text/css"><!--';
			$this->styleEnd = '--></style>';
		}

		$this->elapsedTime = 0;
		$this->outputDone = false;
		$this->title = $this->sitename;
		$this->time = time();
	}


	/**
		Generate page content according to submission type (POST or GET).
	*/
	public function execute() {
		$this->content = $this->request->wasPosted()
			? $this->processSubmission() : $this->buildContent();
		return $this->content;
	}


	public function title() {
		return $this->title;
	}

	public function messages() {
		return $this->messages;
	}

	public function content() {
		return $this->content;
	}

	public function get($field) {
		return isset($this->$field) ? $this->$field : null;
	}

	public function set($field, $value) {
		if ( isset($this->$field) ) {
			$this->$field = $value;
		}
	}

	public function setAction($action) {
		$this->action = $action;
	}

	public function setOutEncoding($enc) {
		if ( empty($enc) ) {
			return;
		}
		$enc = strtolower($enc);
		$this->outencoding = $enc;
		switch ($enc) {
		case 'mik': // non-standard BG DOS encoding
			$this->outencoding = 'cp866';
			$this->encfilter = 'cp8662mik';
			break;
		case 'iso-8859-1': case 'cp1252': case 'windows-1252':
			$this->doIconv = false;
			$this->encfilter = 'cyr2lat';
			break;
		}
		$this->out->outencoding = $this->outencoding;
	}

	public function setFields($data) {
		foreach ((array) $data as $field => $value) {
			$this->$field = $value;
		}
	}

	/**
		@param $message
		@param $isError
	*/
	public function addMessage($message, $isError = false) {
		$class = $isError ? ' class="error"' : '';
		$this->messages .= "<p$class>$message</p>\n";
	}

	public function addContent($content) {
		$this->content .= $content;
	}

	public function addStyle($style) {
		$this->style .= $style;
	}

	public function addJs($jsContent) {
		$this->jsContent .= $jsContent;
	}

	public function addExtraLinks($extra) {
		$this->extra .= $extra;
	}

	public function addHeadContent($content) {
		$this->head .= $content;
	}

	public function addRssLink($title = null, $action = null) {
		fillOnEmpty($title, $this->title());
		fillOnEmpty($action, $this->action);
		$params = array(self::FF_ACTION=>'feed', 'obj' => $action);
		$url = $this->out->internUrl($params, 2);
		$feedlink = <<<EOS
	<link rel="alternate" type="application/rss+xml" title="RSS 2.0 — $title" href="$url" />
EOS;
		$this->addHeadContent($feedlink);
	}

	public function addScript($file) {
		$this->scripts[] = $file;
	}

	public function allowCaching() {
		return $this->allowCaching;
	}

	/**
		Output page content.

		@param $elapsedTime How much time took the page generation (in seconds).
	*/
	public function output($elapsedTime) {
		if ( $this->outputDone ) { // already outputted
			return;
		}
		if ( empty($this->fullContent) ) {
			$this->makeFullContent($elapsedTime);
		}
		$this->addTemplates();
		$this->fullContent = expandTemplates($this->fullContent);
		$this->fullContent = $this->encprint($this->fullContent, true);
		$this->outputLength = strlen($this->fullContent);
		if ( !headers_sent() ) {
			$this->sendCommonHeaders();
			header('Content-Style-Type: text/css');
			header('Content-Script-Type: text/javascript');
			header('Content-Length: '. $this->outputLength);
		}
		print $this->fullContent;
	}


	public function sendCommonHeaders() {
		header("Content-Type: $this->contentType; charset=$this->outencoding");
		header("Content-Language: $this->langCode");
	}

	public function isValidEncoding($enc) {
		return @iconv($this->inencoding, $enc, '') !== false;
	}


	/**
		Build full page content.
		@return string
	*/
	public function makeFullContent($elapsedTime = NULL) {
		if ( !empty($this->scripts) ) {
			foreach ($this->scripts as $script) {
				$this->addHeadContent( "\n\t". $this->out->scriptInclude($script) );
			}
		}
		$nav = $this->makeNavigationBar();
		$pageTitle = strtr($this->title(), array('<br />'=>' — '));
		$pageTitle = strip_tags($pageTitle);
		$cssArgs = array(self::FF_ACTION=>'css');
		$cssMainArgs = $cssArgs + array('f' => 'main',
			'o' => $this->user->option('skin').'-'.$this->user->option('nav'));
		$cssMain = htmlspecialchars($this->out->internUrl($cssMainArgs, 2));
		$cssPrint = htmlspecialchars($this->out->internUrl($cssArgs + array('f' => 'print'), 2));
		$cssNonav = htmlspecialchars($this->out->internUrl($cssArgs + array('f' => 'nonav'), 2));
		$cacheLink = $this->makeCacheLink();
		$elapsedTimeMsg = !empty($elapsedTime)
			? "<!-- Страницата беше създадена за $elapsedTime секунди. -->"
			: '';
		$this->style = !empty($this->style)
			? "\t$this->styleStart\n$this->style\n\t$this->styleEnd" : '';
		$this->jsContent = !empty($this->jsContent)
			? "\t$this->scriptStart\n$this->jsContent\n\t$this->scriptEnd" : '';
		$this->messages = !empty($this->messages)
			? '<div id="messages">'.$this->messages.'</div>' : '';
		$xmlPI = '<?xml version="1.0" encoding="'.$this->outencoding.'"?>';
		$req = strtr($this->request->requestUri(), array($this->root => ''));
		$req = preg_replace('/&submitButton=[^&]+/', '', $req);
		$aboutL = $this->out->internLink('За '.$this->sitename, array(self::FF_ACTION=>'about'), 1);
		$rulesL = $this->out->internLink('Правила', array(self::FF_ACTION=>'rules'), 1);
		$purl = $this->out->link($this->purl . $req,
			strtr($this->purl . urldecode($req), ' ', '+'),
			'Постоянен адрес на страницата');
		$appLink = $this->out->link('http://sourceforge.net/projects/mylib/', APP_NAME);
		$this->fullContent = <<<EOS
$xmlPI
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="$this->langCode" lang="$this->langCode">
<!--
	Съхранено от „Моята библиотека“ ($this->purl)
-->
<head>
	<meta http-equiv="Content-Type" content="$this->contentType; charset=$this->outencoding" />
	<meta http-equiv="Content-Language" content="$this->langCode" />
	<meta http-equiv="Content-Style-Type" content="text/css" />
	<meta http-equiv="Content-Script-Type" content="text/javascript" />

	<title>$pageTitle — $this->sitename</title>

	<link rel="stylesheet" type="text/css" href="$cssMain" title="Основен изглед" />
	<link rel="stylesheet" type="text/css" media="print" href="$cssPrint" />
	<link rel="alternate stylesheet" type="text/css" href="$cssMain" title="Без навигация" />
	<link rel="alternate stylesheet" type="text/css" href="$cssNonav" title="Без навигация" />
	<link rel="home" href="$this->root" title="Начална страница" />
	<link rel="icon" href="$this->rootd/img/favicon.png" type="image/png" />
	<link rel="shortcut icon" href="$this->rootd/img/favicon.png" type="image/png" />
$this->scriptStart
	var ml_webroot = "{ROOT}";
	var ml_docroot = "{DOCROOT}";
	var ml_imgdir = "{IMGDIR}";
	var ml_sitename = "{SITENAME}";
$this->scriptEnd
	<script type="text/javascript" src="$this->rootd/script/main.js"></script>
$this->head
$this->style
$this->jsContent
</head>
<body>
<p id="jump-to" class="non-graphic">Направо към
<a href="#nav-main">навигационните връзки</a>,
<a href="#personal">личните инструменти</a> или
<a href="#search">формуляра за търсене</a>.</p>

<div id="main-content-wrapper">
<div id="main-content"><a name="main-content"> </a>
<h1>$this->title</h1>
$this->messages
$this->content

<div class="extra purl">Постоянен адрес на страницата: $purl</div>

</div><!-- край на #main-content -->
</div>

$nav

<div id="footer">
<ul>
	<li>$aboutL</li>
	<li>$rulesL</li>
	<li>{SITENAME} се задвижва от $appLink.</li>
</ul>
</div>

<p class="non-graphic">Това е краят на страницата.
Можете да се върнете в <a href="#jump-to">началото й</a> или да посетите <a href="$this->root">началната страница</a>.</p>

$this->scriptStart
if (window.runOnloadHook) runOnloadHook();
$this->scriptEnd
$cacheLink
$elapsedTimeMsg
</body>
</html>
EOS;
		unset($this->content); // free some memory
		/*if ($this->outencoding != $this->inencoding) {
			$o = iconv($this->inencoding,
				$this->outencoding.'//TRANSLIT', $o);
		}*/
		return $this->fullContent;
	}


	/**
		Process POST Forms if there are any.
		Override this function if your page contains POST Forms.
	*/
	protected function processSubmission() {
		return $this->buildContent();
	}

	/**
		Create page content.
		Override this function to include content in your page.
	*/
	protected function buildContent() {
		return '';
	}


	protected function addTemplates() {
		addTemplate('ROOT', $this->root);
		addTemplate('DOCROOT', $this->rootd.'/');
		addTemplate('IMGDIR', $this->skin->imageDir());
		$faction = $this->out->internUrl( array(self::FF_ACTION => $this->action) );
		addTemplate('FACTION', expandTemplates($faction));
		// hidden field for the action in GET forms
		$hidact = $this->out->hasPathInfo()
			? '' // superfluous if there is path info (done with FACTION)
			: $this->out->hiddenField(self::FF_ACTION, $this->action);
		addTemplate('HIDDEN_ACTION', $hidact);
		addTemplate('SITENAME', $this->sitename);
	}


	protected function makeNavigationBar() {
		$startwith = isset($this->startwith) ? stripslashes($this->startwith) : '';
		$startwith = trim( strtr($startwith, '%', ' ') );
		$startwith = preg_replace('/  +/', ' ', $startwith);
		$options = array('author'=>'Автор', 'translator'=>'Преводач',
			'title'=>'Заглавие', 'series'=>'Поредица', 'label'=>'Етикет');
		$action = $this->out->selectBox(self::FF_ACTION, '', $options, $this->action);
		$persTools = $this->makePersonalTools();
		$links = array(
'Навигация' => array(
	array('author', 'Автори', 'Начална страница за преглед на авторите'),
	array('translator', 'Преводачи', 'Начална страница за преглед на преводачите'),
	array('title', 'Заглавия', 'Начална страница за преглед на заглавията'),
	array(array('text', 'textId'=>'random', '_pos'=>2), 'Случайно заглавие', 'Показване на случайно заглавие'),
	array('series', 'Поредици', 'Начална страница за преглед на поредиците'),
	array('label', 'Етикети', 'Начална страница за преглед на етикетите'),
	array('news', 'Новини', 'Новини относно сайта'),
	array('history', 'История', 'Списък на произведенията по месец на добавяне'),
	array('work', 'Сканиране', 'Списък на произведения, подготвящи се за добавяне'),
	array('comment', 'Читател. мнения', 'Читателски коментари за произведенията'),
	array('liternews', 'Литер. новини', 'Новини, свързани с литературата. Добавят се от потребителите.'),
	array('/'.$this->forum_root, 'Форум', 'Форумната система на сайта'),
	array('statistics', 'Статистика', ''),
	array('links', 'Връзки', 'Връзки към други полезни места в мрежата'),
	array('feedback', 'Обратна връзка', 'Връзка с хората, отговарящи за библиотеката'),
),
		);
		$navmenu = '';
		$reqUri = $this->request->requestUri();
		foreach ($links as $section => $slinks) {
			$navmenu .= "\n<dt>$section</dt>";
			foreach ($slinks as $linkdata) {
				list($link, $selected) = $this->makeMenuLink($linkdata);
				$sel = $selected ? ' class="selected"' : '';
				$navmenu .= "\n\t<dd$sel>$link</dd>";
			}
		}
		$prefix = $this->out->hiddenField('prefix', '%');
		$sortby = $this->out->hiddenField('sortby', 'first');
		$mode = $this->out->hiddenField('mode', 'simple');
		$q = $this->out->textField(self::FF_QUERY, '', $startwith, 14, 40, 0,
			'Клавиш за достъп — Т; натиснете <Enter>, за да започнете търсенето',
			array('accesskey'=>'т'));
		$alabel = $this->out->label('по:', self::FF_ACTION, '',
			array('id'=>'label-'. self::FF_ACTION));
		$qlabel = $this->out->label('на:', self::FF_QUERY, '',
			array('id'=>'label-'. self::FF_QUERY, 'style'=>'display:none'));
		$submit = $this->out->submitButton('Показване',
			'Показване на резултатите от търсенето', 0, false, array('id'=>'search-go'));
		$helpus_link = $this->out->internLink('<strong>Сканирайте книга!</strong>', 'work');
		return <<<EOS
<div id="navigation"><a name="navigation"> </a>
<div id="logo"><a href="$this->root" title="Към началната страница">$this->sitename</a></div>

$persTools

<dl id="nav-main" title="Това са връзки, улесняващи разхождането из сайта">$navmenu
</dl>
<dl id="search" title="Претърсване на библиотеката">
<dt>Търсене</dt>
<dd>
<form action="$this->root" method="get">
<div><a name="search"> </a>
	$alabel
	$action
	$qlabel
	$q
	$submit
	$prefix
	$sortby
	$mode
</div>
</form>
</dd>
</dl>

<dl id="nav-extra">
$this->extra
<dt>Полезно</dt>
	<dd>» <a href="http://bg.wikipedia.org/" title="Уикипедия е свободна и безплатна енциклопедия, която непрекъснато се обогатява и обновява от читателите си">Уикипедия</a></dd>
	<dd>» <a href="http://bgf.zavinagi.org/" title="БГ-Фантастика е свободна енциклопедия, посветена на българската фантастика и сътворявана от любителите й">БГ-Фантастика</a></dd>
	<dd>» <a href="http://bg.wiktionary.org/" title="Уикиречник е свободен и безплатен речник, който се обогатява и обновява от читателите си">Уикиречник</a></dd>
	<dd>» <a href="http://www.getfirefox.com/" title="Мозила Файърфокс е свободен и безплатен графичен уеб браузър, достъпен за множество платформи">Файърфокс</a></dd>
</dl>
<div id="searchhelpbox" style="text-align:center">
<p><img src="{IMGDIR}helpcenter.png" alt="Помощ" title="Спасителен пояс" /></p>
<p>Желаете да помогнете?</p>
<p>$helpus_link</p>
</div>
<div class="propaganda">
<a href="http://bg.wikipedia.org/wiki/WP:100000"><img src="$this->rootd/img/wikipedia-banner-100000-138x115.jpg" alt="Уикипедия 100 000" width="138" title="Включете се в инициативата Уикипедия 100 000!" /></a>
</div>
<div class="propaganda">
<a href="http://protest.bloghub.org" style="margin-bottom:0.2ex"><img src="$this->rootd/img/protestlogo7um.138px.jpg" alt="Труд нападна сляп!" width="138" title="Протест срещу отношението на КК ТРУД и други издателства към електронните библиотеки" /></a>
</div>
</div>

EOS;
	}


	protected function makePersonalTools() {
		if ( $this->user->isAnon() ) {
			$links = array(
				array('settings', 'Настройки', 'Потребителски настройки'),
				array('register', 'Регистрация', 'Регистриране в '.$this->sitename),
				array('login', 'Вход', 'Влизане в '.$this->sitename),
			);
		} else {
			$links = array(
				array(array('user', 'username'=>$this->user->username,
					'_pos'=>2),
					$this->user->username, 'Лична страница'),
				array('settings', 'Настройки', 'Потребителски настройки'),
				array('logout', 'Изход', 'Излизане от '.$this->sitename),
			);
		}
		$l = '';
		foreach ($links as $linkdata) {
			list($link, $selected) = $this->makeMenuLink($linkdata);
			$sel = $selected ? ' class="selected"' : '';
			$l .= "\n\t<dd$sel>$link</dd>";
		}
		return <<<EOS
<dl id="personal" title="Лични инструменти">
<dt>Лични инструменти</dt>$l
</dl>
EOS;
	}


	/**
		Generate a menu link.

		@param $data Associative array
		@return An array with two elements: the link and a boolean flag telling if
			this link corressponds to the currently requested page
	*/
	protected function makeMenuLink($data) {
		list($urlparams, $text, $title) = $data;
		if ( is_string($urlparams) ) {
			if ( $urlparams{0} == '/' ) { // absolute URL, use almost as is
				return array(
					$this->out->link(substr($urlparams, 1), $text, $title),
					false);
			}
			$urlparams = array(self::FF_ACTION => $urlparams);
		} else if ( isset($urlparams[0]) ) {
			$action = $urlparams[0];
			unset($urlparams[0]);
			$urlparams = array(self::FF_ACTION => $action) + $urlparams;
		}
		if ( isset($urlparams['_pos']) ) {
			$ignorePos = $urlparams['_pos'];
			unset($urlparams['_pos']);
		} else {
			$ignorePos = 1;
		}
		return array(
			$this->out->internLink($text, $urlparams, $ignorePos, $title),
			$this->request->isCurrentRequest($urlparams));
	}


	protected function encprint($s, $return = false) {
		if ($this->outencoding != $this->inencoding && $this->doIconv) {
			$s = iconv($this->inencoding, $this->outencoding.'//TRANSLIT', $s);
		}
		if ( !empty($this->encfilter) && function_exists($this->encfilter) ) {
			$s = call_user_func($this->encfilter, $s);
		}
		if ($return) {
			return $s;
		}
		$this->fullContent .= $s;
		return print $s;
	}


	/**
		Redirect to another page.
	*/
	protected function redirect($action = '') {
		if ( empty($action) ) {
			$action = PageManager::defaultPage();
		}
		$newPage = PageManager::executePage($action);
		$this->copyPage($newPage);
		return $this->content;
	}


	protected function copyPage($page) {
		$fields = array('title', 'content', 'fullContent', 'style', 'jsContent', 'extra', 'user');
		$this->messages .= $page->messages;
		foreach ($fields as $field) {
			$this->$field = $page->$field;
		}
	}


	/**
		Returns first char of a string, which may be build from up to 3 Bytes
		(taken from MediaWiki).
	*/
	protected function firstChar($s) {
		preg_match( '/^([\x00-\x7f]|[\xc0-\xdf][\x80-\xbf]|' .
			'[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xf7][\x80-\xbf]{3})/', $s, $matches);
		return isset( $matches[1] ) ? $matches[1] : '';
	}


	/**
		@param $textId Text ID
		@param $zfsize Zip file size in bytes
	*/
	protected function makeDlLink($textId, $zfsize, $ltext = NULL) {
		$zfsize = int_b2k($zfsize);
		$ltext = empty($ltext) ? "$zfsize&nbsp;KiB" : htmlspecialchars($ltext);
		$p = array(self::FF_ACTION => 'download', 'textId' => $textId);
		$title = "Сваляне във формат ZIP, $zfsize ".
			chooseGrammNumber($zfsize, 'кибибайт', 'кибибайта');
		$attrs = array('class' => 'download');
		return $this->out->internLink($ltext, $p, 2, $title, $attrs);
	}


	protected function makeMwLink($term, $src = 'w', $extended = true, $pref='', $suf='') {
		$term = rtrim($term, ',');
		if ( empty($term) ) {
			return '';
		}
		$url = strtr($this->infoSrcs[$src],
			array('$1'=>"'.urlencode(strtr('$1', ' ', '_')).'"));
		$title = 'Информация за $1 в '.$this->infoSrcs[$src.'_name'];
		$text = $extended ? $title : '$1';
		return ltrim( preg_replace('/([^,]+)/e',
			"' $pref<a href=\"$url\" title=\"$title\">$text</a>$suf'", $term) );
	}


	protected function makeTextLink($data) {
		extract($data);
		$class = empty($reader) ? 'unread' : 'read';
		$ksize = int_b2k($size);
		$etitle = workType($type) .', '. $ksize . ' '.
			chooseGrammNumber($ksize, 'кибибайт', 'кибибайта');
		if ( ($this->time - $datestamp) < 2592000 ) { // 30 days limit
			$class .= ' new';
			$etitle .= ' — ново произведение';
		}
		if ( !empty($datestamp) ) {
			$etitle .= ', добавено на '. humanDate($date);
		}
		$attrs = array('class' => $class, 'title' => $etitle);
		return $this->makeSimpleTextLink($title, $textId, 1, '', $attrs);
	}


	protected function makeSimpleTextLink($title, $textId, $chunkId = 1,
			$linktext = '', $attrs = array()) {
		$p = array(self::FF_ACTION=>'text', 'textId'=>$textId);
		if ($chunkId != 1) {
			$p['chunkId'] = $chunkId;
		}
		if ( empty($linktext) ) {
			$linktext = '<em>'. $title .'</em>';
		}
		if ( !empty($attrs['title']) ) {
			$etitle = $attrs['title'];
		} else {
			$etitle = empty($title) ? '' : "Към произведението „{$title}“";
		}
		return $this->out->internLink($linktext, $p, count($p), $etitle, $attrs);
	}


	protected function makeRawTextLink($textId, $ltext = NULL, $title = NULL, $enc = NULL) {
		$ltext = empty($ltext) ? 'Суров текст' : htmlspecialchars($ltext);
		$title = empty($title) ? 'Към суровия текст на произведението' : htmlspecialchars($title);
		$p = array(self::FF_ACTION=>'text', 'textId'=>$textId, 'chunkId'=>'raw');
		if ( !empty($enc) ) $p['enc'] = $enc;
		return $this->out->internLink($ltext, $p, 3, $title);
	}


	public function makeAuthorLink($name, $sortby='first', $pref='', $suf='',
			$query=array()) {
		$name = rtrim($name, ',');
		if ( empty($name) ) {
			return '';
		}
		settype($query, 'array');
		$o = '';
		foreach ( explode(',', $name) as $lname ) {
			$text = empty($sortby) ? 'Произведения'
				: $this->formatPersonName($lname, $sortby);
			$p = array(self::FF_ACTION=>'author', 'q'=> trim($lname));
			$link = $this->out->internLink($text, $p + $query, 2);
			$o .= ', ' . $pref . $link . $suf;
		}
		return substr($o, 2);
	}


	public function makeFromAuthorSuffix($fields) {
		if ( is_string($fields) ) {
			$author = $fields;
		} else {
			extract($fields);
		}
		if ( (!isset($collection) || $collection == 'false' || $collection == false)
				&& isset($author) && trim($author, ', ') != '' ) {
			return ' от '.$this->makeAuthorLink($author);
		}
		return '';
	}


	public function makeTranslatorLink($name, $sortby='first', $pref='', $suf='',
			$query=array()) {
		settype($query, 'array');
		$o = '';
		foreach ( explode(',', $name) as $lname ) {
			$text = empty($sortby) ? 'Преводи' : $this->formatPersonName($lname, $sortby);
			$p = array(self::FF_ACTION=>'translator', 'q'=>trim($lname));
			$link = $this->out->internLink($text, $p + $query, 2);
			$o .= ', ' . $pref . $link . $suf;
		}
		return substr($o, 2);
	}


	protected function makeInfoLink($name, $extended=true, $pref='', $suf='',
			$query=array()) {
		settype($query, 'array');
		$o = '';
		foreach ( explode(',', $name) as $lname ) {
			$title = "Информация за $lname";
			$text = $extended ? $title : $lname;
			$p = array(self::FF_ACTION=>'info', 'term'=>$lname);
			$link = $this->out->internLink($text, $p + $query, 2, $title);
			$o .= ', ' . $pref . $link . $suf;
		}
		return substr($o, 2);
	}


	protected function makeSeriesLink($name, $pref='', $suf='', $query=array()) {
		settype($query, 'array');
		$p = array(self::FF_ACTION=>'series', 'q'=>$name);
		return $this->out->internLink("$pref<em>$name</em>$suf", $p + $query, 2);
	}

	protected function makeBookLink($name, $pref='', $suf='', $query=array()) {
		settype($query, 'array');
		$p = array(self::FF_ACTION=>'book', 'q'=>$name);
		return $this->out->internLink("$pref<em>$name</em>$suf", $p + $query, 2);
	}

	protected function makeLabelLink($name, $query = array()) {
		$p = array(self::FF_ACTION=>'label', 'q'=>$name) + $query;
		$title = "Преглед на произведенията с етикет „{$name}“";
		return $this->out->internLink($name, $p, 2, $title);
	}


	protected function makeUserLink($name) {
		$p = array(self::FF_ACTION=>'user', 'username'=>$name);
		$title = 'Към личната страница на '. $name;
		return $this->out->internLink($name, $p, 2, $title);
	}

	protected function makeUserLinkWithEmail($username, $email, $allowemail) {
		$mlink = '';
		if ( !empty($email) && $allowemail == 'true' ) {
			$title = 'Пращане на писмо на '. htmlspecialchars($username);
			$img = $this->out->image($this->skin->image('mail'), 'е-поща', $title);
			$p = array(self::FF_ACTION=>'emailUser', 'username'=>$username);
			$mlink = '&nbsp;'. $this->out->internLink($img, $p, 2, $title);
		}
		return $this->makeUserLink($username) . $mlink;
	}


	protected function makeEditTextLink($textId, $chunkId = 1, $ext = true) {
		$l = '';
		if ($ext) {
			$l = ' | '. $this->makeEditLink('', $textId, 'основните данни за произведението', 'гл');
		}
		$p = array(self::FF_ACTION=>'editText', 'id'=>$textId, 'chunkId'=>$chunkId);
		$attrs = array('class' => 'edit');
		$title = 'Редактиране само на текстовото съдържание';
		$l .= ' | '. $this->out->internLink('т', $p, 3, $title, $attrs);
		$p['obj'] = 'anno';
		$title = 'Редактиране на анотацията към текста';
		$l .= ' | '. $this->out->internLink('ан', $p, 3, $title, $attrs);
		$p['obj'] = 'info';
		$title = 'Редактиране на допълнителната информация за текста';
		$l .= ' | '. $this->out->internLink('инф', $p, 3, $title, $attrs);
		return $l;
	}


	protected function makeEditAuthorLink($id) {
		return $this->makeEditLink('person', $id, 'автора', 'ред.');
	}

	protected function makeEditAltAuthorLink($id) {
		return $this->makeEditLink('altPerson', $id, 'алтернативното име', 'ред.');
	}

	protected function makeEditTranslatorLink($id) {
		return $this->makeEditLink('person', $id, 'преводача', 'ред.');
	}

	protected function makeEditSeriesLink($id, $name = '') {
		$title = 'поредицата';
		if ( !empty($name) ) $title .= ' „'.$name.'“';
		return $this->makeEditLink('series', $id, $title, 'ред.');
	}

	protected function makeEditBookLink($id, $name = '') {
		$title = 'книгата';
		if ( !empty($name) ) $title .= ' „'.$name.'“';
		return $this->makeEditLink('book', $id, $title, 'ред.');
	}

	protected function makeEditLabelLink($id, $name = '') {
		$title = 'етикета';
		if ( !empty($name) ) $title .= ' „'.$name.'“';
		return $this->makeEditLink('label', $id, $title, 'ред.');
	}

	protected function makeEditLiternewsLink($id) {
		return $this->makeEditLink('liternews', $id, 'новината');
	}

	protected function makeEditLink($key, $id, $title, $text = '') {
		if ( empty($id) ) {
			return '';
		}
		$p = array(self::FF_ACTION=>'edit'.ucfirst($key), 'id'=>$id);
		$title = 'Редактиране на '.$title;
		$attrs = array('class' => 'edit');
		fillOnEmpty($text, 'редактиране');
		return $this->out->internLink($text, $p, 2, $title, $attrs);
	}


	protected function makeCountryImage($code) {
		global $countries;
		return $this->out->image("$this->rootd/img/flag/$code.png", $countries[$code]);
	}


	protected function makeYearView($year, $yearAlt = 0, $year2 = 0) {
		if ( !empty($yearAlt) ) $year = $yearAlt;
		if ( empty($year) ) {
			return '????';
		}
		$year2 = empty($year2) ? '' : '–'. abs($year2);
		return $year > 0 ? $year . $year2 : abs($year) . $year2 .' пр.н.е.';
	}


	protected function formatPersonName($name, $sortby = 'first') {
		preg_match('/([^,]+) ([^,]+)(, .+)?/', $name, $m);
		if ( !isset($m[2]) ) { return $name; }
		$last = "<span class='lastname'>$m[2]</span>";
		$m3 = isset($m[3]) ? $m[3] : '';
		return $sortby == 'last' ? $last.', '.$m[1].$m3 : $m[1].' '.$last.$m3;
	}


	protected function makeCacheLink() {
		$l = '';
		if ( PageManager::pageCanBeCachedServer($this->action)
				&& $this->user->isAnon() ) {
			$url = $this->addUrlQuery(array('cache'=>'0'));
			$link = $this->out->link($url, 'Прочистване на склада',
				'Изтриване на складираното копие на страницата');
			$l = '<p id="cache-link">'. $link .'</p>';
		}
		return $l;
	}


	protected function makePageLinks($count, $limit, $offset = 0, $urlprefix = array()) {
		if ( $count <= $limit ) {
			return '';
		}
		if ( !array_key_exists(self::FF_ACTION, $urlprefix) ) {
			$urlprefix = array(self::FF_ACTION => $this->action) + $urlprefix;
		}
		$p = $urlprefix;
		$p += array(self::FF_LIMIT => $limit);
		$curCnt = $i = 0;
		$o = '';
		while ($curCnt < $count) {
			$i++;
			if ($offset == $curCnt) {
				$o .= "· <strong>$i</strong> ·";
			} else {
				$p[self::FF_OFFSET] = $curCnt;
				$o .= ' '. $this->out->internLink($i, $p) .' ';
			}
			$curCnt += $limit;
		}
		return '<div class="buttonlinks pagelinks">Страници:'.
			trim($o, '·').'</div>';
	}

	protected function makePrevNextPageLinks($maxlimit, $minlimit = 0,
			$qfields = array()) {
		$prev = $this->makePrevPageLink($minlimit, $qfields);
		$next = $this->makeNextPageLink($maxlimit, $qfields);
		return '<div class="pagelinks">'. trim($prev .' | '. $next, '| ') .'</div>';
	}

	protected function makePrevPageLink($minlimit = 0, $qfields = array()) {
		if ( $this->loffset <= $minlimit ) {
			return '';
		}
		$newoffset = normInt($this->loffset - $this->llimit, $this->loffset, $minlimit);
		$p = array(self::FF_ACTION => $this->action,
			self::FF_OFFSET => $newoffset, self::FF_LIMIT => $this->llimit);
		return '←&nbsp;'. $this->out->internLink('Предишна страница',
			$p + $qfields, 1);
	}

	protected function makeNextPageLink($maxlimit, $qfields = array()) {
		$newoffset = normInt($this->loffset + $this->llimit, $maxlimit, $this->loffset);
		if ( $newoffset >= $maxlimit ) {
			return '';
		}
		$p = array(self::FF_ACTION => $this->action,
			self::FF_OFFSET => $newoffset, self::FF_LIMIT => $this->llimit);
		return $this->out->internLink('Следваща страница',
			$p + $qfields, 1) .'&nbsp;→';
	}

	protected function initPaginationFields() {
		$this->loffset = (int) $this->request->value(self::FF_OFFSET, 0);
		$this->llimit = normInt(
			(int) $this->request->value(self::FF_LIMIT, $this->defListLimit),
			$this->maxListLimit);
	}


	protected function verifyCaptchaAnswer($showWarning = false,
			$_question = null, $_answer = null) {
		if ( !$this->showCaptchaToUser() ) {
			return true;
		}
		$this->captchaTries++;
		fillOnEmpty($_question, $this->captchaQuestion);
		fillOnEmpty($_answer, $this->captchaAnswer);
		$res = $this->db->select(DBT_QUESTION, array('id' => $_question));
		if ( $this->db->numRows($res) == 0 ) { // invalid question
			return false;
		}
		$row = $this->db->fetchAssoc($res);
		$answers = explode(',', $row['answers']);
		foreach ($answers as $answer) {
			if ($_answer == $answer) {
				$this->user->setIsHuman(true);
				return true;
			}
		}
		if ($showWarning) {
			$this->addMessage($this->makeCaptchaWarning(), true);
		}
		return false;
	}

	protected function makeCaptchaQuestion() {
		if ( !$this->showCaptchaToUser() ) {
			return '';
		}
		if ( empty($this->captchaQuestion) ) {
			extract( $this->db->getRandomRow(DBT_QUESTION) );
		} else {
			$id = $this->captchaQuestion;
			$question = $this->captchaQuestionT;
		}
		$qid = $this->out->hiddenField(self::FF_CQUESTION, $id);
		$qt = $this->out->hiddenField(self::FF_CQUESTION_T, $question);
		$tr = $this->out->hiddenField(self::FF_CTRIES, $this->captchaTries);
		$q = $this->out->label($question, self::FF_CANSWER);
		$answer = $this->out->textField(self::FF_CANSWER, '', $this->captchaAnswer, 30, 60);
		return $qid . $qt . $tr . $q .' '. $answer .'<br />';
	}

	protected function initCaptchaFields() {
		$this->captchaQuestion = (int) $this->request->value(self::FF_CQUESTION, 0);
		$this->captchaQuestionT = $this->request->value(self::FF_CQUESTION_T);
		$this->captchaAnswer = $this->request->value(self::FF_CANSWER);
		$this->captchaTries = (int) $this->request->value(self::FF_CTRIES, 0);
	}

	protected function clearCaptchaQuestion() {
		$this->captchaQuestion = 0;
		$this->captchaQuestionT = $this->captchaAnswer = '';
		$this->captchaTries = 0;
	}

	protected function makeCaptchaWarning() {
		if ( $this->hasMoreCaptchaTries() ) {
			$rest = $this->maxCaptchaTries - $this->captchaTries;
			$tries = chooseGrammNumber($rest, 'един опит', $rest.' опита');
			return "Отговорили сте грешно на въпроса „{$this->captchaQuestionT}“. Имате право на още $tries.";
		}
		return "Вече сте направили $this->maxCaptchaTries неуспешни опита да отговорите на въпроса „{$this->captchaQuestionT}“. Нямате право на повече.";
	}

	protected function hasMoreCaptchaTries() {
		return $this->captchaTries < $this->maxCaptchaTries;
	}

	protected function showCaptchaToUser() {
		return $this->user->isAnon() && !$this->user->isHuman();
	}

	protected function getFreeId($dbtable) {
		return $this->db->autoIncrementId($dbtable);
	}

	protected function addUrlQuery($args) {
		return $this->out->addUrlQuery($this->request->requestUri(), $args);
	}

}
