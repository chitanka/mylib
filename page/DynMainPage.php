<?php

class DynMainPage extends Page {

	const
		FF_EC_COOKIE = 'dynEntryCount', FF_ENTRY_COUNT = 'ec';
	protected
		$headers = array(
			'liternews' => 'Литературни новини',
			'newtitles' => 'Добавени произведения',
			'worktitles' => 'Подготвяни произведения',
			'readercomments' => 'Читателски мнения',
			'forumnews' => 'Съобщения от форума',
			'sitenews' => 'Новини относно библиотеката',
		),
		$urls,
		$functions = array(
			'liternews' => 'makeLastLiterNews',
			'newtitles' => 'makeLastNewTitles',
			'worktitles' => 'makeLastWorkTitles',
			'readercomments' => 'makeLastReaderComments',
			'forumnews' => 'makeLastForumPosts',
			'sitenews' => 'makeLastNews',
		),
		$canonicalOrder = array(
			'newtitles',
			'worktitles',
			'forumnews',
			'readercomments',
			'sitenews',
			'liternews'),
		$deflimit = 5, $maxlimit = 25,
		$baseLimits = array(5, 10, 15);


	public function __construct() {
		parent::__construct();
		$this->action = 'dynMain';
		$this->title = 'Начална страница';
		$this->urls = array(
			'liternews' => $this->out->internUrl('liternews'),
			'newtitles' => $this->out->internUrl('history'),
			'worktitles' => $this->out->internUrl('work'),
			'readercomments' => $this->out->internUrl('comment'),
			'forumnews' => $this->forum_root,
			'sitenews' => $this->out->internUrl('news'),
		);
		$this->limit = (int) $this->request->value(self::FF_EC_COOKIE, $this->deflimit);
		$this->limit = (int) $this->request->value(self::FF_ENTRY_COUNT, $this->limit);
		if ($this->limit > $this->maxlimit) $this->limit = $this->maxlimit;
		elseif ($this->limit < 1) $this->limit = $this->deflimit;
		$this->request->setCookie(self::FF_EC_COOKIE, $this->limit);
		$this->userOpts = $this->user->options();
		$this->sectionOrder = array();
		foreach ($this->canonicalOrder as $pos => $id) {
			$key = isset($this->userOpts[$id][1]) ? $this->userOpts[$id][1] : $pos;
			$this->sectionOrder[$key] = $id;
		}
		ksort($this->sectionOrder);
	}


	protected function buildContent() {
		$this->addHeadContent('
	<meta http-equiv="Cache-Control" content="max-age=1,no-cache" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="-1" />');
		$this->addStyle('h2 a { text-decoration: none; }');
		$o = '';
		foreach ($this->sectionOrder as $id) {
			if ( isset($this->userOpts[$id][0]) && $this->userOpts[$id][0] == 0 ) {
				// don't show this section
				continue;
			}
			$makefunc = $this->functions[$id];
			$limit = isset($this->userOpts[$id][2])
				? (int) $this->userOpts[$id][2] : $this->limit;
			$o .= $this->makeHeader($id) . $this->$makefunc($limit);
			$link = $this->out->link('#'.$id, $this->headers[$id]);
			$this->toc .= "\n\t<li>$link</li>";
		}
		return $this->makeIntro() . $o;
	}


	protected function makeHeader($id) {
		$link = $this->out->link($this->urls[$id], $this->headers[$id], '',
			array('id' => $id, 'name' => $id));
		return "\n\n<h2>$link</h2>";
	}


	protected function makeIntro() {
		$p = array();
		if ( $this->user->option('mainpage') != 'd' ) {
			$p[self::FF_ACTION] = $this->action;
		}
		$ignorePos = count($p);
		$selflinks = '';
		foreach ($this->baseLimits as $cnt) {
			if ($this->limit == $cnt) {
				$selflinks .= "<strong>$cnt</strong>";
			} else {
				$p[self::FF_ENTRY_COUNT] = $cnt;
				$selflinks .= $this->out->internLink($cnt, $p, $ignorePos,
					"Показване на най-много $cnt записа в раздел");
			}
			$selflinks .= ', ';
		}
		$selflinks = rtrim($selflinks, ' ,');
		$static = $this->out->internLink('статична', 'staticMain', 1,
			'Към статичната версия на началната страница');
		return <<<EOS

<div id="toc">
<h2>Съдържание</h2>
<ul>$this->toc
</ul>
</div>
<p style="text-align:right">Максимален брой на записите в раздел: $selflinks</p>
<p><br/></p>
<p><em>$this->sitename</em> предлага две версии на началната страница: $static и динамична. В настройките можете да изберете коя от двете да се показва по подразбиране.</p>
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


	protected function makeLastNewTitles($limit = 10) {
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
		return $newspage->makeNews($limit, 0, false);
	}


	protected function makeLastReaderComments($limit = 10) {
		$commentpage = PageManager::buildPage('comment');
		return $commentpage->makeAllComments($limit, 0, 'DESC', false);
	}


	protected function makeLastNews($limit = 10) {
		$page = PageManager::executePage('news');
		return $page->makeNews($limit, 0, false);
	}
}
