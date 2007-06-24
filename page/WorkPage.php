<?php
class WorkPage extends Page {

	protected $FF_COMMENT = 'comment', $FF_EDIT_COMMENT = 'editComment';
	protected $tabs = array('Самостоятелна подготовка', 'Работа в екип');
	protected $tabImgs = array('singleuser', 'multiuser');
	protected $tabImgAlts = array('сам', 'екип');
	protected $statuses = array('Планира се сканиране', 'Сканира се',
		'Сканирано е', 'Редактира се', 'Готово е за добавяне');
	protected $imgs = array('0', '25', '50', '75', '100');
	protected $progressBarWidth = '20', $maxScanStatus = 2;
	const DEF_TMPFILE = 'http://';
	// copied from MediaWiki
	protected $fileBlacklist = array(
		# HTML may contain cookie-stealing JavaScript and web bugs
		'html', 'htm', 'js', 'jsb',
		# PHP scripts may execute arbitrary code on the server
		'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
		# Other types that may be interpreted by some servers
		'shtml', 'jhtml', 'pl', 'py', 'cgi');


	public function __construct() {
		parent::__construct();
		$this->action = 'work';
		$this->title = 'Подготовка на нови произведения';
		$this->mainDbTable = 'work';
		$this->suplDbTable = 'work_multi';
		$this->tmpDir = 'to-do/';
		$this->subaction = $this->request->param(1);
		$this->entry = (int) $this->request->value('entry', 0, 2);
		$this->workType = (int) $this->request->value('workType', 0, 3);
		$this->btitle = $this->request->value('title');
		$this->author = $this->request->value('author');
		$this->status = (int) $this->request->value('status');
		$this->progress = (int) $this->request->value('progress');
		if ($this->progress > 100) $this->progress = 100;
		$this->frozen = $this->request->checkbox('frozen');
		$this->delete = $this->request->checkbox('delete');
		$this->scanuser = (int) $this->request->value('user', $this->user->id);
		$this->comment = $this->request->value($this->FF_COMMENT);
		$this->comment = strtr($this->comment, array("\r"=>''));
		$this->tmpfiles = $this->request->value('tmpfiles', self::DEF_TMPFILE);
		$this->tfsize = $this->request->value('tfsize');
		$this->editComment = $this->request->value($this->FF_EDIT_COMMENT);
		$this->uplfile = $this->makeUploadedFileName();
		$this->uplfile = $this->escapeBlackListedExt($this->uplfile);
		$this->form = $this->request->value('form');
		$this->date = date('Y-m-d H:i:s');
	}


	protected function processSubmission() {
		if ( !empty($this->entry) &&
				!$this->thisUserCanEditEntry($this->entry, $this->workType) ) {
			$this->addMessage('Нямате право да редактирате този запис.', true);
			return $this->makeLists();
		}
		switch ($this->workType) {
		case 0: return $this->updateMainUserData();
		case 1: return $this->updateMultiUserData();
		}
	}


	protected function updateMainUserData() {
		$key = array('id' => $this->entry);
		if ($this->delete) {
			$this->db->delete($this->mainDbTable, $key, 1);
			if ( $this->isMultiUser($this->workType) ) {
				$this->db->delete($this->suplDbTable, array('pid'=>$this->entry));
			}
			$this->addMessage("Произведението „{$this->btitle}“ беше махнато от списъка.");
			if ( $this->isSingleUser($this->workType) ) {
				$this->handleUpload();
			}
			return $this->makeLists();
		}
		if ( empty($this->btitle) ) {
			$this->addMessage('Не сте посочили заглавие на произведението.', true);
			return $this->makeForm();
		}
		require_once 'include/replace.php';
		$this->btitle = my_replace($this->btitle);
		$id = $this->entry == 0 ? $this->db->autoincrementId($this->mainDbTable) : $this->entry;
		$set = array('id' => $id, 'type' => $this->workType,
			'title'=>$this->btitle,
			'author'=> strtr($this->author, array(';'=>',')),
			'user'=>$this->scanuser, 'comment' => $this->comment,
			'date'=>$this->date, 'frozen' => $this->frozen,
			'status'=>$this->status, 'progress' => $this->progress,
			'tmpfiles' => $this->tmpfiles, 'tfsize' => $this->tfsize);
		if ( $this->isSingleUser($this->workType) ) {
			if ( $this->handleUpload() && !empty($this->uplfile) ) {
				$set['uplfile'] = $this->uplfile;
			}
		}
		$this->db->insertOrUpdate($this->mainDbTable, $set, $this->entry);
		$msg = $this->entry == 0
			? 'Произведението беше добавено в списъка с подготвяните.'
			: 'Данните за произведението бяха обновени.';
		$this->addMessage($msg);
		return $this->makeLists();
	}


