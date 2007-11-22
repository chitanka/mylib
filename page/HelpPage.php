<?php

class HelpPage extends WikiPage {

	protected
		$titles = array(
			'toc' => 'Съдържание',
// 			'add' => 'Добавяне на текстове',
// 			'edit' => 'Редактиране на текстове',
			'sfb' => 'Описание на формата SFB',
		),
		$defTopic = 'toc';


	public function __construct() {
		parent::__construct();
		$this->action = 'help';
		$this->title = 'Помощ — ';
		$this->topic = normKey($this->request->value('topic', '', 1),
			$this->titles, $this->defTopic);
		$this->title .= $this->titles[$this->topic];
	}


	protected function buildContent() {
		$o = $this->makeTOC();
		if ($this->topic != 'toc') {
			$o .= parent::buildContent();
		}
		return $o;
	}


	protected function filename($action = NULL) {
		return parent::filename() .'-'. $this->topic;
	}


	protected function makeTOC() {
		$titles = $this->titles;
		unset($titles['toc']);
		$t = '<div id="toc"><p>Помощни страници:</p><ul>';
		foreach ($titles as $page => $title) {
			$item = $page == $this->topic
				? "<strong>$title</strong>"
				: $this->out->internLink($title, array(self::FF_ACTION=>'help', 'topic'=>$page), 2);
			$t .= "\n\t<li>$item</li>";
		}
		$t .= '</ul></div>';
		return $t;
	}

	protected function customizeParser() {
		$this->parser->addPattern("\t", "<span class='visible-ws'>\t</span>");
	}

}
