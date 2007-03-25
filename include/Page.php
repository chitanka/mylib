<?php

class Page {

	protected $FF_QUERY = 'q';
	protected $action, $title, $head, $langCode, $outencoding, $contentType;
	protected $request, $user, $db, $content, $messages, $jsContent, $style;
	protected $scriptStart, $scriptEnd, $styleStart, $styleEnd;


	public function __construct($action = '') {
		$this->request = Setup::request();
		$this->db = Setup::db();
		$this->user = Setup::user();
		$this->skin = Setup::skin();
		$this->out = Setup::outputMaker();
		$this->langCode = Setup::setting('lang_code');
		#$this->masterEncoding = Setup::$masterEncoding;
		$this->inencoding = 'utf-8';
		$this->outencoding = $this->request->outputEncoding;
		$this->root = Setup::setting('webroot');
		$this->rootd = Setup::setting('docroot');
		$this->sitename = Setup::setting('sitename');
		$this->purl = Setup::setting('purl');
		$this->infoSrcs = Setup::setting('info');

		$this->action = $action;
		$this->messages = $this->content =
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
	 * Generate page content according to submission type (POST or GET)
	 * @return string
	 */
	public function execute() {
		$this->content = $this->request->wasPosted()
			? $this->processSubmission() : $this->buildContent();
		return $this->content;
	}


	/**
	 *
	 * @param string $message
	 * @param bool $isError
	 */
	public function addMessage($message, $isError = false) {
		$class = $isError ? ' class="error"' : '';
		$this->messages .= "<p$class>$message</p>\n";
	}


	public function title() { return $this->title; }
	public function messages() { return $this->messages; }
	public function content() { return $this->content; }
	public function setAction($action) { $this->action = $action; }
	public function setContent($content) { $this->content = $content; }
	public function setFullContent($content) { $this->fullContent = $content; }
	public function setMessages($messages) { $this->messages = $messages; }
	public function setTitle($title) { $this->title = $title; }
	public function setOutEncoding($enc) { $this->outencoding = $enc; }
	public function setContentType($contentType) { $this->contentType = $contentType; }

	public function setFields($data) {
		foreach ((array)$data as $field => $value) {
			$this->$field = $value;
		}
	}


	public function addContent($content) { $this->content .= $content; }
	public function addStyle($style) { $this->style .= $style; }
	public function addJs($jsContent) { $this->jsContent .= $jsContent; }
	public function addExtraLinks($extra) { $this->extra .= $extra; }
	public function addHeadContent($content) { $this->head .= $content; }


	/**
	 * Output page content
	 */
	public function output($elapsedTime) {
		if ( $this->outputDone ) { return; } // already outputted
		if ( !headers_sent() ) {
			header("Content-Type: $this->contentType; charset=$this->outencoding");
			header("Content-Language: $this->langCode");
			header('Content-Style-Type: text/css');
			header('Content-Script-Type: text/javascript');
		}
		if ( empty($this->fullContent) ) {
			$this->makeFullContent($elapsedTime);
		}
		print $this->fullContent;
	}


	public function isValidEncoding($enc) {
		return @iconv($this->inencoding, $enc, '') !== false;
	}


	/**
	 * Process POST Forms if there are any.
	 * Override this function if your page contains POST Forms.
	 * @return string
	 */
	protected function processSubmission() { return $this->buildContent(); }

	/**
	 * Create page content.
	 * Override this function to include content in your page.
	 * @return string
	 */
	protected function buildContent() { return ''; }


	/**
	 * Build full page content
	 * @return string
	 */
	protected function makeFullContent($elapsedTime = NULL) {
		$nav = $this->makeNavigationBar();
		$pageTitle = strtr($this->title, array('<br />'=>' — '));
		$pageTitle = strip_tags($pageTitle);
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
		$opts = $this->user->option('skin').'-'.$this->user->option('nav');
		$xmlPI = '<?xml version="1.0" encoding="'.$this->outencoding.'"?>';
		$req = strtr($this->request->requestUri(), array($this->root => ''));
		$req = preg_replace('/&submitButton=[^&]+/', '', $req);
		$purl = $this->out->genlink($this->purl.$req,
			strtr($this->purl.urldecode($req), ' ', '+'),
			array(), 'Постоянен адрес на страницата');
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

	<link rel="stylesheet" type="text/css" href="$this->root/css/main?$opts" title="Основен изглед" />
	<link rel="stylesheet" type="text/css" media="print" href="$this->root/css/print" />
	<link rel="alternate stylesheet" type="text/css" href="$this->root/css/main?$opts" title="Без навигация" />
	<link rel="alternate stylesheet" type="text/css" href="$this->root/css/nonav" title="Без навигация" />
	<link rel="home" href="$this->root" title="Начална страница" />
	<link rel="icon" href="$this->rootd/img/favicon.png" type="image/png" />
	<link rel="shortcut icon" href="$this->rootd/img/favicon.png" type="image/png" />
	<script type="text/javascript" src="$this->rootd/main.js.php"></script>
$this->style
$this->jsContent
$this->head
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
	<li><a href="$this->root/about">За $this->sitename</a></li>
</ul>
</div>

<p class="non-graphic">
Това е краят на страницата.
Можете да се върнете в <a href="#jump-to">началото й</a> или да посетите <a href="$this->root">началната страница</a>.
</p>

$cacheLink
$elapsedTimeMsg
</body>
</html>
EOS;
		unset($this->content); // free some memory
		/*if ($this->outencoding != $this->masterEncoding) {
			$o = iconv($this->masterEncoding,
				$this->outencoding.'//TRANSLIT', $o);
		}*/
		addTemplate('ROOT', $this->root.'/');
		addTemplate('DOCROOT', $this->rootd.'/');
		addTemplate('IMGDIR', $this->skin->imageDir());
		addTemplate('FACTION', $this->root.'/'.$this->action);
		addTemplate('SITENAME', $this->sitename);
		$this->fullContent = expandTemplates($this->fullContent);
		return $this->fullContent;
	}


	protected function makeNavigationBar() {
		$startwith = isset($this->startwith) ? stripslashes($this->startwith) : '';
		$startwith = trim( strtr($startwith, '%', ' ') );
		$options = array('author'=>'Автор', 'translator'=>'Преводач',
			'title'=>'Заглавие', 'series'=>'Поредица', 'label'=>'Етикет');
		$action = $this->out->selectBox('action', '', $options, $this->action);
		$persTools = $this->makePersonalTools();
		$links = array(
'Автори' => array(
	array("$this->root/author", 'Начална страница за преглед на авторите', 'Съдържание'),
	array("$this->root/author/sortby=last/mode=simple", 'Всички автори, подредени по фамилия', 'Всички'),
),
'Преводачи' => array(
	array("$this->root/translator", 'Начална страница за преглед на преводачите', 'Съдържание'),
	array("$this->root/translator/sortby=last/mode=simple", 'Всички преводачи, подредени по фамилия', 'Всички'),
),
'Заглавия' => array(
	array("$this->root/title", 'Начална страница за преглед на заглавията', 'Съдържание'),
	array("$this->root/text/random", 'Показване на случайно заглавие', 'Случайно'),
	array("$this->root/title/mode=simple", 'Всички заглавия', 'Всички'),
),
'Поредици' => array(
	array("$this->root/series", 'Начална страница за преглед на поредиците', 'Съдържание'),
	array("$this->root/series/mode=simple", 'Всички поредици', 'Всички'),
),
'Етикети' => array(
	array("$this->root/label", 'Начална страница за преглед на етикетите', 'Съдържание'),
	array("$this->root/label/mode=simple", 'Всички етикети', 'Всички'),
),
'Разни' => array(
	array("$this->root/news", 'Новини относно сайта', 'Новини'),
	array("$this->root/history", 'Списък на произведенията по месец на добавяне', 'История'),
	array("$this->root/work", 'Списък на произведения, подготвящи се за добавяне', 'Сканиране'),
	array("$this->root/liternews", 'Новини, свързани с литературата. Добавят се от потребителите.', 'Литер. новини'),
	array("/phpBB2/", 'Форумната система на сайта', 'Форум'),
	array("$this->root/links", 'Връзки към други полезни места в мрежата', 'Връзки'),
	array("$this->root/feedback", 'Връзка с хората, отговарящи за библиотеката', 'Обратна връзка'),
),
		);
		$navmenu = '';
		$reqUri = $this->request->requestUri();
		foreach ($links as $section => $slinks) {
			$navmenu .= "\n<dt>$section</dt>";
			foreach ($slinks as $linkdata) {
				list($href, $title, $text) = $linkdata;
				$navmenu .= strpos(strrev($reqUri), strrev($href)) === 0
					? "\n\t<dd class='selected'>$text</dd>"
					: "\n\t<dd><a href='$href' title='$title'>$text</a></dd>";
			}
		}
		$prefix = $this->out->hiddenField('prefix', '%');
		$sortby = $this->out->hiddenField('sortby', 'first');
		$mode = $this->out->hiddenField('mode', 'simple');
		$q = $this->out->textField('q', '', $startwith, 14, 40, 0,
			'Клавиш за достъп — Т', 'style="width:9em" accesskey="т"');
		$submit = $this->out->submitButton('Показване', 'Показване на резултатите от търсенето');
		return <<<EOS
<div id="navigation"><a name="navigation"> </a>
<div id="logo"><a href="$this->root" title="Към началната страница">$this->sitename</a></div>

$persTools

<dl id="nav-main" title="Това са връзки, улесняващи разхождането из сайта">$navmenu
</dl>
<dl id="search" title="Претърсване на библиотеката">
<dt>Търсене</dt>
<dd>
<form action="$this->root" method="get" style="margin:0.3em 0">
<div><a name="search"> </a>
	<label for="action">по:</label>
	$action
	<label for="q" style="display:none">на:</label>
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
	<dd>» <a href="http://bg.wiktionary.org/" title="Уикиречник е свободен и безплатен речник, който се обогатява и обновява от читателите си">Уикиречник</a></dd>
	<dd>» <a href="http://www.getfirefox.com/" title="Мозила Файърфокс е свободен и безплатен графичен уеб браузър, достъпен за множество платформи">Файърфокс</a></dd>
</dl>
<div id="searchhelpbox" style="text-align:center">
<p><img src="{IMGDIR}helpcenter.png" alt="Помощ" title="Спасителен пояс" /></p>
<p>Желаете да помогнете?</p>
<p><a href="$this->root/work"><strong>Сканирайте книга!</strong></a></p>
</div>
<div id="propaganda" style="text-align:center">
<a href="http://protest.bloghub.org" style="margin-bottom:0.2ex"><img src="$this->rootd/img/protestlogo7um.138px.jpg" alt="Труд нападна сляп!" width="138" title="Протест срещу отношението на КК ТРУД и други издателства към електронните библиотеки" /></a>
</div>
</div>

EOS;
	}


	protected function makePersonalTools() {
		if ( $this->user->isAnon() ) {
			$c = <<<EOS
<dd><a href="$this->root/settings" title="Потребителски настройки">Настройки</a></dd>
<dd><a href="$this->root/register" title="Регистриране в $this->sitename">Регистрация</a></dd>
<dd><a href="$this->root/login" title="Влизане в $this->sitename">Вход</a></dd>
EOS;
		} else {
			$usernameEnc = $this->urlencode($this->user->username);
			$c = <<<EOS
<dd><a href="$this->root/user/$usernameEnc" title="Лична страница">{$this->user->username}</a></dd>
<dd><a href="$this->root/settings" title="Потребителски настройки">Настройки</a></dd>
<dd><a href="$this->root/logout" title="Излизане от $this->sitename">Изход</a></dd>
EOS;
		}
		return <<<EOS
<dl id="personal" title="Лични инструменти">
<dt>Лични инструменти</dt>
$c
</dl>
EOS;
	}


	protected function encprint($s, $filter = NULL) {
		if ($this->outencoding != $this->inencoding) {
			$s = iconv($this->inencoding, $this->outencoding.'//TRANSLIT', $s);
			if ( !empty($filter) && function_exists($filter) ) {
				$s = $filter($s);
			}
		}
		echo $s;
	}


	/**
	 * Redirect to another page
	 * @param string $action
	 */
	protected function redirect($action = '') {
		if ( empty($action) ) $action = PageManager::defaultPage();
		$newPage = PageManager::executePage($action);
		$this->copyPage($newPage);
		return $this->content;
	}


	protected function copyPage($page) {
		$fields = array('title', 'content', 'style', 'jsContent', 'extra', 'user');
		$this->messages .= $page->messages;
		foreach ($fields as $field) { $this->$field = $page->$field; }
	}


	/**
	 * Returns first char of a string, which may be build from up to 3 Bytes
	 * (taken from MediaWiki)
	 * @param string $s
	 * @return string
	 */
	protected function firstChar($s) {
		preg_match( '/^([\x00-\x7f]|[\xc0-\xdf][\x80-\xbf]|' .
			'[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xf7][\x80-\xbf]{3})/', $s, $matches);
		return isset( $matches[1] ) ? $matches[1] : '';
	}

	protected function urlencode($str) {
		#$str = iconv($this->masterEncoding, $this->outencoding, $str);
		$enc = urlencode($str);
		if ( strpos($str, '/') !== false ) {
			$enc = strtr($enc, array('%2F' => '/'));
		}
		return $enc;
	}


	/**
	 * @param int $textId Text ID
	 * @param int $zfsize Zip file size in bytes
	 * @return string
	 */
	protected function makeDlLink($textId, $zfsize, $ltext = NULL) {
		$zfsize = ceil($zfsize / 1024);
		$ltext = empty($ltext) ? "$zfsize&nbsp;КБ" : htmlspecialchars($ltext);
		return <<<EOS
	<a class="save" href="$this->root/download/$textId" title="Сваляне във формат ZIP, $zfsize КБ">$ltext</a>
EOS;
	}


	protected function makeMwLink($term, $src = 'w', $extended = true, $pref='', $suf='') {
		$term = rtrim($term, ',');
		if ( empty($term) ) { return ''; }
		$url = strtr($this->infoSrcs[$src], array('$1'=>"'.urlencode(strtr('$1', ' ', '_')).'"));
		$title = 'Информация за $1 в '.$this->infoSrcs[$src.'_name'];
		$text = $extended ? $title : '$1';
		return ltrim( preg_replace('/([^,]+)/e',
			"' $pref<a href=\"$url\" title=\"$title\">$text</a>$suf'", $term) );
	}


	protected function makeTextLink($data) {
		global $types;
		extract($data);
		$class = empty($reader) ? 'unread' : 'read';
		$ksize = floor($size / 1024);
		$titleExt = '';
		if ( ($this->time - $date) < 2592000 ) { // 30 days limit
			$class .= ' new';
			$fdate = date('d.m.Y', $date);
			$titleExt = " — ново произведение, добавено на $fdate";
		}
		return <<<EOS
	<a class="$class" href="$this->root/text/$textId"
		title="$types[$type], {$ksize} КБ$titleExt"><em>$title</em></a>
EOS;
	}


	protected function makeSimpleTextLink($title, $textId, $chunkId = 1) {
		return "<a href='$this->root/text/$textId/$chunkId' title='Към произведението „{$title}“'><em>$title</em></a>";
	}


	protected function makeRawTextLink($textId, $ltext = NULL, $title = NULL, $enc = NULL) {
		$ltext = empty($ltext) ? 'Суров текст' : htmlspecialchars($ltext);
		$title = empty($title) ? 'Към суровия текст на произведението' : htmlspecialchars($title);
		$enc = empty($enc) ? '' : '/'.$enc;
		return "<a href='$this->root/text/$textId/raw$enc' title='$title'>$ltext</a>";
	}


	protected function makeAuthorLink($name, $sortby='first', $pref='', $suf='', $query='') {
		$name = rtrim($name, ',');
		if ( empty($name) ) { return ''; }
		if ( !empty($query) ) { $query = '/'.$query; }
		$o = '';
		foreach ( explode(',', $name) as $lname ) {
			$text = empty($sortby) ? 'Произведения'
				: $this->formatPersonName($lname, $sortby);
			$o .= ", $pref<a href='$this->root/author/".$this->urlencode($lname).
				"$query'>$text</a>$suf";
		}
		return substr($o, 2);
// 		return ltrim( preg_replace('/([^,]+)/e',
// 		"' $pref<a href=\"$this->root/author/'.urlencode('$1').'$query\">$1</a>$suf'",
// 		$name) );
	}


	protected function makeTranslatorLink($name, $sortby='first', $pref='', $suf='', $query='') {
		if ( !empty($query) ) { $query = '/'.$query; }
		$o = '';
		foreach ( explode(',', $name) as $lname ) {
			$text = empty($sortby) ? 'Преводи' : $this->formatPersonName($lname, $sortby);
			$o .= ", $pref<a href=\"$this->root/translator/".$this->urlencode($lname).
				$query.'">'. $text ."</a>$suf";
		}
		return substr($o, 2);
	}


	protected function makeInfoLink($name, $extended=true, $pref='', $suf='', $query='') {
		if ( !empty($query) ) { $query = '/'.$query; }
		$o = '';
		foreach ( explode(',', $name) as $lname ) {
			$title = "Информация за $lname";
			$text = $extended ? $title : $lname;
			$o .= ", $pref<a href='$this->root/info/".$this->urlencode($lname).
				"$query' title='$title'>$text</a>$suf";
		}
		return substr($o, 2);
	}


	protected function makeSeriesLink($name, $rmSuffix = false) {
		$enc = $this->urlencode($name);
		if ($rmSuffix) { $name = preg_replace('/ \(.+\)$/', '', $name); }
		return "<a href='$this->root/series/$enc'><em>$name</em></a>";
	}


	protected function makeLabelLink($name, $query='') {
		$enc = $this->urlencode($name);
		return "<a href='$this->root/label/$enc$query' title='Преглед на произведенията с етикет „{$name}“'>$name</a>";
	}


	protected function makeUserLink($name) {
		$enc = $this->urlencode($name);
		return "<a href='$this->root/user/$enc'>$name</a>";
	}


	protected function makeEditTextLink($textId, $chunkId = 1, $ext = true) {
		$head = '';
		if ($ext) {
			$head = " | <a class='edit' href='$this->root/edit/$textId' ".
				"title='Редактиране на основните данни за произведението'>гл</a>";
		}
		return $head . <<<EOS
	 | <a class="edit" href="$this->root/editText/$textId/$chunkId" title="Редактиране само на текстовото съдържание" />т</a>
	 | <a class="edit" href="$this->root/editText/$textId/$chunkId/obj=anno" title="Редактиране на анотацията към текста" />ан</a>
	 | <a class="edit" href="$this->root/editText/$textId/$chunkId/obj=info" title="Редактиране на допълнителната информация за текста" />инф</a>
EOS;
	}


	protected function makeEditAuthorLink($authorId) {
		return <<<EOS
	<a class="edit" href="$this->root/editPerson/$authorId" title="Редактиране на автора"></a>
EOS;
	}


	protected function makeEditAltAuthorLink($altId) {
		return <<<EOS
	<a class="edit" href="$this->root/editAltPerson/$altId" title="Редактиране на алтернативното име"></a>
EOS;
	}


	protected function makeEditTranslatorLink($translatorId) {
		return <<<EOS
	<a class="edit" href="$this->root/editPerson/$translatorId" title="Редактиране на преводача"></a>
EOS;
	}


	protected function makeEditSeriesLink($seriesId) {
		return <<<EOS
	<a class="edit" href="$this->root/editSeries/$seriesId" title="Редактиране на поредицата"></a>
EOS;
	}


	protected function makeEditLabelLink($id) {
		return <<<EOS
	<a class="edit" href="$this->root/editLabel/$id" title="Редактиране на етикета"></a>
EOS;
	}


	protected function makeEditLiternewsLink($id) {
		return <<<EOS
	<a class="edit" href="$this->root/editLiternews/$id" title="Редактиране на новината">редактиране</a>
EOS;
	}


	protected function makeCountryImage($code) {
		global $countries;
		return <<<EOS
<img src="$this->rootd/img/flag/$code.png" alt="$countries[$code]" title="$countries[$code]" />
EOS;
	}


	protected function makeYearView($year, $year2 = 0) {
		if ( !empty($year2) ) return $year2;
		return empty($year) ? '????' : $year;
	}


	protected function formatPersonName($name, $sortby = 'first') {
		preg_match('/([^,]+) ([^,]+)(, .+)?/', $name, $m);
		if ( !isset($m[2]) ) { return $name; }
		$last = "<span class='lastname'>$m[2]</span>";
		return $sortby == 'last' ? $last.', '.$m[1].@$m[3] : $m[1].' '.$last.@$m[3];
	}


	protected function makeCacheLink() {
		$l = '';
		if ( PageManager::pageCanBeCachedServer($this->action)
				&& $this->user->isAnon() ) {
			$url = $this->request->addUrlQuery('cache', '0');
			$l = "<p id='cache-link'><a href='$url' title='Изтриване на складираното копие на страницата'>Прочистване на склада</a></p>";
		}
		return $l;
	}


	protected function getFreeId($dbtable) {
		return $this->db->autoIncrementId($dbtable);
	}

}

?>
