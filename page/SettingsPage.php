<?php

class SettingsPage extends RegisterPage {

	protected $optKeys = array('skin', 'nav', 'mainpage');
	protected $mpOpts = array(
		'newtitles' => 'Добавени произведения',
		'worktitles' => 'Подготвяни произведения',
		'forumnews' => 'Съобщения от форума',
		'readercomments' => 'Читателски мнения',
		'sitenews' => 'Новини относно библиотеката',
		'liternews' => 'Литературни новини',
	);
	protected $defEcnt = 10;
	protected $nonEmptyFields = array();


	public function __construct() {
		parent::__construct();
		$this->action = 'settings';
		$this->title = 'Настройки';
		$this->isAnon = $this->user->isAnon();
		$this->userId = $this->user->id;
		$this->allowemail = $this->request->checkbox('allowemail');
		$this->opts['skin'] = $this->request->value('skin', 'orange');
		$this->opts['nav'] = $this->request->value('navpos', 'right');
		$this->opts['mainpage'] = $this->request->value('mainpage', 's');
		$this->expire = $this->request->value('expire', '30');
		$this->optKeys = array_merge($this->optKeys, array_keys($this->mpOpts));
		$pos = 0;
		foreach ($this->mpOpts as $key => $_) {
			$pos++;
			$sopts = (array) $this->request->value($key);
			// show section: checkbox, by default 1
			$this->opts[$key][0] = isset($sopts[0]) ? 1 : (empty($sopts) ? 1 : 0);
			// section position
			$this->opts[$key][1] = isset($sopts[1]) ? $sopts[1] : $pos;
			// number of entries in the section
			$this->opts[$key][2] = isset($sopts[2]) ? $sopts[2] : $this->defEcnt;
		}
		$this->tabindex = 1;
	}


	protected function processSubmission() {
		if ($this->isAnon) { return $this->processAnonUserRequest(); }
		return $this->processRegUserRequest();
	}


	protected function processRegUserRequest() {
		$err = $this->validateInput();
		$this->attempt++;
		if ( !empty($err) ) {
			$this->addMessage($err, true);
			return $this->makeRegUserForm();
		}
		$set = array('realname' => $this->realname,
			'lastname' => ltrim(strrchr($this->realname, ' ')),
			'email' => $this->email, 'allowemail' => $this->allowemail,
			'news' => $this->news, 'opts' => $this->makeOptionsOutput());
		if ( !empty($this->username) && $this->user->username != $this->username ) {
			if ( $this->userExists() ) {
				return $this->makeRegUserForm();
			}
			$set['username'] = $this->username;
		}
		if ( $this->emailExists($this->user->username) ) {
			return $this->makeRegUserForm();
		}
		if ( !empty($this->password) ) { // change password
			$set['password']= $this->db->encodePasswordDB($this->password);
		}
		$key = array('id' => $this->userId);
		if ( $this->db->update(User::MAIN_DB_TABLE, $set, $key) === false ) {
			$this->addMessage('Имаше някакъв проблем при промяната на данните.', true);
		} else {
			$this->addMessage("Данните ви бяха променени.");
			if ( isset($set['username']) ) {
				$this->addMessage("<strong>Обърнете внимание, че отсега нататък потребителското ви име е „{$set['username']}“. Старото ви име „{$this->user->username}“ е вече невалидно.</strong>");
				$this->user->username = $set['username'];
			}
			$this->setNewUserOptions();
		}
		return $this->makeRegUserForm();
	}


	protected function processAnonUserRequest() {
		$opts = $this->makeOptionsOutput();
		$expire = time() + ONEDAYSECS * $this->expire;
		setcookie(OPTS_COOKIE, $opts, $expire, Setup::setting('path'));
		$this->setNewUserOptions();
		$this->addMessage('Настройките ви бяха съхранени.');
		return $this->makeAnonUserForm();
	}


	protected function buildContent() {
		if ($this->isAnon) {
			$this->initAnonUserData();
			return $this->makeAnonUserForm();
		}
		$this->initRegUserData();
		return $this->makeRegUserForm();
	}