	protected function updateMultiUserData() {
		if ( $this->thisUserCanDeleteEntry() && $this->form != 'edit' ) {
			return $this->updateMainUserData();
		}
		return $this->updateMultiUserDataForEdit();
	}


	protected function updateMultiUserDataForEdit() {
		$pkey = array('id' => $this->entry);
		$key = array('pid' => $this->entry, 'user' => $this->user->id);
		if ( empty($this->editComment) ) {
			$this->addMessage('Въвеждането на коментар е задължително.', true);
			return $this->buildContent();
		}
		require_once 'include/replace.php';
		$this->editComment = my_replace($this->editComment);
		$set = array('pid' => $this->entry, 'user' => $this->user->id,
			'comment' => $this->editComment, 'date' => $this->date,
			'progress' => $this->progress, 'frozen' => $this->frozen);
		if ( $this->handleUpload() && !empty($this->uplfile) ) {
			$set['uplfile'] = $this->uplfile;
		}
		if ($this->db->exists($this->suplDbTable, $key)) {
			$this->db->update($this->suplDbTable, $set, $key);
			$msg = 'Данните за редакцията ви бяха обновени.';
		} else {
			$this->db->insert($this->suplDbTable, $set);
			$msg = 'Току-що се включихте в редакцията на произведението.';
			$this->informScanUser($this->entry);
		}
		$this->addMessage($msg);
		// update main entry
		$set = array('date' => $this->date, 'status' => $this->isEditDone() ? 4 : 3);
		$this->db->update($this->mainDbTable, $set, $pkey);
		return $this->makeLists();
	}


	protected function handleUpload() {
		$tmpfile = $_FILES['file']['tmp_name'];
		if ( is_uploaded_file($tmpfile) ) {
			$dest = $this->tmpDir . $this->uplfile;
			if ( file_exists($dest) ) { $dest .= '.1'; }
			if ( !move_uploaded_file($tmpfile, $dest) ) {
				$this->addMessage("Файлът не успя да бъде качен. Опитайте пак!", true);
				return;
			}
			$this->addMessage("Файлът беше качен и през идните дни ще бъде добавен в библиотеката. Благодаря ви за положения труд!");
			$log = "$this->date\t$this->scanuser ({$this->user->username})\t$dest\t$this->btitle\t$this->author\n";
			file_put_contents('log/todo', $log, FILE_APPEND);
			$mailpage = PageManager::buildPage('mail');
			$fields = array('mailSubject'=>'Ново произведение за Моята библиотека',
				'mailMessage' => "$log\n$dest");
			$mailpage->setFields($fields);
			$mailpage->execute();
		}
		return true;
	}


	protected function makeUploadedFileName() {
		$filename = @$_FILES['file']['name'];
		if ( empty($filename) ) return '';
		return $this->entry .'-'. $this->user->username .'-'.
			str_replace(array('"', "'"), '', $filename);
	}


	protected function buildContent() {
		if ($this->subaction == 'edit' && $this->userCanAddEntry()) {
			$this->initData();
			return $this->makeForm();
		}
		return $this->makeLists();
	}


	protected function makeLists() {
		return $this->makePageHelp() . $this->makeNewEntryLink() .
			$this->makeWorkList() . $this->makeContribList();
	}


	public function makeWorkList($limit = 0) {
		$q = "SELECT w.*, DATE(date) ddate, u.username, u.email, u.allowemail
			FROM /*p*/$this->mainDbTable w
			LEFT JOIN /*p*/". User::MAIN_DB_TABLE ." u ON (w.user = u.id)
			ORDER BY date DESC, w.id DESC";
		if ($limit > 0) $q .= " LIMIT $limit";
		$this->rowclass = 'even';
		$this->tooltips = '';
		$l = $this->db->iterateOverResult($q, 'makeWorkListItem', $this, true);
		if ( empty($l) ) {
			return '<p style="text-align:center"><strong>Няма подготвящи се произведения.</strong></p>';
		}
		return <<<EOS

<table class="content" cellpadding="0" cellspacing="0" rules="all">
<thead>
	<tr>
		<th>Дата</th>
		<th></th>
		<th>Заглавие</th>
		<th>Автор</th>
		<th>Етап на работата</th>
		<th>Потребител</th>
	</tr>
</thead>
<tbody>$l
</tbody>
</table>
EOS;
	}


