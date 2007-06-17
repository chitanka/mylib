<?php
class EditPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'edit';
		$this->title = 'Редактиране';
		$this->mainDbTable = 'text';
		$this->textId = (int) $this->request->value('textId', 0, 1);
		$this->obj = $this->request->value('obj', '', 2);
		$this->withText = true;
		$this->textonly = $this->obj == 'textonly';
		$defChunkId = $this->withText ? 1 : 0;
		$this->chunkId = (int) $this->request->value('chunkId', $defChunkId, 2);

		$this->mode = $this->request->value('mode', 'simple', 3);
		$this->replace = $this->request->checkbox('replace');
		$this->showagain = $this->request->checkbox('showagain');

		$this->author = (array) $this->request->value('author');
		$this->translator = (array) $this->request->value('translator');
		$this->ttitle = $this->request->value('title', '');
		$this->ctitle = $this->request->value('ctitle', '');
		$this->subtitle = $this->request->value('subtitle', '');
		$this->orig_title = $this->request->value('orig_title', '');
		$this->orig_subtitle = $this->request->value('orig_subtitle', '');
		$this->tlang = $this->request->value('lang', 'bg');
		$this->orig_lang = $this->request->value('orig_lang', 'bg');
		$this->year = $this->request->value('year');
		$this->year2 = $this->request->value('year2');
		$this->trans_year = $this->request->value('trans_year');
		$this->trans_year2 = $this->request->value('trans_year2');
		$this->type = $this->request->value('type', 'shortstory');
		$this->series = $this->request->value('series', 0);
		$this->sernr = $this->request->value('sernr', 0);
		$this->collection = $this->request->checkbox('collection');
		$this->headlevel = $this->request->value('headlevel', 0);
		$this->copy = (int) $this->request->checkbox('copy');
		$this->tcontent = rtrim($this->request->value('content', ''));
		// Format: USER(,PERCENT)?(;USER(,PERCENT)?)*
		$this->scanUser = $this->request->value('user');
	}


	protected function processSubmission() {
		if ( !$this->request->isCompleteSubmission() ) {
			$this->addMessage('Имало е проблем при пращането на формуляра.
			Не са получени всички необходими данни. Опитайте пак.', true);
			return $this->buildContent();
		}
		switch ($this->obj) {
		#case 'text' : $qs = (array)$this->makeUpdateChunkQuery(); break;
		case 'info' : $qs = $this->makeUpdateInfoQueries(); break;
		case 'anno' : $qs = $this->makeUpdateAnnoQueries(); break;
		case 'textonly' : $qs = $this->makeUpdateTextContentQueries(); break;
		default:      $qs = $this->makeUpdateTextQueries();
		}
		$this->db->transaction($qs);
		if ( !isset($this->isNew) ) {
			$this->addMessage('Промените бяха съхранени.');
		}
		if ( $this->showagain ) {
			#$this->addMessage("<a href='$this->root/text/$this->textId'>Към текста</a>");
			return $this->buildContent();
		}
		if ( strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ) {
			$this->addMessage("<a href='$this->root/text/$this->textId'>Към текста</a>");
			return '';
		}
		return $this->redirect('text');
	}


	protected function buildContent() {
		$this->replace = true;
		switch ($this->obj) {
		case 'text' : return $this->makeEditChunkForm();
		case 'info' : return $this->makeEditInfoForm();
		case 'anno' : return $this->makeEditAnnoForm();
		default:      return $this->makeEditTextForm();
		}
	}


	protected function makeUpdateTextQueries() {
		require_once 'include/replace.php';
		$this->ttitle = ltrim(my_replace(' '.$this->ttitle));
		$set = array('title' => $this->ttitle, 'orig_title' => $this->orig_title,
			'subtitle' => $this->subtitle, 'orig_subtitle' => $this->orig_subtitle,
			'lang' => $this->tlang, 'orig_lang' => $this->orig_lang,
			'year' => $this->year, 'trans_year' => $this->trans_year,
			'year2' => $this->year2, 'trans_year2' => $this->trans_year2,
			'type' => $this->type, 'collection' => $this->collection,
			'series' => $this->series, 'sernr' => $this->sernr,
			'copy' => $this->copy);
		$key = $this->textId;
		$qs = array();
		if ($this->textId == 0) {
			$set['entrydate'] = date('Y-m-d');
			$this->textId = $this->db->autoIncrementId($this->mainDbTable);
			if ( !empty($this->scanUser) ) {
				foreach ( explode(';', $this->scanUser) as $user_perc ) {
					$up = explode(',', $user_perc);
					$user = $up[0];
					$perc = isset($up[1]) ? $up[1] : 100;
					$setut = array('text' => $this->textId, 'user' => (int)$user,
						'percent' => (int)$perc);
					$qs[] = $this->db->insertQ('user_text', $setut);
				}
			}
		}
		$set['id'] = $this->textId;
		$qs[] = $this->db->makeInsertOrUpdateQuery($this->mainDbTable, $set, $key);
		if ( $this->mode == 'full' ) {
			$is_changed = $this->request->value('is_changed');
			foreach ( array('author', 'translator') as $key ) {
				if ( $is_changed[$key] ) {
					$dbkey = array('text' => $this->textId);
					$qs[] = $this->db->deleteQ($key.'_of', $dbkey);
					foreach ($this->$key as $pos => $person) {
						if ( empty($person) ) { continue; }
						$set = array($key => $person, 'text' => $this->textId, 'pos' => $pos);
						$qs[] = $this->db->insertQ($key.'_of', $set);
					}
				}
			}
		}
		$qs = array_merge($qs, $this->makeUpdateTextContentQueries());
		CacheManager::clearCache("text$this->textId");
		return $qs;
	}


	protected function makeUpdateTextContentQueries() {
		$qs = array();
		if ( is_uploaded_file($_FILES['file']['tmp_name']) ) {
			$file = $GLOBALS['contentDirs']['text'].$this->textId;
			move_uploaded_file($_FILES['file']['tmp_name'], $file);
			$qs = $this->makeUpdateChunkQuery($file);
			$size = filesize($file);
			$set = array('size' => $size, 'zsize' => $size/3.5,
				'headlevel' => $this->headlevel, 'lastmod' => date('Y-m-d H:i:s'));
			$qs[] = $this->db->updateQ($this->mainDbTable, $set, array('id'=>$this->textId));
			$set = array("size = percent/100 * $size");
			$key = array('text' => $this->textId);
			$qs[] = $this->db->updateQ('user_text', $set, $key);
		} elseif ( $this->request->checkbox('usecurr') ) {
			$file = $GLOBALS['contentDirs']['text'].$this->textId;
			$qs = $this->makeUpdateChunkQuery($file);
		}
		return $qs;
	}


	protected function makeUpdateChunkQuery($file) {
		require_once 'include/headerextract.php';
		require_once 'include/replace.php';
		$q = '';
		$pref = $this->request->value('partpref');
		foreach (makeDbRows($file, $this->headlevel) as $row) {
			$name = $row[2];
			if ( !empty($pref) && preg_match('/^[\dIXVLC.]+$/', $name) ) {
				$name = $pref .' '. $name;
			}
			$name = strtr($name, array('_'=>''));
			$name = $this->db->escape(my_replace($name));
			$q .= "($this->textId, $row[0], $row[1], '$name', $row[3], $row[4]), ";
		}
		$qs = array();
		$qs[] = $this->db->deleteQ('header', array('text' => $this->textId));
		if ( !empty($q) ) {
			$qs[] = 'INSERT /*p*/header (`text`, `nr`, `level`, `name`, `fpos`, `linecnt`) VALUES '.rtrim($q, ' ,');
		}
		return $qs;
	}


	protected function makeUpdateInfoQueries() {
		global $contentDirs;

		$qs = array();
		$this->tcontent = str_replace("\r", '', $this->tcontent);
		if ($this->replace) {
			require_once 'include/replace.php';
			$this->tcontent = my_replace($this->tcontent);
			if ( strpos($this->tcontent, '"') !== false ) {
				$this->addMessage('Вероятна грешка: останала е непроменена кавичка (").', true);
				file_put_contents('./log/error',
					"Кавичка при доп. информация за $this->textId\n", FILE_APPEND);
			}
		}
		$file = $contentDirs['text-info'] . $this->textId;
		$bak = $contentDirs['oldtext-info'] . $this->textId .'-'. time();
		if ( file_exists($file) ) copy($file, $bak);
		file_put_contents($file, $this->tcontent);
		CacheManager::clearCache("text-info$this->textId");
		return $qs;
	}


	protected function makeUpdateAnnoQueries() {
		global $contentDirs;

		$qs = array();
		$this->tcontent = str_replace("\r", '', $this->tcontent);
		if ($this->replace) {
			require_once 'include/replace.php';
			$this->tcontent = my_replace($this->tcontent);
			if ( strpos($this->tcontent, '"') !== false ) {
				$this->addMessage('Вероятна грешка: останала е непроменена кавичка (").', true);
				file_put_contents('./log/error.log',
					"Кавичка при анотация към $this->textId\n", FILE_APPEND);
			}
		}
		$file = $contentDirs['text-anno'] . $this->textId;
		$bak = $contentDirs['oldtext-anno'] . $this->textId .'-'. time();
		if ( file_exists($file) ) copy($file, $bak);
		file_put_contents($file, $this->tcontent);
		CacheManager::clearCache("text-anno$this->textId");
		return $qs;
	}


	protected function makeEditTextForm() {
		$this->title .= ' на текст';
		$this->initTextData();
		$author = $this->makeAuthorLink($this->nauthor);
		$personsEdit = '';
		if ( $this->mode == 'full' ) {
			$this->addJs( $this->makePersonJs() );
			$authors = $this->makePersonInput(1);
			$translators = $this->makePersonInput(2);
			$personsEdit = <<<EOS
	<table style="margin-bottom:1em"><tr valign="top">
	<td>
		<label for="author0">Автор(и):</label><br />
		$authors
		<p>[<a href="javascript:void(0)" onclick="addRow('author')">Още един</a>]</p>
	</td><td>
		<label for="translator0">Преводач(и):</label><br />
		$translators
		<p>[<a href="javascript:void(0)" onclick="addRow('translator')">Още един</a>]</p>
	</td>
	</tr></table>
EOS;
		} else {
			$url = $this->request->addUrlQuery('mode', 'full');
			$personsEdit = "<p style='text-align:right'><a href='$url'>Редактиране на автор и преводач</a></p>";
		}
		$opts = array('0' => 'Без разделяне',
			'1' => '1', '2' => '2', '3' => '3', '4' => '4');
		$headlevel = $this->out->selectBox('headlevel', '', $opts);
		$partpref = $this->out->textField('partpref', '', '', 20);
		$usecurr = $this->out->checkbox('usecurr', '', false, 'Ползване на сегашния файл');
		$file = $this->out->fileField('file', '', 60);
		$contentInput = <<<EOS
	<label for="file">Файл със съдържанието на произведението:</label>
	$file<br />
	$usecurr<br />
	<label for="headlevel">Ниво на заглавията за разделяне на текста:</label>
	$headlevel<br />
	<label for="partpref">Представка за заглавия-числа:</label>
	$partpref
EOS;
		$formBegin = $this->makeFormBegin();
		$formEnd = $this->makeFormEnd();
		if ( empty($author) ) $author = 'неизвестен автор';
		$toText = !empty($this->textId)  ?
			"<p>„<a href='$this->root/text/$this->textId'>".
			"$this->ttitle</a>“ от $author</p>" : '';
		$mainElements = $this->makeEditTextMainElements();
		$userInput = $this->makeUserInput();
		return <<<EOS
$toText

$formBegin
	$personsEdit

	$mainElements<br />
	$contentInput<br />
	$userInput<br />
$formEnd
EOS;
	}


	protected function makeEditTextMainElements() {
		if ($this->textonly) return '';
		$series = $this->makeSeriesInput();
		$type = $this->out->selectBox('type', '', $GLOBALS['types'], $this->type);
		$title = $this->out->textField('title', '', $this->ttitle, 60);
		$orig_title = $this->out->textField('orig_title', '', $this->orig_title, 60);
		$subtitle = $this->out->textField('subtitle', '', $this->subtitle, 60);
		$orig_subtitle = $this->out->textField('orig_subtitle', '', $this->orig_subtitle, 60);
		$sernr = $this->out->textField('sernr', '', $this->sernr, 2);
		$year = $this->out->textField('year', '', $this->year, 4);
		$trans_year = $this->out->textField('trans_year', '', $this->trans_year, 4);
		$year2 = $this->out->textField('year2', '', $this->year2, 4);
		$trans_year2 = $this->out->textField('trans_year2', '', $this->trans_year2, 4);
		$langs = $GLOBALS['langs'];
		$langs[''] = '(Неизвестен)';
		$lang = $this->out->selectBox('lang', '', $langs, $this->tlang);
		$olang = $this->out->selectBox('orig_lang', '', $langs, $this->orig_lang);
		$collection = $this->out->checkbox('collection', '', $this->collection, 'Колективен сборник');
		$copy = $this->out->checkbox('copy', '', $this->copy, 'Важи авторско право');
		return <<<EOS
	<label for="title">Заглавие:</label>
	$title<br />
	<label for="orig_title">Ориг. заглавие:</label>
	$orig_title<br />
	<label for="subtitle">Подзаглавие:</label>
	$subtitle<br />
	<label for="orig_subtitle">Ориг. подзаглавие:</label>
	$orig_subtitle<br />
	<label for="series">Поредица:</label>
	$series &nbsp;
	<label for="sernr">Пореден номер в поредицата:</label>
	$sernr<br />
	<label for="lang">Език:</label>
	$lang &nbsp;
	<label for="orig_lang">Оригинален език:</label>
	$olang<br />
	<label for="year">Година на написване/излизане:</label>
	{$year}–$year2 &nbsp;
	<label for="trans_year">Година на превод:</label>
	{$trans_year}–$trans_year2<br />
	<label for="type">Вид:</label>
	$type &nbsp;
	$collection<br />
	$copy
EOS;
	}


	protected function makeEditChunkForm() {
		$this->title .= ' на текстова част';
		$this->initChunkData();
		$formBegin = $this->makeFormBegin();
		$formEnd = $this->makeFormEnd();
		$author = implode(',', $this->nauthor);
		$author = $this->makeAuthorLink($author);
		if ( empty($author) ) $author = 'неизвестен автор';
		$contentInput = $this->withText ? $this->makeTextarea() : '';
		$otitle = $this->orig_title != $this->ttitle && !empty($this->orig_title)
			? "($this->orig_title)" : '';
		$ctitle = $this->out->textField('ctitle', '', $this->ctitle, 30);
		return <<<EOS

<p>„<a href="$this->root/text/$this->textId/$this->chunkId">{$this->ttitle}</a>“
$otitle от $author</p>
<p style="text-align:right"><a href="$this->root/help/edit">Съвети за редактирането</a></p>

$formBegin
	<label for="ctitle">Заглавие:</label>
	$ctitle&nbsp;
	<label for="chunkId">Номер:</label> $this->chunkId<br />
	$contentInput<br />
$formEnd
EOS;
	}


	protected function makeEditInfoForm() {
		$this->title .= ' допълнителна информация за текст';
		$this->initChunkData();
		$formBegin = $this->makeFormBegin();
		$formEnd = $this->makeFormEnd();
		$author = implode(',', $this->nauthor);
		$author = $this->makeAuthorLink($author);
		if ( empty($author) ) $author = 'неизвестен автор';
		$contentInput = $this->makeTextarea();
		$otitle = $this->orig_title != $this->ttitle && !empty($this->orig_title)
			? "($this->orig_title)" : '';
		return <<<EOS

<p>„<a href="$this->root/text/$this->textId/$this->chunkId">{$this->ttitle}</a>“
$otitle от $author</p>
<p style="text-align:right"><a href="$this->root/help/edit">Съвети за редактирането</a></p>
$formBegin
	$contentInput<br />
$formEnd
EOS;
	}


	protected function makeEditAnnoForm() {
		$this->title .= ' анотация към текст';
		$this->initChunkData();
		$formBegin = $this->makeFormBegin();
		$formEnd = $this->makeFormEnd();
		$author = implode(',', $this->nauthor);
		$author = $this->makeAuthorLink($author);
		if ( empty($author) ) $author = 'неизвестен автор';
		$contentInput = $this->makeTextarea();
		$otitle = $this->orig_title != $this->ttitle && !empty($this->orig_title)
			? "($this->orig_title)" : '';
		return <<<EOS

<p>„<a href="$this->root/text/$this->textId/$this->chunkId">{$this->ttitle}</a>“
$otitle от $author</p>
<p style="text-align:right"><a href="$this->root/help/edit">Съвети за редактирането</a></p>
$formBegin
	$contentInput<br />
$formEnd
EOS;
	}


	protected function makeTextarea() {
		$content = $this->out->textarea('content', '', $this->tcontent, 25, 90);
		$replace = $this->out->checkbox('replace', '', $this->replace,
			'Оправяне на кавички и тирета');
		return <<<EOS
	<label for="content">Съдържание:</label><br />
	$content<br />
	$replace
EOS;
	}


	protected function makeFormBegin() {
		$textId = $this->out->hiddenField('textId', $this->textId);
		$obj = $this->out->hiddenField('obj', $this->obj);
		$mode = $this->out->hiddenField('mode', $this->mode);
		$chunkId = $this->out->hiddenField('chunkId', $this->chunkId);
		return <<<EOS

<form action="{FACTION}" method="post" enctype="multipart/form-data">
<div>
	$textId
	$chunkId
	$obj
	$mode
EOS;
	}


	protected function makeFormEnd() {
		$showagain = $this->out->checkbox('showagain', '', $this->showagain,
			'Показване на формуляра отново');
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS
	$showagain<br />
	$submit
</div>
</form>
EOS;
	}


	protected function makePersonInput($ind) {
		$keys = array(1 => 'author', 2 => 'translator');
		$key = $keys[$ind];
		$js = "\npersons['$key'] = {";
		$dbkey = array("(role & $ind)");
		foreach ($this->db->getObjects('person', null, null, $dbkey) as $id => $name) {
			if ( empty($name) ) { $name = '(Неизвестен автор)'; }
			$js .= "\n\t$id: '$name',";
		}
		$js = rtrim($js, ',') . "\n}; // end of array persons['$key']\n";
		$this->addJs($js);
		$dbkey = array('text' => $this->textId);
		$q = $this->db->selectQ($key.'_of', $dbkey, $key, 'pos');
		$addRowFunc = create_function('$row',
			'return "addRow(\''.$key.'\', $row['.$key.']); ";');
		$load = $this->db->iterateOverResult($q, $addRowFunc);
		if ( empty($load) ) { $load = "addRow('$key', 0); "; }
		$is_changed = $this->out->hiddenField("is_changed[$key]", 0);
		$o = <<<EOS
	<table><tbody id="t$key"><tbody></table>
	$is_changed
	$this->scriptStart
		$load
	$this->scriptEnd
EOS;
		return $o;
	}


	protected function makePersonJs() {
		return <<<EOS

		var persons = new Array();

		function addRow(key, ind) {
			var tbody = document.getElementById("t"+key);
			var curInd = tbody.rows.length;
			var newRow = tbody.insertRow(curInd);
			var cells = new Array();
			for (var i = 0; i < 1; i++) { cells[i] = newRow.insertCell(i); }
			i = 0;
			cells[i++].innerHTML = makePersonSelectMenu(key, curInd, ind);
		}

		function makePersonSelectMenu(key, curInd, selInd) {
			var ext = key == 'author' ? 'loadSeries(this.value); ' : '';
			var o = '<select id="'+key+curInd+'" name="'+key+'[]" onchange="'+ ext +
				'this.form.elements[\'is_changed['+key+']\'].value=1;">'+
				'<option value="">(Избор)</option>';
			for (var i in persons[key]) {
				var sel = i == selInd ? ' selected="selected"' : '';
				o += '<option value="'+i+'"'+sel+'>'+persons[key][i]+'</option>';
			}
			o += '</select>';
			return o;
		}
EOS;
	}

	protected function makeSeriesInput() {
		$js = <<<EOS

		function loadSeries(author) {
			var select = document.getElementById("series");
			if ( typeof(ser[author]) == 'undefined' ) {
				select.options.length = 1;
				return;
			}
			var aser = ser[author];
			for (var i=0; i < aser.length; i++) {
				var selected = aser[i][0] == $this->series;
				var opt = new Option(aser[i][1], aser[i][0], false, selected);
				select.options[i+1] = opt;
			}
			select.options.length = aser.length+1;
		}

		var ser = new Array();
EOS;
		$query = 'SELECT aof.author, s.id, s.name
			FROM /*p*/ser_author_of aof
			LEFT JOIN /*p*/series s ON (aof.series = s.id)
			ORDER BY aof.author, s.name';
		$this->curInd = 0;
		$this->curAuthor = 0;
		$js .= $this->db->iterateOverResult($query, 'makeSeriesJsItem', $this);
		$this->addJs($js);
		$opts = array(0 => '(Не е част от поредица)');
		$series = $this->out->selectBox('series', '', $opts);
		return <<<EOS

	$series
	$this->scriptStart
		loadSeries({$this->author[0]});
	$this->scriptEnd
EOS;
	}


	public function makeSeriesJsItem($dbrow) {
		extract($dbrow);
		if ( empty($id) ) { continue; }
		$js = '';
		if ($this->curAuthor != $author) {
			$js .= "\nser[$author]=new Array();";
			$this->curAuthor = $author;
			$this->curInd = 0;
		}
		$js .= "\nser[$author][$this->curInd]=new Array($id, '$name');";
		$this->curInd++;
		return $js;
	}


	protected function makeUserInput() {
		$user = $this->out->textField('user', '', '', 20, 100);
		return <<<EOS

	<label for="user" title="Потребител(и), обработил(и) текста">Потребител(и):</label>
	$user
EOS;
	}


	protected function initTextData() {
		if ( $this->withText && !empty($this->tcontent) ) {
			$this->initChunkData();
		}
		$result = $this->db->query("SELECT title ttitle, orig_title, orig_lang,
		subtitle, orig_subtitle, trans_year, trans_year2, t.year, year2,
		type, series, sernr, collection, copy,
		GROUP_CONCAT(aof.author) author, GROUP_CONCAT(a.name) nauthor
		FROM /*p*/$this->mainDbTable t
		LEFT JOIN /*p*/author_of aof ON (t.id = aof.text)
		LEFT JOIN /*p*/person a ON (aof.author = a.id)
		WHERE t.id = $this->textId GROUP BY t.id");
		$data = $this->db->fetchAssoc($result);
		extract2object($data, $this);
		if ( empty($data) ) {
			$this->copy = true;
			$this->nauthor = '';
		} else {
			$this->collection = $this->db->s2b($this->collection);
		}
		$this->author = explode(',', (string) $this->author);
	}


	protected function initChunkData() {
		global $contentDirs;
		switch ($this->obj) {
		case 'info' : $file = $contentDirs['text-info'] .$this->textId; break;
		case 'anno' : $file = $contentDirs['text-anno'] .$this->textId; break;
		default: $file = $contentDirs['text'] ."$this->textId-$this->chunkId"; break;
		}
		$this->tcontent = @file_get_contents($file);
		$sel = array('title ttitle', 'orig_title');
		$res = $this->db->select('text', array('id' => $this->textId), $sel);
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) { $this->ttitle = ''; }
		extract2object($data, $this);

		$query = "SELECT a.name FROM /*p*/author_of aof
			LEFT JOIN /*p*/person a ON aof.author = a.id
			WHERE aof.text = $this->textId";
		$this->nauthor = array();
		$this->db->iterateOverResult($query, 'addNAuthor', $this);
	}

	public function addNAuthor($dbrow) {
		$this->nauthor[] = $dbrow['name'];
	}

}
?>