	protected function makeRegUserForm() {
		$this->addAltStylesheets();
		$formBegin = $this->makeFormBegin();
		$attempt = $this->out->hiddenField('attempt', $this->attempt);
		$username = $this->out->textField('username', '', $this->username, 25, 60, $this->tabindex++);
		$password = $this->out->passField('password', '', '', 25, 40, $this->tabindex++);
		$passwordRe = $this->out->passField('passwordRe', '', '', 25, 40, $this->tabindex++);
		$realname = $this->out->textField('realname', '', $this->realname, 25, 60, $this->tabindex++);
		$email = $this->out->textField('email', '', $this->email, 25, 60, $this->tabindex++);
		$allowemail = $this->out->checkbox('allowemail', '', $this->allowemail,
			'Разрешаване на писма от другите потребители', '', $this->tabindex++);
		$common = $this->makeCommonInput();
		$news = $this->out->checkbox('news', '', $this->news,
			'Получаване на месечно новинарско писмо', '', $this->tabindex++);
		$formEnd = $this->makeFormEnd();
		return <<<EOS

$formBegin
	$attempt
	<legend>Данни и настройки</legend>
	<table>
	<tr>
		<td class="fieldname-left"><label for="username">Потребителско име:</label></td>
		<td>$username</td>
	</tr><tr>
		<td class="fieldname-left"><label for="password">Нова парола<a id="nb1" href="#n1">*</a>:</label></td>
		<td>$password</td>
	</tr><tr>
		<td class="fieldname-left"><label for="passwordRe">Новата парола още веднъж:</label></td>
		<td>$passwordRe</td>
	</tr><tr>
		<td class="fieldname-left"><label for="realname">Истинско име:</label></td>
		<td>$realname</td>
	</tr><tr>
		<td class="fieldname-left"><label for="email">Е-поща:</label></td>
		<td>$email</td>
	</tr>
	<tr>
		<td colspan="2">$allowemail</td>
	</tr>$common
	<tr>
		<td colspan="2">$news</td>
	</tr>
$formEnd
<p><a id="n1" href="#nb1">*</a> <em>Нова парола</em> — въведете нова парола само ако искате да смените сегашната си.</p>
EOS;
	}


	protected function makeAnonUserForm() {
		$this->addAltStylesheets();
		$formBegin = $this->makeFormBegin();
		$common = $this->makeCommonInput();
		$expire = $this->makeExpireInput($this->tabindex++);
		$formEnd = $this->makeFormEnd();
		return <<<EOS

$formBegin
	<legend>Настройки</legend>
	<table>$common
	<tr>
		<td><label for="expire" title="Срок на валидност на настройките">Срок
		на валидност<a id="nb1" href="#n1">*</a>:</label></td>
		<td>$expire</td>
	</tr>
$formEnd
<p><a id="n1" href="#nb1">*</a> Тъй като не сте регистриран, единственият начин да бъдат запомнени настройките, е тяхното съхраняване в паметта на браузъра ви под формата на бисквитка (англ. cookie). Чрез <em>срока на валидност</em> можете да определите колко време да се съхранява тази бисквитка.</p>
EOS;
	}


	protected function makeFormBegin() {
		return <<<EOS
<form action="{FACTION}" method="post">
<fieldset style="width:26em; margin:1em auto" align="center">
EOS;
	}

	protected function makeFormEnd() {
		$submit = $this->out->submitButton('Съхраняване', '', $this->tabindex++);
		return <<<EOS
	<tr>
		<td colspan="2" align="center">$submit</td>
	</tr>
	</table>
</fieldset>
</form>
EOS;
	}


	protected function makeCommonInput() {
		$skin = $this->makeSkinInput($this->tabindex++);
		$navpos = $this->makeNavPosInput($this->tabindex++);
		$mainpage = $this->makeMainPageInput($this->tabindex++);
		$mpExtra = $this->makeMainPageExtraInput();
		return <<<EOS

	<tr>
		<td class="fieldname-left"><label for="skin">Облик:</label></td>
		<td>$skin</td>
	</tr><tr>
		<td class="fieldname-left"><label for="navpos">Навигация:</label></td>
		<td>$navpos</td>
	</tr><tr>
		<td class="fieldname-left"><label for="mainpage">Начална страница:</label></td>
		<td>$mainpage</td>
	</tr>
	<tr>
		<td colspan="2" align="center">$mpExtra</td>
	</tr>
EOS;
	}