	public function makeWorkListItem($dbrow) {
		extract($dbrow);
		$author = strtr($author, array(', '=>','));
		$author = $this->makeAuthorLink($author);
		$userlink = $this->makeUserLinkWithEmail($username, $email, $allowemail);
		$info = '';
		if ( !empty($comment) ) {
			$comment = strtr($comment, array("\n"=>'', "\r"=>''));
			$info = $this->out->image($this->skin->image('info'),  '', $comment);
		}
		$title = $this->userCanEditEntry($user, $type)
			? "<a href='$this->root/$this->action/edit/$id' title='Към страницата за редактиране'><em>$title</em></a>" : "<em>$title</em>";
		$this->rowclass = $this->out->nextRowClass($this->rowclass);
		if ($progress > 0) {
			$progressbar = $this->makeProgressBar($progress);
			$st = '';
		} else {
			$img = $this->skin->image('b'.$this->imgs[$status].'p');
			$st = "<img src='$img' alt='{$this->imgs[$status]}%' />" .
				$this->statuses[$status];
			$progressbar = '';
		}
		if ( $this->db->s2b($frozen) ) {
			$sfrozen = '<span title="Подготовката е замразена">(замразена)</span>';
			$extraclass = ' frozen';
		} else {
			$sfrozen = $extraclass = '';
		}
		$img = $this->makeTabImg($type);
		if ( $this->isMultiUser($type) ) {
			$mdata = $this->getMultiEditData($id);
			foreach ($mdata as $muser => $data) {
				if ($muser == $user) continue;
				$ulink = $this->makeUserLinkWithEmail($data['username'],
					$data['email'], $data['allowemail']);
				if ($data['frozen']) {
					$ulink = "<span class='frozen'>$ulink</span>";
				}
				$userlink .= ', '. $ulink;
			}
			if ( $status == $this->maxScanStatus && empty($mdata) ) {
				$userlink .= ' (<strong>очакват се редактори</strong>)';
			}
		}
		return <<<EOS

	<tr class="$this->rowclass$extraclass">
		<td title="$date">$ddate</td>
		<td>$img</td>
		<td>$info $title</td>
		<td>$author</td>
		<td>$st
			$progressbar
			$sfrozen
		</td>
		<td>$userlink</td>
	</tr>
EOS;
	}


	protected function makeTabImg($type) {
		return $this->out->image('{IMGDIR}'.$this->tabImgs[$type].'.png',
			$this->tabImgAlts[$type], $this->tabs[$type]);
	}


	protected function makeProgressBar($progressInPerc) {
		$bar = str_repeat(' ', $this->progressBarWidth);
		$perc = $progressInPerc .'%';
		$bar = substr_replace($bar, $perc, $this->progressBarWidth/2-1, strlen($perc));
		$curProgressWidth = ceil($this->progressBarWidth * $progressInPerc / 100);
		// done bar end
		$bar = substr_replace($bar, '</span>', $curProgressWidth, 0);
		$bar = strtr($bar, array(' '=>'&nbsp;'));
		return "<pre><span class='progressbar'><span class='done'>$bar</span></pre>";
	}


	protected function makeNewEntryLink() {
		$newLink = $this->userCanAddEntry()
			? "<a href='$this->root/$this->action/edit'>Подготовка на ново произведение</a>" : '';
		return "<p style='text-align:center; margin:1em 0'>$newLink</p>";

	}


