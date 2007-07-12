<?php

class FeedPage extends Page {

	protected
		$appname = 'mylib', // Application name
		$validObjs = array('new', 'edit', 'comment', 'liternews', 'news', 'work'),
		$validFeedTypes = array('rss'),
		$maphist = array('new' => 'entrydate', 'edit' => 'lastmod'),
		$defObj = 'new', $defFeedType = 'rss', $defListLimit = 25,
		$maxListLimit = 200;


	public function __construct() {
		parent::__construct();
		$this->action = 'feed';
		$this->title = 'Зоб за новинарски четци';
		$this->feedDescription = 'Универсална електронна библиотека';
		$this->root = $this->purl;
		$this->contentType = 'application/rss+xml';
		$this->skin->useAbsolutePath();
		$this->obj = normVal(
			$this->request->value('obj', $this->defObj, 1),
			$this->validObjs, $this->defObj);
		$this->llimit = normInt(
			(int) $this->request->value('limit', $this->defListLimit, 2),
			$this->maxListLimit);
		$this->feedtype = normVal(
			$this->request->value('type', $this->defFeedType, 3),
			$this->validFeedTypes, $this->defFeedType);
	}


	protected function buildContent() {
		$ftPref = ucfirst($this->feedtype);
		$myfields = array('root' => $this->root);
		$pagename = '';
		switch ($this->obj) {
		case 'new': case 'edit':
			$makeItemFunc = 'make'.$ftPref.'HistoryItem';
			$pagename = 'history';
			$this->dbfield = $this->maphist[$this->obj];
			$myfields['getby'] = $this->dbfield;
			break;
		case 'comment': case 'liternews': case 'news': case 'work':
			$makeItemFunc = 'make'. $ftPref . ucfirst($this->obj) .'Item';
			$pagename = $this->obj;
			$myfields['objId'] = 0;
			break;
		}
		$bufferq = false;
		if ($this->obj == 'work') {
			$bufferq = true;
			$myfields['showProgressbar'] = false;
		}
		$this->basepage = PageManager::buildPage($pagename);
		$this->basepage->setFields($myfields);
		$this->title = $this->basepage->title();
		$makeFunc = 'make'.$ftPref.'Feed';
		$q = $this->basepage->makeSqlQuery($this->llimit, 0, 'DESC');
		$this->fullContent = $this->$makeFunc($q, $makeItemFunc, $bufferq);
		return $this->fullContent;
	}


	protected function makeRssFeed($query, $makeItemFunc, $bufferq) {
		$xmlpi = '<?xml version="1.0" encoding="'.$this->outencoding.'"?>';
		$ch =
			$this->makeXmlElement('title', "$this->title — $this->sitename") .
			$this->makeXmlElement('link', $this->purl) .
			$this->makeXmlElement('description', $this->feedDescription) .
			$this->makeXmlElement('language', $this->langCode) .
			$this->makeXmlElement('lastBuildDate', $this->makeRssDate()) .
			$this->makeXmlElement('generator', $this->appname) .
			$this->db->iterateOverResult($query, $makeItemFunc, $this, $bufferq);
		return <<<EOS
$xmlpi
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>$ch
</channel>
</rss>
EOS;
	}


	public function makeRssHistoryItem($dbrow) {
		extract($dbrow);
		$author = $collection == 'true' ? '' : trim($author, ',');
		$ttitle = $title;
		$sauthor = $stranslator = $sseries = '';
		if ( !empty($series) ) {
			$sseries = '<li>Поредица: '. $this->makeSeriesLink($series) ."</li>\n";
		}
		if ( !empty($author) ) {
			$sauthor = '<li>Автор: '. $this->makeAuthorLink($author) ."</li>\n";
			$title .= ' — '. strtr($author, array(','=>', '));
		}
		if ($lang != $orig_lang) {
			$stranslator = '<li>Превод: '. (empty($translator) ? 'Няма данни'
				: $this->makeTranslatorLink($translator)) ."</li>\n";
		}
		$comment = empty($edit_comment) ? '' : "<li>Коментар: $edit_comment</li>";
		$description = <<<EOS
<ul>
$comment
<li>Произведение: <a href="$this->root/text/$textId"><em>$ttitle</em></a> (<a href="$this->root/text/$textId/raw">суров текст</a>, <a href="$this->root/download/$textId">архивиран суров текст</a>)</li>
$sseries$sauthor$stranslator<li>Форма: {$GLOBALS['types'][$type]}</li>
</ul>
EOS;
		$time = $this->makeRssDate($dbrow[$this->dbfield]);
		$link = "$this->root/text/$textId";
		$guid = $link .'#'. $this->formatDateForGuid($dbrow[$this->dbfield]);
		$data = compact('title', 'link', 'time', 'guid', 'description');
		return $this->makeRssItem($data);
	}


