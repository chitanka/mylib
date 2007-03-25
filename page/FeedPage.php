<?php

class FeedPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'feed';
		$this->title = 'Зоб за новинарски четци';
		$this->feedtype = $this->request->param(1);
		$this->root = 'http://purl.org/NET'.$this->root;
		$this->tlimit = (int) $this->request->param(2, 25);
		$this->maxtlimit = 200;
		if ( $this->tlimit > $this->maxtlimit ) { $this->tlimit = $this->maxtlimit; }
	}


	protected function buildContent() {
		$q= "SELECT GROUP_CONCAT(DISTINCT a.name) author,
			t.id textId, t.title, t.type, t.date, t.lang, t.orig_lang,
			GROUP_CONCAT(DISTINCT tr.name) translator
			FROM /*p*/author_of aof
			LEFT JOIN /*p*/text t ON aof.text = t.id
			LEFT JOIN /*p*/person a ON aof.author = a.id
			LEFT JOIN /*p*/translator_of tof ON t.id = tof.text
			LEFT JOIN /*p*/person tr ON tof.translator = tr.id
			GROUP BY t.id ORDER BY t.date DESC, t.id DESC LIMIT $this->tlimit";
		switch ($this->feedtype) {
		case 'add-rss': default: return $this->makeRssFeed($q);
		}
	}


	protected function makeRssFeed($query) {
		$list = $this->db->iterateOverResult($query, 'makeRssItem', $this);
		$date = $this->makeRssDate();
		$xmlpi = '<?xml version="1.0" encoding="utf-8"?>';
		$xslpi = '<?xml-stylesheet type="text/xsl" href="'.$this->rootd.'/style/add-rss.xsl"?>';
/*
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/">
*/
		$feedcontent = <<<EOS
$xmlpi
$xslpi
<rss version="2.0">
<channel>
	<title>Моята библиотека</title>
	<link>http://purl.org/NET/mylib</link>
	<description>Универсална електронна библиотека</description>
	<language>bg</language>
	<pubDate>$date</pubDate>
	<generator>mylib</generator>
$list
</channel>
</rss>
EOS;
		#header('Content-Type: application/rss+xml');
		header('Content-Type: application/xml');
		header('Content-Length: '. strlen($feedcontent));
 		echo $feedcontent;
		$this->outputDone = true;
		return '';
	}


	public function makeRssItem($dbrow) {
		extract($dbrow);
		$cat = $GLOBALS['typesPl'][$type];
		$author = trim($author, ',');
		if ( empty($author) ) {
			$sauthor = $tauthor = '';
		} else {
			$sauthor = 'Автор: '. $this->makeAuthorLink($author) ."<br/>\n";
			$tauthor = ' — '. strtr($author, array(','=>', '));
		}
		$stranslator = '';
		if ($lang != $orig_lang) {
			$stranslator = 'Преводач: '.
				(empty($translator) ? 'Няма данни'
					: $this->makeTranslatorLink($translator)) ."<br/>\n";
		}
		$stype = 'Форма: '. $GLOBALS['types'][$type];
		$sdate = $this->makeRssDate($date);
		return <<<EOS

	<item>
		<title>$title$tauthor</title>
		<link>$this->root/text/$textId</link>
		<pubDate>$sdate</pubDate>
		<category>$cat</category>
		<guid>$this->root/text/$textId</guid>
		<description><![CDATA[Произведение: <a href="$this->root/text/$textId"><em>$title</em></a> (<a href="$this->root/text/$textId/raw">суров текст</a>, <a href="$this->root/download/$textId">архивиран суров текст</a>)<br/>
$sauthor$stranslator$stype]]></description>
	</item>
EOS;
	}


	protected function makeRssDate($isodate = NULL) {
		$format = 'D, d M Y H:i:s +0200';
		return empty($isodate) ? date($format) : date($format, strtotime($isodate));
	}
}
?>