	protected function makeForm() {
		$this->title .= ' — '.(empty($this->entry) ? 'Добавяне' : 'Редактиране');
		$helpTop = empty($this->entry) ? $this->makeAddEntryHelp() : '';
		$tabs = '';
		foreach ($this->tabs as $type => $text) {
			$text = $this->makeTabImg($type) . $text;
			$class = '';
			if ($this->workType == $type) {
				$class = ' selected';
			} elseif ($this->thisUserCanDeleteEntry()) {
				$text = "<a href='$this->root/$this->action/edit/$this->entry/$type'>$text</a>";
			}
			$tabs .= "\n<div class='tab$class'>$text</div>";
		}
		if ( $this->isSingleUser($this->workType) ) {
			$editFields = $this->makeSingleUserEditFields();
			$extra = '';
		} else {
			$editFields = $this->makeMultiUserEditFields();
			$extra = $this->isScanDone() ? $this->makeMultiEditInput() : '';
		}
		if ( $this->thisUserCanDeleteEntry() ) {
			$title = $this->out->textField('title', '', $this->btitle, 50);
			$author = $this->out->textField('author', '', $this->author, 50, 255,
				0, 'Ако авторите са няколко, ги разделете със запетаи');
			$comment = $this->out->textarea($this->FF_COMMENT, '', $this->comment, 3, 60);
			$delete = empty($this->entry) ? ''
				: '<div class="error" style="margin-bottom:1em">'.
				$this->out->checkbox('delete', '', '', false, 0, 'Изтриване на записа') .
				' (напр., ако произведението вече е добавено в библиотеката)</div>';
			$button = $this->makeSubmitButton();
		} else {
			$title = $this->btitle;
			$author = $this->author;
			$comment = $this->comment;
			$button = $delete = '';
		}
		$helpBot = $this->isSingleUser($this->workType) ?
			$this->makeSingleUserHelp() : $this->makeMultiUserHelp();
		$scanuser = $this->out->hiddenField('user', $this->scanuser);
		$entry = $this->out->hiddenField('entry', $this->entry);
		$workType = $this->out->hiddenField('workType', $this->workType);
		return <<<EOS

$helpTop
<p class="non-graphic">След формуляра ще намерите <a href="#helpBottom">още помощна информация</a>.</p>
<div class="tabbedpane" style="margin:1em auto">$tabs
<div class="tabbedpanebody">
<form action="{FACTION}" method="post" enctype="multipart/form-data">
<div style="margin:1em 0.3em 0.4em 0.3em">
	$scanuser
	$entry
	$workType
	<table><tr>
		<td style="width:6em"><label for="title">Заглавие:</label></td>
		<td>$title</td>
	</tr><tr>
		<td><label for="author">Автор:</label></td>
		<td>$author</td>
	</tr>
	<tr style="vertical-align:top">
		<td><label for="$this->FF_COMMENT">Коментар:</label></td>
		<td>$comment</td>
	</tr>
	$editFields
	</table>
	$delete
	$button
</div>
</form>
$extra
</div>
</div>
<div id="helpBottom">
$helpBot
</div>
EOS;
	}


	protected function makeSubmitButton() {
		$submit = $this->out->submitButton('Съхраняване');
		return <<<EOS
		$submit
	&nbsp; <a href="$this->root/$this->action" title="Към основния списък">Отказ</a>
EOS;
	}

	protected function makeSingleUserEditFields() {
		$status = '';
		foreach ($this->statuses as $code => $text) {
			$sel = $this->status == $code ? ' selected="selected"' : '';
			$img = $this->skin->image('b'.$this->imgs[$code].'p');
			$status .= "\n\t<option value='$code'$sel style='background:url($img) no-repeat left; padding-left:18px;'>$text</option>";
		}
		$progress = $this->out->textField('progress', '', $this->progress, 2, 3);
		$frozen = $this->out->checkbox('frozen', '', $this->frozen,
			'Подготовката е спряна за известно време');
		$file = $this->out->fileField('file', '', 50);
		return <<<EOS
	<tr>
		<td><label for="status">Етап:</label></td>
		<td><select name="status" id="status">$status</select>
		&nbsp; или &nbsp;
		$progress<label for="progress">%</label><br />
		$frozen
		</td>
	</tr><tr>
		<td><label for="file">Файл:</label></td>
		<td>$file</td>
	</tr>
EOS;
	}


	protected function makeMultiUserEditFields() {
		$scanInput = $this->makeMultiScanInput();
		return <<<EOS
	<tr>
	<td colspan="2">
	$scanInput
	</td>
	</tr>
EOS;
	}