	protected function makeSkinInput($tabindex) {
		return $this->out->selectBox('skin', '', Setup::setting('skins'),
			$this->opts['skin'], $tabindex, 'onchange="skin=this.value; changeStyleSheet()"');
	}


	protected function makeNavPosInput($tabindex) {
		return $this->out->selectBox('navpos', '', Setup::setting('navpos'),
			$this->opts['nav'], $tabindex, 'onchange="nav=this.value; changeStyleSheet()"');
	}


	protected function makeMainPageInput($tabindex) {
		$opts = array('s'=>'Статична', 'd'=>'Динамична');
		return $this->out->selectBox('mainpage', '', $opts,
			$this->opts['mainpage'], $tabindex);
	}


	protected function makeExpireInput($tabindex) {
		$opts = array('7' => 'Една седмица', '30' => 'Един месец',
			'90' => 'Три месеца', '180' => 'Шест месеца',
			'365' => 'Една година', '1095' => 'Три години');
		return $this->out->selectBox('expire', '', $opts, $this->expire, $tabindex);
	}


	protected function makeMainPageExtraInput() {
		$ta = array();
		$rclass = '';
		foreach ($this->mpOpts as $key => $title) {
			$show = $this->out->checkbox($key.'[0]', '', $this->opts[$key][0] == '1');
			$pos = $this->out->textField($key.'[1]', '', $this->opts[$key][1], 1, 2);
			$ecnt = $this->out->textField($key.'[2]', '', $this->opts[$key][2], 2, 2);
			$rclass = $this->out->nextRowClass($rclass);
			$ta[ $this->opts[$key][1] ] = <<<EOS

		<tr class="$rclass">
			<th style="white-space: nowrap">$title</th>
			<td>$show</td>
			<td>$pos</td>
			<td>$ecnt</td>
		</tr>
EOS;
		}
		ksort($ta);
		$t = implode('', $ta);
		return <<<EOS

	<table>
		<caption><a href="{$this->root}/dynMain">Динамична начална страница</a></caption>
		<thead>
		<tr>
			<th>Раздел</th>
			<th>Показване</th>
			<th>Позиция</th>
			<th>Записи</th>
		</tr>
		</thead>
		<tbody>$t
		</tbody>
	</table>
EOS;
	}


	protected function makeOptionsOutput() {
		$o = '';
		foreach ($this->optKeys as $key) {
			$o .= $key .'=' . (is_array($this->opts[$key])
				? implode(',', $this->opts[$key]) : $this->opts[$key]) .';';
		}
		return rtrim($o, ';');
	}


	protected function initRegUserData() {
		$sel = array('username', 'realname', 'email', 'allowemail', 'news', 'opts');
		$key = array('id' => $this->userId);
		$res = $this->db->select(User::MAIN_DB_TABLE, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			$this->addMessage("Не съществува потребител с номер $this->userId.", true);
			return;
		}
		$this->opts = array_merge($this->opts, User::extractOptions($data['opts']));
		unset($data['opts']);
		extract2object($data, $this);
		$this->allowemail = $this->db->s2b($this->allowemail);
		$this->news = $this->db->s2b($this->news);
	}


	protected function initAnonUserData() {
		$this->opts = array_merge($this->opts, $this->user->options());
	}


	protected function setNewUserOptions() {
		foreach ($this->optKeys as $key) {
			$this->user->setOption($key, $this->opts[$key]);
		}
		$this->user->updateSession();
	}


	protected function addAltStylesheets() {
		$ss = '';
		foreach (Setup::setting('skins') as $skin => $_) {
			foreach (Setup::setting('navpos') as $nav => $_) {
				$ss .= "\n\t<link rel='alternate stylesheet' type='text/css' href='$this->root/css/main?$skin-$nav' title='$skin-$nav' />";
			}
		}
		$this->addHeadContent($ss);
		$js = <<<EOS

	var nav = "{$this->opts['nav']}", skin = "{$this->opts['skin']}";
	function changeStyleSheet() {
		setActiveStyleSheet(skin +"-"+ nav);
	}
EOS;
		$this->addJs($js);
	}

}
?>
