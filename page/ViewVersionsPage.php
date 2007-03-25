<?php
class ViewVersionsPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'viewVersions';
		$this->title = 'Преглед на версиите';
		$this->type = $this->request->value('type', 'text');
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->chunkId = (int) $this->request->value('chunkId', 1, 2);
		$vers = (array) $this->request->value('vers');
		$this->ver1 = array_shift($vers);
		$this->ver2 = array_shift($vers);
		$this->delOthers = $this->request->checkbox('delOthers');
		$this->ignoreSpaces = $this->request->checkbox('ignoreSpaces');
		global $contentDirs;
		$this->dir = $contentDirs['text'];
		$this->olddir = $contentDirs['oldtext'];
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
				$cont = '<pre class="example">'. $this->$action() .'</pre>';
				break;
			}
		}
		if ( !empty($cont) ) {
			$cont .= '<p>Легенда: <del>изтрито</del>, <ins>вмъкнато</ins></p>';
		}
		$form = $this->makeForm();
		$editLink = $this->makeEditTextLink($this->textId, $this->chunkId);
		return <<<EOS
<p><a href="$this->root/$this->action">Преглед на всички текстове</a></p>
<p><a href="$this->root/text/$this->textId/$this->chunkId">Текст $this->textId-$this->chunkId</a> $editLink</p>
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
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
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
	$chunkId

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
		$o = '';
		if ($dh = opendir($this->olddir)) {
			$pref = "$this->textId-$this->chunkId-";
			while (($file = readdir($dh)) !== false) {
				if ( $file{0} == '.' ) { continue; }
				if ( strpos($file, $pref) !== 0 ) { continue; }
				preg_match("/$pref(\d+)/", $file, $m);
				$timestamp = $m[1];
				$date = date('Y-m-d H:i:s', $timestamp);
				$size = filesize($this->olddir . $file);
				$ver = $this->out->checkbox('vers[]', "v$timestamp",
					$this->ver1 == $timestamp || $this->ver2 == $timestamp,
					"$date, $size байта");
				$o .= "\n\t$ver<br />";
			}
			closedir($dh);
		}
		return $o;
	}


	protected function doCompare() {
		$common = $this->textId .'-'. $this->chunkId;
		$file1 = empty($this->ver1)
			? $this->dir.$common : $this->olddir ."$common-$this->ver1";
		$file2 = empty($this->ver2)
			? $this->dir.$common : $this->olddir ."$common-$this->ver2";
		if ($file1 == $file2) {
			$this->addMessage("Показано е съдържанието на файла
				„<a href='$this->rootd/$file1'>$file1</a>“.");
			$cont = file_get_contents($file1);
			return $cont;
		}
		require 'Text/Diff.php';
		#require 'Text/Diff/Renderer.php';
		require 'Text/Diff/Renderer/inline.php';
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
		$this->addMessage("Показано е сравнение на
			„<a href='$this->rootd/$file1'>$file1</a>“ с
			„<a href='$this->rootd/$file2'>$file2</a>“.");
		return str_replace("\n", "␊\n", $renderer->render($diff));
	}


	protected function doRevert() {
		$file = $this->dir."$this->textId-$this->chunkId";
		$old = $this->olddir."$this->textId-$this->chunkId-$this->ver1";
		$nold = $this->olddir."$this->textId-$this->chunkId-".time();
		if ( file_exists($old) && copy($file, $nold) && copy($old, $file) ) {
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
			$pref = "$this->textId-$this->chunkId-";
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
			if ( !preg_match('/(\d+-\d+)-\d+/', $file, $m) ) { continue; }
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
			list($tid, $cid) = explode('-', $text);
			$date = date('Y-m-d H:i:s', $mod);
			$l .= "\n<li><span class='extra'><tt>$date</tt></span> &nbsp;
				<a href='$this->root/viewVersions/$tid/$cid'>$text</a></li>";
		}
		return <<<EOS
<p>Следните текстове имат стари версии (сортирани по време на последна промяна):</p>
<ul>
$l
</ul>
EOS;
	}

}
?>
