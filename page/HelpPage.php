<?php

class HelpPage extends WikiPage {

	private $titles = array(
		'toc' => 'Съдържание',
		'add' => 'Добавяне на текстове',
		'edit' => 'Редактиране на текстове',
	);


	public function __construct() {
		parent::__construct();
		$this->action = 'help';
		$this->title = 'Помощ — ';
		$this->topic = $this->request->param(1);
		if ( !isset($this->titles[$this->topic]) ) { $this->topic = 'toc'; }
		$this->title .= $this->titles[$this->topic];
	}


	protected function buildContent() {
		$o = $this->makeTOC();
		if ($this->topic != 'toc') {
			$o .= parent::buildContent();
		}
		return $o;
	}


	protected function filename() {
		return parent::filename() .'-'. $this->topic;
	}


	protected function makeTOC() {
		$titles = $this->titles;
		unset($titles['toc'], $titles['add']);
		$t = '<div id="toc"><p>Помощни страници:</p><ul>';
		foreach ($titles as $page => $title) {
			$t .= $page == $this->topic
				? "<li><strong>$title</strong></li>"
				: "<li><a href=\"$this->root/help/$page\">$title</a></li>";
		}
		$t .= '</ul></div>';
		return $t;
	}
}
?>