	protected function makeMultiScanInput() {
		$frozenLabel = 'Сканирането е спряно за известно време';
		$cstatus = $this->status > $this->maxScanStatus
			? $this->maxScanStatus : $this->status;
		if ( $this->thisUserCanDeleteEntry() ) {
			if ( !empty($this->multidata) ) {
				$status = $this->statuses[$cstatus] .
					$this->out->hiddenField('status', $this->status);
				$frozen = '';
			} else {
				$status = '';
				foreach ($this->statuses as $code => $text) {
					if ($code > $this->maxScanStatus) break;
					$sel = $cstatus == $code ? ' selected="selected"' : '';
					$img = $this->skin->image('b'.$this->imgs[$code].'p');
					$status .= "\n\t<option value='$code'$sel style='background:url($img) no-repeat left; padding-left:18px;'>$text</option>";
				}
				$status = "<select name='status' id='status'>$status</select>";
				$frozen = $this->out->checkbox('frozen', '', $this->frozen, $frozenLabel);
			}
			$tmpfiles = $this->out->textField('tmpfiles', '', $this->tmpfiles, 50, 255);
			$tmpfiles .= ' &nbsp; '.$this->out->label('Размер:', 'tfsize') .
				$this->out->textField('tfsize', '', $this->tfsize, 2, 4) .
				'<acronym title="Мегабайта">MB</acronym>';
		} else {
			$status = $this->statuses[$cstatus];
			$frozen = $this->frozen ? "($frozenLabel)" : '';
			$tmpfiles = '';
		}
		$udata = User::getDataById($this->scanuser);
		$ulink = $this->makeUserLink($udata['username']);
		$flink = $this->tmpfiles == self::DEF_TMPFILE ? ''
			: "<a href='$this->tmpfiles'>$this->tmpfiles</a>" .
			($this->tfsize > 0 ? " ($this->tfsize&nbsp;мегабайта)" : '');
		return <<<EOS
	<fieldset>
		<legend>Сканиране и разпознаване ($ulink)</legend>
		<div>
		<label for="status">Етап:</label>
  		$status
		$frozen
		</div>
		<div>
		<label for="tmpfiles">Междинни файлове:</label>
		$tmpfiles $flink
		</div>
	</fieldset>
EOS;
	}


	protected function makeMultiEditInput() {
		$editorList = $this->makeEditorList();
		$myContrib = $this->makeMultiEditMyInput();
		return <<<EOS
	<fieldset>
		<legend>Редактиране</legend>
		$editorList
		$myContrib
	</fieldset>
EOS;
	}


	protected function makeMultiEditMyInput() {
		$msg = '';
		if ( empty($this->multidata[$this->user->id]) ) {
			$comment = $progress = '';
			$frozen = false;
			$msg = '<p>Вие също можете да се включите в редактирането на текста.</p>';
		} else {
			extract( $this->multidata[$this->user->id] );
		}
		$ulink = $this->makeUserLink($this->user->username);
		$button = $this->makeSubmitButton();
		$scanuser = $this->out->hiddenField('user', $this->scanuser);
		$entry = $this->out->hiddenField('entry', $this->entry);
		$workType = $this->out->hiddenField('workType', $this->workType);
		$form = $this->out->hiddenField('form', 'edit');
		$comment = $this->out->textarea($this->FF_EDIT_COMMENT, '', $comment, 3, 60);
		$progress = $this->out->textField('progress', '', $progress, 2, 3);
		$frozen = $this->out->checkbox('frozen', '', $this->frozen,
			'Редакцията е спряна за известно време');
		$file = $this->out->fileField('file', '', 50);
		return <<<EOS

<form action="{FACTION}" method="post" enctype="multipart/form-data">
	<fieldset>
		<legend>Моят принос ($ulink)</legend>
		$msg
	$scanuser
	$entry
	$workType
	$form
	<table>
	<tr style="vertical-align:top">
		<td><label for="$this->FF_EDIT_COMMENT">Коментар:</label></td>
		<td>$comment</td>
	<tr>
		<td><label for="progress">Прогрес:</label></td>
		<td>$progress<label for="progress">%</label> $frozen</td>
	</tr><tr>
		<td><label for="file">Файл:</label>
		<td>$file</td>
	</tr>
	</table>
	$button
	</fieldset>
</form>
EOS;
	}