	public function makeRssCommentItem($dbrow) {
		extract($dbrow);
		$title = "$rname за „{$textTitle}“". $this->makeFromAuthorSuffix($dbrow);
		$dbrow['showtitle'] = $dbrow['showtime'] = false;
		$description = $this->basepage->makeComment($dbrow);
		$link = "$this->root/comment/$textId#e$id";
		$creator = $rname;
		$data = compact('title', 'link', 'time', 'description', 'creator');
		return $this->makeRssItem($data);
	}


	public function makeRssLiternewsItem($dbrow) {
		extract($dbrow);
		$dbrow['showtitle'] = $dbrow['showtime'] = false;
		$description = $this->basepage->makeNewsEntry($dbrow);
		$link = "$this->root/liternews#e$id";
		$creator = $username;
		$source = $src;
		$data = compact('title', 'link', 'time', 'description', 'creator', 'source');
		return $this->makeRssItem($data);
	}


	public function makeRssNewsItem($dbrow) {
		extract($dbrow);
		$dbrow['showtime'] = false;
		$description = $this->basepage->makeNewsEntry($dbrow);
		$link = "$this->root/news#e$id";
		$creator = $username;
		$data = compact('link', 'time', 'description', 'creator');
		return $this->makeRssItem($data);
	}


	public function makeRssWorkItem($dbrow) {
		extract($dbrow);
		$dbrow['showtitle'] = $dbrow['showtime'] = false;
		$dbrow['expandinfo'] = $dbrow['showeditors'] = true;
		$description = $this->basepage->makeWorkListItem($dbrow, false);
		$time = $date;
		$link = "$this->root/work#e$id";
		$guid = "$link-$status-$progress";
		if ( $type == 1 && $status >= $this->basepage->maxScanStatus ) {
			$guid .= '-'. $this->formatDateForGuid($date);
		}
		$creator = $username;
		$data = compact('title', 'link', 'time', 'guid', 'description', 'creator');
		return $this->makeRssItem($data);
	}


	public function makeRssItem($data) {
		extract($data);
		if ( empty($title) ) $title = strtr($time, array(' 00:00:00' => ''));
		if ( empty($creator) ) $creator = $this->sitename;
		if ( empty($guid) ) $guid = $link;
		$src = empty($source) ? '' : $source;
		$lvl = 2;
		return "\n\t<item>".
			$this->makeXmlElement('title', strip_tags($title), $lvl) .
			$this->makeXmlElement('dc:creator', $creator, $lvl) .
			$this->makeXmlElement('link', $link, $lvl) .
			$this->makeXmlElement('pubDate', $this->makeRssDate($time), $lvl) .
			$this->makeXmlElement('guid', $guid, $lvl) .
			$this->makeXmlElement('description', $description, $lvl) .
			$this->makeXmlElement('source', $src, $lvl, array('url'=>$src)) .
			"\n\t</item>";
	}


	protected function makeXmlElement($name, $content, $level = 1, $attribs = array()) {
		if ( empty($content) ) return '';
		$attrs = '';
		foreach ((array)$attribs as $attrname => $attrval) {
			$attrs .= ' '.$attrname .'="'. htmlspecialchars($attrval) .'"';
		}
		$content = htmlspecialchars($content);
		return "\n". str_repeat("\t", $level) ."<$name$attrs>$content</$name>";
	}


	protected function makeRssDate($isodate = NULL) {
		$format = 'r';
		return empty($isodate) ? date($format) : date($format, strtotime($isodate));
	}


	protected function formatDateForGuid($date) {
		return strtr($date, ' :', '__');
	}
}
