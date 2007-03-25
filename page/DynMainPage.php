<?php

class DynMainPage extends Page {

	protected $headers = array(
		'liternews' => 'Литературни новини',
		'addedtitles' => 'Добавени произведения',
		'worktitles' => 'Подготвяни произведения',
		'readercomments' => 'Читателски мнения',
		'forumnews' => 'Съобщения от форума',
		'sitenews' => 'Новини относно библиотеката',
	);
	protected $urls;
	protected $functions = array(
		'liternews' => 'makeLastLiterNews',
		'addedtitles' => 'makeLastAddedTitles',
		'worktitles' => 'makeLastWorkTitles',
		'readercomments' => 'makeLastReaderComments',
		'forumnews' => 'makeLastForumPosts',
		'sitenews' => 'makeLastNews',
	);
	protected $canonicalOrder = array('addedtitles', 'worktitles', 'forumnews',
		'readercomments', 'sitenews', 'liternews');
	protected $FF_EC_COOKIE = 'dynEntryCount', $FF_ENTRY_COUNT = 'ec';


	public function __construct() {
		parent::__construct();
		$this->action = 'dynMain';
		$this->title = 'Начална страница';
		$this->forum_root = Setup::setting('forum_root');
		$this->urls = array(
			'liternews' => "$this->root/liternews",
			'addedtitles' => "$this->root/history",
			'worktitles' => "$this->root/work",
			'readercomments' => "$this->root/comment",
			'forumnews' => $this->forum_root,
			'sitenews' => "$this->root/news",
		);
		$this->limit = (int) $this->request->value($this->FF_EC_COOKIE, 10);
		$this->limit = (int) $this->request->value($this->FF_ENTRY_COUNT, $this->limit);
		$this->maxlimit = 25;
		if ($this->limit > $this->maxlimit) $this->limit = $this->maxlimit;
		elseif ($this->limit < 1) $this->limit = 10;
		$this->request->setCookie($this->FF_EC_COOKIE, $this->limit);
	}


	protected function buildContent() {
		$this->addStyle('h2 a { text-decoration: none; }');
		$o = $this->makeTOC();
		foreach ($this->canonicalOrder as $id) {
			$makefunc = $this->functions[$id];
			$o .= $this->makeHeader($id) . $this->$makefunc($this->limit);
		}
		return $o;
	}


	protected function makeHeader($id) {
		return <<<EOS

<h2><a href="{$this->urls[$id]}" id="$id" name="$id">{$this->headers[$id]}</a></h2>
EOS;
	}


	protected function makeTOC() {
		$selflink = $this->root.($this->user->option('mainpage') == 'd' ? '' : "/$this->action");
		$selflinks = '';
		foreach (array(5, 10, 15) as $cnt) {
			$selflinks .= $this->limit == $cnt ? "<strong>$cnt</strong>, "
				: "<a href='$selflink/$this->FF_ENTRY_COUNT=$cnt' title='Показване на най-много $cnt записа в раздел'>$cnt</a>, ";
		}
		$selflinks = rtrim($selflinks, ' ,');
		$l = '';
		foreach ($this->canonicalOrder as $id) {
			$l .= "\n\t<li><a href='#$id'>{$this->headers[$id]}</a></li>";
		}
		return <<<EOS

<div id="toc">
<h2>Съдържание</h2>
<ul>$l
</ul>
</div>
<p style="text-align:right">Максимален брой на записите в раздел: $selflinks</p>
<p><br/></p>
<p><em>$this->sitename</em> предлага две версии на началната страница: <a href="$this->root/staticMain" title="Към статичната версия на началната страница">статична</a> и динамична. В настройките можете да изберете коя от двете да се показва по подразбиране.</p>
<p>В момента разглеждате динамичната версия, в която накуп са показани последните записи от няколко раздела в библиотеката.</p>
<p style="clear:both"></p>
EOS;
	}


	protected function makeLastForumPosts($limit = 10) {
		$proc = new XSLTProcessor();
		$xsl_filename = $this->forum_root .'templates/rss-compact.xsl';
		$xml_filename = $this->forum_root ."rss.php?c=$limit";
		$proc->importStyleSheet(DOMDocument::load($xsl_filename));
		return $proc->transformToXML(DOMDocument::load($xml_filename));
	}


	protected function makeLastAddedTitles($limit = 10) {
		$page = PageManager::buildPage('history');
		$page->date = -1;
		return $page->makeListByDate($limit, false);
	}


	protected function makeLastWorkTitles($limit = 10) {
		$workpage = PageManager::buildPage('work');
		return $workpage->makeWorkList($limit);
	}


	protected function makeLastLiterNews($limit = 10) {
		$newspage = PageManager::buildPage('liternews');
		return $newspage->makeNews($limit);
	}


	protected function makeLastReaderComments($limit = 10) {
		$commentpage = PageManager::buildPage('comment');
		return $commentpage->makeAllComments($limit, 'DESC');
	}


	protected function makeLastNews($limit = 10) {
		$page = PageManager::executePage('news');
		$regexp = '';
		for ($i=0; $i < $limit; $i++) {
			$regexp .= '(.*\n)?';
		}
		preg_match("/($regexp)/", trim($page->content(), "\n") ."\n", $m);
		return $m[1];
	}
}
?>