	protected function makeEditorList() {
		if ( empty($this->multidata) ) {
			return '<p>Все още никой не се е включил в редакцията на сканирания текст.</p>';
		}
		$l = $class = '';
		foreach ($this->multidata as $edata) {
			extract($edata);
			$class = $this->out->nextRowClass($class);
			$ulink = $this->makeUserLink($username);
			if ( !empty($uplfile) ) {
				$url = $this->rootd .'/'. $this->tmpDir . $uplfile;
				$comment .= " (<a href='$url' title='Качен от $username файл'>Качен файл</a>)";
			}
			$progressbar = $this->makeProgressBar($progress);
			if ($frozen) {
				$class .= ' frozen';
				$progressbar .= ' (замразена)';
			}
			$l .= <<<EOS

		<tr class="$class">
			<td>$date</td>
			<td>$ulink</td>
			<td>$comment</td>
			<td>$progressbar</td>
		</tr>
EOS;
		}
		return <<<EOS

	<table class="content">
	<caption>Следните потребители обработват сканирания текст:</caption>
	<thead>
	<tr>
		<th>Дата</th>
		<th>Потребител</th>
		<th>Коментар</th>
		<th>Прогрес</th>
	</tr>
	</thead>
	<tbody>$l
	</tbody>
	</table>
EOS;
	}


	protected function makePageHelp() {
		$ext = $this->user->isAnon() ? "е необходимо първо да се
<a href='$this->root/register' title='към страницата за регистрация'>регистрирате</a>
(не се притеснявайте, ще ви отнеме най-много 10–20 секунди, колкото и бавно да
пишете). След това се върнете на тази страница и" : '';
		$teamicon = $this->makeTabImg(1);
		return <<<EOS

<p>Тук можете да разгледате списък на произведенията, които се подготвят за добавяне в библиотеката.</p>
<p>За да се включите в подготовката на нови текстове, $ext последвайте връзката „Подготовка на ново произведение“. В случай че нямате възможност сами да сканирате текстове, можете да се присъедините към редактирането на заглавията, отбелязани с иконката $teamicon (може и да няма такива).</p>
EOS;
	}


	protected function makeAddEntryHelp() {
		return <<<EOS

<p>Чрез долния формуляр можете да добавите ново произведение към <a href="$this->root/$this->action">списъка с подготвяните</a>.</p>
<p>Имате възможност за избор между „{$this->tabs[0]}“ (сами ще обработите целия текст) или „{$this->tabs[1]}“ (вие ще сканирате текста, а други потребители ще имат възможността да се включат в редактирането му).</p>
<p>Въведете заглавието и автора и накрая посочете на какъв етап се намира подготовката. Ако още не сте започнали сканирането, изберете „Планира се сканиране“.</p>
<p>През следващите дни винаги можете да промените етапа, на който се намира подготовката на произведението. За тази цел, в основния списък, заглавието ще представлява връзка към страницата за редактиране.</p>
EOS;
	}


	protected function makeSingleUserHelp() {
		$sendFile = $this->makeSendFileHelp();
		return <<<EOS

<p>На тази страница можете да променяте данните за произведението.
Най-често ще се налага да обновявате етапа, на който се намира подготовката. Възможно е да посочите прогреса на подготовката и чрез процент, в случай че операциите сканиране, разпознаване и редактиране се извършват едновременно.</p>
<p>Ако подготовката на произведението е замразена, това може да се посочи, като се отметне полето „Подготовката е спряна за известно време“.</p>
$sendFile
EOS;
	}


	protected function makeMultiUserHelp() {
		$help = '';
		if ( $this->thisUserCanDeleteEntry() )
			$help .= $this->makeMultiUserScanHelp();
		if ( $this->isScanDone() )
			$help .= $this->makeMultiUserEditHelp();
		return $help;
	}


	protected function makeMultiUserScanHelp() {
		return <<<EOS

<h2>Сканиране и разпознаване</h2>
<p>След като сканирате произведението, е нужно да качите суровите файлове някъде в интернет и да посочите адреса в полето „Междинни файлове“. Така останалите потребители ще могат да се включат в редакцията на текста. Полезно е да въведете и големината на файловете в полето „Размер“.</p>
EOS;
	}

