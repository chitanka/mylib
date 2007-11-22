<?php
class WorkPage extends Page {

	const
		DEF_TMPFILE = 'http://',
		DB_TABLE = DBT_WORK, DB_TABLE2 = DBT_WORK_MULTI,
		FF_COMMENT = 'comment', FF_EDIT_COMMENT = 'editComment',
		FF_VIEW_LIST = 'vl',
		MAX_SCAN_STATUS = 2;
	protected
		$tabs = array('Самостоятелна подготовка', 'Работа в екип'),
		$tabImgs = array('singleuser', 'multiuser'),
		$tabImgAlts = array('сам', 'екип'),
		$statuses = array('Планира се сканиране', 'Сканира се',
			'Сканирано е', 'Редактира се', 'Готово е за добавяне'),
		$viewLists = array(
			'work' => 'списъка на подготвяните произведения',
			'contrib' => 'списъка на помощниците'),
		$defViewList = 'work',
		$imgs = array('0', '25', '50', '75', '100'),
		$progressBarWidth = '20',

		// copied from MediaWiki
		$fileBlacklist = array(
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
		$this->tmpDir = 'to-do/';
		$this->subaction = $this->request->value('sa', '', 1);
		$this->entry = (int) $this->request->value('entry', 0, 2);
		$this->workType = (int) $this->request->value('workType', 0, 3);
		$this->btitle = $this->request->value('title');
		$this->author = $this->request->value('author');
		$this->status = (int) $this->request->value('status');
		$this->progress = normInt($this->request->value('progress'), 100, 0);
		$this->frozen = $this->request->checkbox('frozen');
		$this->delete = $this->request->checkbox('delete');
		$this->scanuser = (int) $this->request->value('user', $this->user->id);
		$this->comment = $this->request->value(self::FF_COMMENT);
		$this->comment = strtr($this->comment, array("\r"=>''));
		$this->tmpfiles = $this->request->value('tmpfiles', self::DEF_TMPFILE);
		$this->tfsize = $this->request->value('tfsize');
		$this->editComment = $this->request->value(self::FF_EDIT_COMMENT);
		$this->uplfile = $this->makeUploadedFileName();
		$this->uplfile = $this->escapeBlackListedExt($this->uplfile);
		$this->form = $this->request->value('form');
		$this->bypassExisting = (int) $this->request->value('bypass', 0);
		$this->date = date('Y-m-d H:i:s');
		$this->rowclass = null;
		$this->showProgressbar = true;
		$this->viewList = $this->request->value(self::FF_VIEW_LIST,
			$this->defViewList, null, $this->viewLists);

		$this->multidata = array();
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
			$this->db->delete(self::DB_TABLE, $key, 1);
			if ( $this->isMultiUser($this->workType) ) {
				$this->db->delete(self::DB_TABLE2, array('pid'=>$this->entry));
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

		if ($this->entry == 0) { // check if there is such text in the library
			$work = Work::newFromTitle($this->btitle);
			if ( !empty($work) && !$this->bypassExisting ) {
				$wl = $this->makeSimpleTextLink($work->title, $work->id);
				$this->addMessage('В библиотеката вече съществува произведение'.
					$this->makeFromAuthorSuffix($work->author_name) .
					" със същото заглавие — <div style='text-align:center; margin:0.5em'>$wl.</div>", true);
				$this->addMessage('Повторното съхраняване ще добави вашия запис въпреки горното предупреждение.');
				$this->bypassExisting = 1;
				return $this->makeForm();
			}
		}

		$id = $this->entry == 0 ? $this->db->autoincrementId(self::DB_TABLE) : $this->entry;
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
		$this->db->update(self::DB_TABLE, $set, $this->entry);
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
		if ($this->db->exists(self::DB_TABLE2, $key)) {
			$this->db->update(self::DB_TABLE2, $set, $key);
			$msg = 'Данните за редакцията ви бяха обновени.';
		} else {
			$this->db->insert(self::DB_TABLE2, $set);
			$msg = 'Току-що се включихте в редакцията на произведението.';
			$this->informScanUser($this->entry);
		}
		$this->addMessage($msg);
		// update main entry
		$set = array('date' => $this->date, 'status' => $this->isEditDone() ? 4 : 3);
		$this->db->update(self::DB_TABLE, $set, $pkey);
		return $this->makeLists();
	}


	protected function handleUpload() {
		$tmpfile = $_FILES['file']['tmp_name'];
		if ( !is_uploaded_file($tmpfile) ) {
			return false;
		}
		$dest = $this->tmpDir . $this->uplfile;
		if ( file_exists($dest) ) { $dest .= '.1'; }
		if ( !move_uploaded_file($tmpfile, $dest) ) {
			$this->addMessage("Файлът не успя да бъде качен. Опитайте пак!", true);
			return false;
		}
		$this->addMessage("Файлът беше качен и през идните дни ще бъде добавен в библиотеката. Благодаря ви за положения труд!");
		$log = "$this->date\t$this->scanuser ({$this->user->username})\t$dest\t$this->btitle\t$this->author\n";
		file_put_contents('log/todo', $log, FILE_APPEND);
		$mailpage = PageManager::buildPage('mail');
		$fields = array('mailSubject'=>'Ново произведение за Моята библиотека',
			'mailMessage' => "$log\n$dest");
		$mailpage->setFields($fields);
		$mailpage->execute();
		return true;
	}


	protected function makeUploadedFileName() {
		$filename = $this->request->fileName('file');
		if ( empty($filename) ) {
			return '';
		}
		return $this->entry .'-'. $this->user->username .'-'.
			str_replace(array('"', "'"), '', $filename);
	}


	protected function buildContent() {
		$this->addScript('table-sortable.js');
		$this->addJs("\n".'addOnloadHook(sortables_init);');
		if ($this->subaction == 'edit' && $this->userCanAddEntry()) {
			$this->initData();
			return $this->makeForm();
		}
		$this->addRssLink();
		return $this->makeLists();
	}


	protected function makeLists() {
		$listFunc = 'make' . ucfirst($this->viewList) . 'List';
		return $this->makePageHelp() . $this->makeNewEntryLink() .
			$this->makeViewListSelector() . $this->$listFunc();
	}


	protected function makeViewListSelector() {
		$label = $this->out->label('Показване на: ', self::FF_VIEW_LIST);
		$box = $this->out->selectBox(self::FF_VIEW_LIST, '', $this->viewLists,
			$this->viewList, null, array('onchange' => 'this.form.submit()'));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<form action="{FACTION}" style="text-align:center">
<div>
$label
$box
	<noscript><div style="display:inline">$submit</div></noscript>
</div>
</form>
EOS;
	}

	public function makeWorkList($limit = 0) {
		$this->tooltips = '';
		$q = $this->makeSqlQuery($limit);
		$l = $this->db->iterateOverResult($q, 'makeWorkListItem', $this, true);
		if ( empty($l) ) {
			return '<p style="text-align:center"><strong>Няма подготвящи се произведения.</strong></p>';
		}
		return <<<EOS

<table class="content sortable" cellpadding="0" cellspacing="0">
<!--thead-->
	<tr>
		<th>Дата</th>
		<th class="unsortable"></th>
		<th>Заглавие</th>
		<th>Автор</th>
		<th>Етап на работата</th>
		<th>Потребител</th>
	</tr>
<!--/thead-->
<tbody>$l
</tbody>
</table>
EOS;
	}


	public function makeSqlQuery($limit = 0, $offset = 0, $order = null) {
		$qa = array(
			'SELECT' => 'w.*, DATE(date) ddate, u.username, u.email, u.allowemail',
			'FROM' => self::DB_TABLE. ' w',
			'LEFT JOIN' => array(
				User::DB_TABLE .' u' => 'w.user = u.id',
			),
			'ORDER BY' => 'date DESC, w.id DESC',
			'LIMIT' => array($offset, $limit)
		);
		return $this->db->extselectQ($qa);
	}


	public function makeWorkListItem($dbrow, $astable = true) {
		extract($dbrow);
		$author = strtr($author, array(', '=>','));
		$author = $this->makeAuthorLink($author);
		$userlink = $this->makeUserLinkWithEmail($username, $email, $allowemail);
		$info = empty($comment) ? ''
			: $this->makeExtraInfo($comment, isset($expandinfo) && $expandinfo);
		$title = "<em>$title</em>";
		if ( $this->userCanEditEntry($user, $type) ) {
			$params = array(self::FF_ACTION=>$this->action, 'sa'=>'edit', 'entry'=>$id);
			$title = $this->out->internLink($title, $params, 3, 'Към страницата за редактиране');
		}
		$this->rowclass = $this->out->nextRowClass($this->rowclass);
		$st = $progress > 0
			? $this->makeProgressBar($progress)
			: $this->makeStatus($status);
		$extraclass = $this->user->id == $user ? ' hilite' : '';
		if ( $this->db->s2b($frozen) ) {
			$sfrozen = '<span title="Подготовката е замразена">(замразена)</span>';
			$extraclass .= ' frozen';
		} else {
			$sfrozen = '';
		}
		$img = $this->makeTabImg($type);
		if ( $this->isMultiUser($type) ) {
			$mdata = $this->getMultiEditData($id);
			foreach ($mdata as $muser => $data) {
				$uinfo = $this->makeExtraInfo($data['comment']);
				if ($muser == $user) {
					$userlink = $uinfo .'&nbsp;'. $userlink;
					continue;
				}
				$ulink = $uinfo .'&nbsp;'. $this->makeUserLinkWithEmail($data['username'],
					$data['email'], $data['allowemail']);
				if ($data['frozen']) {
					$ulink = "<span class='frozen'>$ulink</span>";
				}
				$userlink .= ', '. $ulink;
				$extraclass .= $this->user->id == $muser ? ' hilite' : '';
			}
			if ( !empty($mdata) ) {
				if ( isset($showeditors) && $showeditors ) {
					$userlink .= $this->makeEditorList($mdata);
				}
			} else if ( $status >= self::MAX_SCAN_STATUS ) {
				$userlink .= ' (<strong>очакват се редактори</strong>)';
			}
		}
		if ($astable) {
			return <<<EOS

	<tr class="$this->rowclass$extraclass" id="e$id">
		<td class="date" title="$date">$ddate</td>
		<td>$img</td>
		<td>$info $title</td>
		<td>$author</td>
		<td>$st $sfrozen</td>
		<td>$userlink</td>
	</tr>
EOS;
		}
		$time = !isset($showtime) || $showtime ? "Дата: $date<br />" : '';
		$titlev = !isset($showtitle) || $showtitle ? $title : '';
		return <<<EOS

		<p>$time
		$img $info $titlev<br/>
		<strong>Автор:</strong> $author<br/>
		<strong>Етап:</strong> $st $sfrozen<br/>
		Подготвя се от $userlink
		</p>
EOS;
	}


	public function makeStatus($stCode) {
		return $this->makeStatusImage($stCode) . $this->statuses[$stCode];
	}


	public function makeStatusImage($stCode, $urlonly = false) {
		$url = $this->skin->image('b'.$this->imgs[$stCode].'p');
		if ($urlonly) return $url;
		return "<img src='$url' alt='{$this->imgs[$stCode]}%' />";
	}


	public function makeTabImg($type) {
		return $this->out->image('{IMGDIR}'.$this->tabImgs[$type].'.png',
			$this->tabImgAlts[$type], $this->tabs[$type]);
	}


	public function makeExtraInfo($info, $expand = false) {
		$info = strtr($info, array("\n" => '', "\r" => ''));
		return $expand
			? $info
			: $this->out->image($this->skin->image('info'),  '', $info);
	}


	public function makeProgressBar($progressInPerc) {
		$perc = $progressInPerc .'%';
		if ( !$this->showProgressbar ) return $perc;
		$bar = str_repeat(' ', $this->progressBarWidth);
		$bar = substr_replace($bar, $perc, $this->progressBarWidth/2-1, strlen($perc));
		$curProgressWidth = ceil($this->progressBarWidth * $progressInPerc / 100);
		// done bar end
		$bar = substr_replace($bar, '</span>', $curProgressWidth, 0);
		$bar = strtr($bar, array(' '=>'&nbsp;'));
		return "<pre style='display:inline'><span class='progressbar'><span class='done'>$bar</span></pre>";
	}


	protected function makeNewEntryLink() {
		if ( !$this->userCanAddEntry() ) {
			return '';
		}
		$params = array(self::FF_ACTION=>$this->action, 'sa'=>'edit');
		$link = $this->out->internLink('Подготовка на ново произведение', $params, 2);
		return "<p style='text-align:center; margin:1em 0'>$link</p>";

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
			} else if ($this->thisUserCanDeleteEntry()) {
				$params = array(self::FF_ACTION=>$this->action, 'sa'=>'edit',
					'entry'=>$this->entry, 'workType'=>$type);
				$text = $this->out->internLink($text, $params, 4);
			}
			$tabs .= "\n<div class='tab$class'>$text</div>";
		}
		if ( $this->isSingleUser($this->workType) ) {
			$editFields = $this->makeSingleUserEditFields();
			$extra = '';
		} else {
			$editFields = $this->makeMultiUserEditFields();
			#$extra = $this->isScanDone() ? $this->makeMultiEditInput() : '';
			$extra = $this->makeMultiEditInput();
		}
		if ( $this->thisUserCanDeleteEntry() ) {
			$title = $this->out->textField('title', '', $this->btitle, 50);
			$author = $this->out->textField('author', '', $this->author, 50, 255,
				0, 'Ако авторите са няколко, ги разделете със запетаи');
			$comment = $this->out->textarea(self::FF_COMMENT, '', $this->comment, 3, 60);
			$delete = empty($this->entry) ? ''
				: '<div class="error" style="margin-bottom:1em">'.
				$this->out->checkbox('delete', '', false, 'Изтриване на записа') .
				' (напр., ако произведението вече е добавено в библиотеката)</div>';
			$button = $this->makeSubmitButton();
		} else {
			$title = $this->btitle;
			$author = $this->author;
			$comment = $this->comment;
			$button = $delete = '';
		}
		$lcomment = $this->out->label('Коментар:', self::FF_COMMENT);
		$helpBot = $this->isSingleUser($this->workType) ?
			$this->makeSingleUserHelp() : $this->makeMultiUserHelp();
		$scanuser = $this->out->hiddenField('user', $this->scanuser);
		$entry = $this->out->hiddenField('entry', $this->entry);
		$workType = $this->out->hiddenField('workType', $this->workType);
		$bypass = $this->out->hiddenField('bypass', $this->bypassExisting);
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
	$bypass
	<table><tr>
		<td style="width:6em"><label for="title">Заглавие:</label></td>
		<td>$title</td>
	</tr><tr>
		<td><label for="author">Автор:</label></td>
		<td>$author</td>
	</tr>
	<tr style="vertical-align:top">
		<td>$lcomment</td>
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
		$params = array(self::FF_ACTION=>$this->action);
		$cancel = $this->out->internLink('Отказ', $params, 1, 'Към основния списък');
		return $submit .' &nbsp; '. $cancel;
	}

	protected function makeSingleUserEditFields() {
		$status = '';
		foreach ($this->statuses as $code => $text) {
			$sel = $this->status == $code ? ' selected="selected"' : '';
			$img = $this->makeStatusImage($code, true);
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
		$cstatus = $this->status > self::MAX_SCAN_STATUS
			? self::MAX_SCAN_STATUS : $this->status;
		if ( $this->thisUserCanDeleteEntry() ) {
			if ( !empty($this->multidata) ) {
				$status = $this->statuses[$cstatus] .
					$this->out->hiddenField('status', $this->status);
				$frozen = '';
			} else {
				$status = '';
				foreach ($this->statuses as $code => $text) {
					if ($code > self::MAX_SCAN_STATUS) break;
					$sel = $cstatus == $code ? ' selected="selected"' : '';
					$img = $this->makeStatusImage($code, true);
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
		$ulink = $this->makeUserLinkWithEmail($udata['username'],
			$udata['email'], $udata['allowemail']);
		$flink = $this->tmpfiles == self::DEF_TMPFILE ? ''
			: $this->out->link($this->tmpfiles) .
			($this->tfsize > 0 ? " ($this->tfsize&nbsp;MB)" : '');
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
		$comment = $this->out->textarea(self::FF_EDIT_COMMENT, '', $comment, 3, 60);
		$lcomment = $this->out->label('Коментар:', self::FF_EDIT_COMMENT);
		$progress = $this->out->textField('progress', '', $progress, 2, 3);
		$frozen = $this->out->checkbox('frozen', 'frozen_e', $this->frozen,
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
		<td>$lcomment</td>
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


	protected function makeEditorList($mdata = null) {
		fillOnEmpty($mdata, $this->multidata);
		if ( empty($mdata) ) {
			return '<p>Все още никой не се е включил в редакцията на сканирания текст.</p>';
		}
		$l = $class = '';
		foreach ($mdata as $edata) {
			extract($edata);
			$class = $this->out->nextRowClass($class);
			$ulink = $this->makeUserLinkWithEmail($username, $email, $allowemail);
			if ( !empty($uplfile) ) {
				$url = $this->rootd .'/'. $this->tmpDir . $uplfile;
				$comment .= ' ('.$this->out->link($url, 'Качен файл', "Качен от $username файл").')';
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

	<table class="content sortable">
	<caption>Следните потребители обработват сканирания текст:</caption>
	<!--thead-->
	<tr>
		<th>Дата</th>
		<th>Потребител</th>
		<th>Коментар</th>
		<th>Прогрес</th>
	</tr>
	<!--/thead-->
	<tbody>$l
	</tbody>
	</table>
EOS;
	}


	protected function makePageHelp() {
		$reg = $this->out->internLink('регистрирате',
			array(self::FF_ACTION=>'register'), 1, 'Към страницата за регистрация');
		$ext = $this->user->isAnon() ? "е необходимо първо да се $reg (не се притеснявайте, ще ви отнеме най-много 10–20 секунди, колкото и бавно да пишете). След това се върнете на тази страница и" : '';
		$teamicon = $this->makeTabImg(1);
		return <<<EOS

<p>Тук можете да разгледате списък на произведенията, които се подготвят за добавяне в библиотеката.</p>
<p>За да се включите в подготовката на нови текстове, $ext последвайте връзката „Подготовка на ново произведение“. В случай че нямате възможност сами да сканирате текстове, можете да се присъедините към редактирането на заглавията, отбелязани с иконката $teamicon (може и да няма такива).</p>
EOS;
	}


	protected function makeAddEntryHelp() {
		$mainlink = $this->out->internLink('списъка с подготвяните',
			array(self::FF_ACTION=>$this->action), 1);
		return <<<EOS

<p>Чрез долния формуляр можете да добавите ново произведение към $mainlink.</p>
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
		$tmpDir = $this->out->link($this->rootd.'/'.$this->tmpDir);
		$adminMail = $this->out->obfuscateEmail(ADMIN_EMAIL);
		$maxUploadSizeInMiB = int_b2m( ini_bytes( ini_get('upload_max_filesize') ) );
		return <<<EOS

<p>Когато сте готови с текста, в полето „Файл“ изберете файла с произведението (като натиснете бутона до полето ще ви се отвори прозорче за избор).</p>
<p>Има ограничение от <strong>$maxUploadSizeInMiB</strong> мебибайта за големината на файла, затова първо го компресирайте. Ако и това не помогне, опитайте да го разделите на части или пък ми го пратете по електронната поща — $adminMail.</p>
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
		$qa = array(
			'SELECT' => 'u.username, COUNT(ut.user) count, SUM(ut.size) size',
			'FROM' => DBT_USER_TEXT .' ut',
			'LEFT JOIN' => array(User::DB_TABLE .' u' => 'ut.user = u.id'),
			'GROUP BY' => 'ut.user',
			'ORDER BY' => 'size DESC',
		);
		$q = $this->db->extselectQ($qa);
		$list = $this->db->iterateOverResult($q, 'makeContribListItem', $this);
		if ( empty($list) ) {
			return '';
		}
		return <<<EOS

	<table class="content sortable">
	<caption>Следните потребители са сканирали или редактирали текстове за библиотеката:</caption>
		<colgroup>
			<col />
			<col align="right" />
			<col align="right" />
		</colgroup>
	<!--thead-->
	<tr>
		<th>Потребител</th>
		<th title="Размер на обработените произведения в мебибайта">Размер (в <acronym title="Мебибайта">MiB</acronym>)</th>
		<th title="Брой на обработените произведения">Брой</th>
	</tr>
	<!--/thead-->
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
		$res = $this->db->select(self::DB_TABLE, $key, $sel);
		if ( $this->db->numRows($res) == 0 ) {
			return array();
		}
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			return false;
		}
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


	public function getMultiEditData($mainId) {
		$qa = array(
			'SELECT' => 'm.*, DATE(m.date) date, u.username, u.email, u.allowemail',
			'FROM' => self::DB_TABLE2 .' m',
			'LEFT JOIN' => array(User::DB_TABLE .' u' => 'm.user = u.id'),
			'WHERE' => array('pid' => $mainId),
			'ORDER BY' => 'm.date DESC',
		);
		$q = $this->db->extselectQ($qa);
		$this->_medata = array();
		$this->db->iterateOverResult($q, 'addMultiEditData', $this);
		return $this->_medata;
	}


	public function addMultiEditData($dbrow) {
		$dbrow['frozen'] = $this->db->s2b($dbrow['frozen']);
		$this->_medata[$dbrow['user']] = $dbrow;
	}


	protected function isScanDone() {
		return $this->status >= self::MAX_SCAN_STATUS;
	}


	protected function isEditDone() {
		$key = array('pid' => $this->entry, 'progress' => array('<', 100));
		return !$this->db->exists(self::DB_TABLE2, $key);
	}


	public function isSingleUser($type) { return $type == 0; }
	public function isMultiUser($type) { return $type == 1; }

	public function thisUserCanEditEntry($entry, $type) {
		if ($this->user->isSuperUser() || $type == 1) return true;
		$key = array('id' => $entry, 'user' => $this->user->id);
		return $this->db->exists(self::DB_TABLE, $key);
	}

	public function userCanEditEntry($user, $type = 0) {
		return $this->user->isSuperUser() || $user == $this->user->id
			|| ($type == 1 && $this->userCanAddEntry());
	}

	public function thisUserCanDeleteEntry() {
		if ($this->user->isSuperUser() || empty($this->entry)) return true;
		if ( isset($this->_tucde) ) return $this->_tucde;
		$key = array('id' => $this->entry, 'user' => $this->user->id);
		return $this->_tucde = $this->db->exists(self::DB_TABLE, $key);
	}

	public function userCanDeleteEntry($user) {
		return $this->user->isSuperUser() || $user == $this->scanuser;
	}


	public function userCanAddEntry() {
		return !$this->user->isAnon();
	}


	protected function informScanUser($entry) {
		$res = $this->db->select(self::DB_TABLE, array('id'=>$entry));
		extract( $this->db->fetchAssoc($res) );

		$sel = array('realname', 'email');
		$res = $this->db->select(User::DB_TABLE, array('id'=>$user), $sel);
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
		// remove leading dots
		$filename = ltrim($filename, '.');
		return $filename;
	}
}
