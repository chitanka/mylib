<?php
class ViewVersionsPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'viewVersions';
		$this->title = 'Преглед на версиите';
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->type = $this->request->value('type', 'text');
		$vers = array_keys((array) $this->request->value('vers'));
		$this->ver1 = array_shift($vers);
		$this->ver2 = array_shift($vers);
		$this->delOthers = $this->request->checkbox('delOthers');
		$this->ignoreSpaces = $this->request->checkbox('ignoreSpaces');
		$this->dir = getContentFilePath($this->type, $this->textId, false);
		$this->olddir = getContentFilePath('old'. $this->type, $this->textId, false);
	}


	protected function buildContent() {
		if ( empty($this->textId) ) {
			return $this->viewAllTexts();
		}
		$actions = array('doCompare', 'doRevert', 'doDelete');
		$cont = '';
		foreach ($actions as $action) {
			$req = $this->request->value($action);
			if ( !empty($req) ) {
				$cont = '<p>'. $this->$action() .'</p>';
				break;
			}
		}
		if ( !empty($cont) ) {
			$cont .= '<hr /><p>Легенда: <del>изтрито</del>, <ins>добавено</ins></p>';
		}
		$form = $this->makeForm();
		$editLink = $this->makeEditTextLink($this->textId);
		$alltexts = $this->out->internLink('Преглед на всички текстове', $this->action);
		$tlink = $this->makeSimpleTextLink('', $this->textId, 1, 'Текст '.$this->textId);
		return <<<EOS
<p>$alltexts</p>
<p>$tlink $editLink</p>
$cont
$form
EOS;
	}


	protected function makeForm() {
		$vers = $this->makeVersionsInput();
		if ( empty($vers) ) {
			$this->addMessage('Няма стари версии.');
			return '';
		}
		$textId = $this->out->hiddenField('textId', $this->textId);
		$type = $this->out->hiddenField('type', $this->type);
		$submit1 = $this->out->submitButton('Сравнение на избраните версии', '', 0, 'doCompare');
		$submit2 = $this->out->submitButton('Връщане към избраната версия', '', 0, 'doRevert');
		$submit3 = $this->out->submitButton('Изтриване на всички стари версии', '', 0, 'doDelete');
		$ignoreSpaces = $this->out->checkbox('ignoreSpaces', '', false,
			'Пренебрегване на разлики в интервалите');
		$delOthers = $this->out->checkbox('delOthers', '', false,
			'заедно с изтриване на останалите версии');
		return <<<EOS

<form action="{FACTION}" method="post" style="margin: 1em 0">
<div>
	$textId
	$type
	<fieldset>
	<legend>Версии</legend>
	$vers
	</fieldset>

	<ul>
	<li>
	$submit1
	$ignoreSpaces
	<br />(Ако изберете само една версия, тя ще бъде сравнена с текущата)
	</li>
	<li>или
	$submit2
	$delOthers
	</li>
	<li>или
	$submit3
	</li>
	</ul>
</div>
</form>
EOS;
	}


	protected function makeVersionsInput() {
		$vo = array();
		if ($dh = opendir($this->olddir)) {
			$pref = "$this->textId-";
			while (($file = readdir($dh)) !== false) {
				if ( $file{0} == '.' ) { continue; }
				if ( strpos($file, $pref) !== 0 ) { continue; }
				preg_match("/$pref(\d+)/", $file, $m);
				$timestamp = $m[1];
				$date = date('Y-m-d H:i:s', $timestamp);
				$size = filesize($this->olddir . $file);
				$ver = $this->out->checkbox("vers[$timestamp]", "v$timestamp",
					$this->ver1 == $timestamp || $this->ver2 == $timestamp,
					"$date, $size байта");
				$vo[$timestamp] = "\n\t$ver<br />";
			}
			closedir($dh);
		}
		#krsort($vo);
		return implode('', $vo);
	}


	protected function doCompare() {
		$file1 = empty($this->ver1)
			? $this->dir . $this->textId
			: $this->olddir ."$this->textId-$this->ver1";
		$file2 = empty($this->ver2)
			? $this->dir . $this->textId
			: $this->olddir ."$this->textId-$this->ver2";
		if ($file1 == $file2) {
			$link = $this->out->link($this->rootd.'/'.$file1, $file1);
			$this->addMessage("Показано е съдържанието на файла „{$link}“.");
			$cont = file_get_contents($file1);
			return $cont;
		}
		require 'Text/Diff.php';
		#require 'Text/Diff/Renderer.php';
		require 'Text/Diff/Renderer/inline.php';
		#require 'Text/Diff/Renderer/unified.php';
		$cont1 = file_get_contents($file1);
		$cont2 = file_get_contents($file2);
		if ($this->ignoreSpaces) {
			$cont1 = preg_replace('/  +/', ' ', $cont1);
			$cont2 = preg_replace('/  +/', ' ', $cont2);
		}
		$lines1 = explode("\n", $cont1);
		$lines2 = explode("\n", $cont2);
		$diff = &new Text_Diff($lines1, $lines2);
		$renderer = &new Text_Diff_Renderer_inline();
		$renderer->_block_header = '<hr />';
		$link1 = $this->out->link($this->rootd.'/'.$file1, $file1);
		$link2 = $this->out->link($this->rootd.'/'.$file2, $file2);
		$this->addMessage("Показано е сравнение на „{$link1}“ с „{$link2}“.");
		# ␊
		$repl = array("\n" => "<br />\n", "\t" => '&nbsp; &nbsp; &nbsp; ');
		return strtr($renderer->render($diff), $repl);
	}


	protected function doRevert() {
		$file = $this->dir . $this->textId;
		$old = $this->olddir . $this->textId .'-'. $this->ver1;
		$nold = $this->olddir . $this->textId .'-'. time();
		if ( file_exists($old) && mycopy($file, $nold) && mycopy($old, $file) ) {
			$date = date('Y-m-d H:i:s', $this->ver1);
			$this->addMessage("Версията от <strong>$date</strong> беше възвърната.");
			unlink($old);
		} else {
			$this->addMessage('Възвръщането не сполучи!', true);
		}
		if ($this->delOthers) { $this->doDelete(); }
	}


	protected function doDelete() {
		if ($dh = opendir($this->olddir)) {
			$pref = "$this->textId-";
			while (($file = readdir($dh)) !== false) {
				if ( $file{0} == '.' ) { continue; }
				if ( strpos($file, $pref) !== 0 ) { continue; }
				unlink($this->olddir . $file);
			}
			closedir($dh);
			$this->addMessage('Всички стари версии бяха изтрити.');
		}
		return '';
	}


	protected function viewAllTexts() {
		$texts = array();
		$dh = opendir($this->olddir);
		while (($file = readdir($dh)) !== false) {
			if ( !preg_match('/(\d+)-\d+/', $file, $m) ) { continue; }
			$text = $m[1];
			$mod = filemtime($this->olddir . $file);
			if ( !isset($texts[$text]) || $texts[$text] < $mod ) {
				$texts[$text] = $mod;
			}
		}
		closedir($dh);
		if ( empty($texts) ) {
			$this->addMessage('Никой текст не притежава стари версии.');
			return '';
		}
		arsort($texts);
		$l = '';
		foreach ( $texts as $text => $mod ) {
			$date = date('Y-m-d H:i:s', $mod);
			$p = array(self::FF_ACTION=>$this->action, 'textId'=>$text, 'type'=>$this->type);
			$link = $this->out->internLink($text, $p, 2);
			$l .= "\n<li><span class='extra'><tt>$date</tt></span> &nbsp; $link</li>";
		}
		return <<<EOS
<p>Следните текстове имат стари версии (сортирани по време на последна промяна):</p>
<ul>$l
</ul>
EOS;
	}

}