	protected function makeMultiUserEditHelp() {
		$sendFile = $this->makeSendFileHelp();
		return <<<EOS

<h2>Редактиране</h2>
<p>Преди да се включите в редактирането на текста, изтеглете междинните файлове от адреса, посочен в раздела „Сканиране и разпознаване“.</p>
<h3>Коментар и прогрес</h3>
<p>В полето „Коментар“ посочете каква част от текста сте се захванали да обработите, за да могат останалите редактори да си изберат нещо друго. <em>Няма нужда едни и същи страници да се редактират от повече от един човек!</em></p>
<p>В хода на редакцията е добре да обновявате прогреса на подготовката. В случай че сте решили да направите малка почивка, отметнете полето „Подготовката е спряна за известно време“.</p>
<h3>Пращане на готовия файл</h3>
$sendFile
EOS;
	}

	protected function makeSendFileHelp() {
		$tmpDir = "<a href='$this->rootd/$this->tmpDir'>$this->rootd/$this->tmpDir</a>";
		$adminMail = $this->out->obfuscateEmail(ADMIN_EMAIL);
		return <<<EOS

<p>Когато сте готови с текста, в полето „Файл“ изберете файла с произведението (като натиснете бутона до полето ще ви се отвори прозорче за избор).</p>
<p>Има ограничение от <strong>2</strong> мебибайта за големината на файла, затова първо го компресирайте. Ако и това не помогне, опитайте да го разделите на части или пък ми го пратете по електронната поща — $adminMail.</p>
<p><strong>Важно:</strong> Ако след съхранението не видите съобщението „Файлът беше качен“, значи е станал някакъв фал при качването на файла. В такъв случай опитайте да го пратите отново.</p>
<p>Ще съм ви благодарен, ако включвате и всякаква допълнителна информация както за текста, така и за самия файл.</p>
<p>За произведението е добре да има данни относно хартиеното издание и за преводача, ако е превод. За файла е хубаво да се знае кой го е сканирал и редактирал.</p>
<p>Тъй като обичам свободата, предпочитам следните <em>свободни</em> документови формати:</p>
<ul>
	<li><a href="http://en.wikipedia.org/wiki/Plain_text" title="http://en.wikipedia.org/wiki/Plain_text — чист текст">чист текст</a> — обикновено файловете са с разширение <strong>.txt</strong>;</li>
	<li><a href="http://en.wikipedia.org/wiki/OpenDocument" title="http://en.wikipedia.org/wiki/OpenDocument — OpenDocument">OpenDocument</a> — разширение <strong>.odt</strong>;</li>
	<li><a href="http://en.wikipedia.org/wiki/HTML" title="http://en.wikipedia.org/wiki/HTML — HTML"><acronym title='Hypertext Markup Language'>HTML</acronym></a>.</li>
</ul>
<p>В краен случай бих приел и файлове в <a href="http://en.wikipedia.org/wiki/Rich_Text_Format" title="http://en.wikipedia.org/wiki/Rich_Text_Format — Rich Text Format">Rich Text Format</a>. Това не е свободен формат, но поне спецификацията му е известна на обществото.</p>
<p>След като качите файла, той се записва в директорията $tmpDir на сървъра.</p>
EOS;
	}


	protected function makeContribList() {
		$this->mb = 1 << 20; // = 2^20
		$this->rowclass = '';
		$q = "SELECT DISTINCT u.username, COUNT(ut.user) count, SUM(ut.size) size
		FROM /*p*/user_text ut
		LEFT JOIN /*p*/".User::MAIN_DB_TABLE." u ON ut.user = u.id
		GROUP BY ut.user ORDER BY size DESC";
		$list = $this->db->iterateOverResult($q, 'makeContribListItem', $this);
		if ( empty($list) ) return '';
		return <<<EOS

	<table class="content">
	<caption>Следните потребители са сканирали или редактирали текстове за библиотеката:</caption>
		<colgroup>
			<col />
			<col align="right" />
			<col align="right" />
		</colgroup>
	<thead>
	<tr>
		<th>Потребител</th>
		<th title="Размер на обработените произведения в мебибайта">Размер (в <acronym title="Мебибайта">MiB</acronym>)</th>
		<th title="Брой на обработените произведения">Брой</th>
	</tr>
	</thead>
	<tbody>$list
	</tbody>
	</table>
EOS;
	}


	public function makeContribListItem($dbrow) {
		extract($dbrow);
		$this->rowclass = $this->out->nextRowClass($this->rowclass);
		$ulink = $this->makeUserLink($username);
		$s = formatNumber($size / $this->mb);
		return "\n\t<tr class='$this->rowclass'><td>$ulink</td><td>$s</td><td>$count</td></tr>";
	}


	protected function initData() {
		$sel = array('id', 'type workType', 'title btitle', 'author',
			'user scanuser', 'comment', 'DATE(date) date', 'status', 'progress',
			'frozen', 'tmpfiles', 'tfsize');
		$key = array('id' => $this->entry);
		$res = $this->db->select($this->mainDbTable, $key, $sel);
		if ( $this->db->numRows($res) == 0 ) { return array(); }
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) { return false; }
		if ( $this->thisUserCanDeleteEntry() &&
				!is_null( $this->request->value('workType', null, 3) ) ) {
			unset($data['workType']);
		}
		$this->multidata = $this->getMultiEditData($data['id']);
		unset($data['id']);
		extract2object($data, $this);
		$this->frozen = $this->db->s2b($this->frozen);
		return true;
	}


	protected function getMultiEditData($mainId) {
		$q = "SELECT m.*, DATE(m.date) date, u.username, u.email, u.allowemail
		FROM /*p*/$this->suplDbTable m
		LEFT JOIN /*p*/". User::MAIN_DB_TABLE ." u ON m.user = u.id
		WHERE pid = $mainId ORDER BY m.date DESC";
		$this->_medata = array();
		$this->db->iterateOverResult($q, 'addMultiEditData', $this);
		return $this->_medata;
	}


	public function addMultiEditData($dbrow) {
		$dbrow['frozen'] = $this->db->s2b($dbrow['frozen']);
		$this->_medata[$dbrow['user']] = $dbrow;
	}


	protected function isScanDone() {
		return $this->status >= $this->maxScanStatus;
	}


	protected function isEditDone() {
		$key = array('pid' => $this->entry, 'progress' => array('<', 100));
		return !$this->db->exists($this->suplDbTable, $key);
	}


	protected function isSingleUser($type) { return $type == 0; }
	protected function isMultiUser($type) { return $type == 1; }

	protected function thisUserCanEditEntry($entry, $type) {
		if ($this->user->isSuperUser() || $type == 1) return true;
		$key = array('id' => $entry, 'user' => $this->user->id);
		return $this->db->exists($this->mainDbTable, $key);
	}

	protected function userCanEditEntry($user, $type = 0) {
		return $this->user->isSuperUser() || $user == $this->user->id
			|| ($type == 1 && $this->userCanAddEntry());
	}

	protected function thisUserCanDeleteEntry() {
		if ($this->user->isSuperUser() || empty($this->entry)) return true;
		if ( isset($this->_tucde) ) return $this->_tucde;
		$key = array('id' => $this->entry, 'user' => $this->user->id);
		return $this->_tucde = $this->db->exists($this->mainDbTable, $key);
	}

	protected function userCanDeleteEntry($user) {
		return $this->user->isSuperUser() || $user == $this->scanuser;
	}


	protected function userCanAddEntry() {
		return !$this->user->isAnon();
	}


	protected function informScanUser($entry) {
		$res = $this->db->select($this->mainDbTable, array('id'=>$entry));
		extract( $this->db->fetchAssoc($res) );

		$sel = array('realname', 'email');
		$res = $this->db->select(User::MAIN_DB_TABLE, array('id'=>$user), $sel);
		extract( $this->db->fetchAssoc($res) );
		if ( empty($email) ) return true;

		$mailpage = PageManager::buildPage('mail');
		$msg = <<<EOS
Нов потребител се присъедини към редактирането на „{$title}“ от $author.

$this->purl/work/edit/$entry

Моята библиотека
EOS;
		$fields = array('mailTo' => "$realname <$email>",
			'mailSubject' => "$this->sitename: Нов редактор на ваш текст",
			'mailMessage' => $msg);
		$mailpage->setFields($fields);
		return $mailpage->execute();
	}


	protected function escapeBlackListedExt($filename) {
		$fext = ltrim(strrchr($this->uplfile, '.'), '.');
		foreach ($this->fileBlacklist as $blext) {
			if ($fext == $blext) {
				$filename = preg_replace("/$fext$/", '$0.txt', $filename);
				break;
			}
		}
		return $filename;
	}
}
?>
