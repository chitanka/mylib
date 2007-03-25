<?php

class SettingsPage extends Page {

	protected $optKeys = array('skin', 'nav', 'mainpage');

	public function __construct() {
		parent::__construct();
		$this->action = 'settings';
		$this->title = 'Настройки';
		$this->isAnon = $this->user->isAnon();
		$this->userId = $this->user->id;
		$this->username = $this->request->value('username');
		$this->oldusername = $this->request->value('oldusername');
		$this->realname = $this->request->value('realname');
		$this->password = $this->request->value('password');
		$this->passwordRe = $this->request->value('passwordRe');
		$this->email = $this->request->value('email', '');
		$this->opts['skin'] = $this->request->value('skin', 'orange');
		$this->opts['nav'] = $this->request->value('navpos', 'right');
		$this->opts['mainpage'] = $this->request->value('mainpage', 's');
		$this->news = (int) $this->request->checkbox('news');
		$this->expire = $this->request->value('expire', '30');
		$this->tabindex = 1;
	}


	protected function processSubmission() {
		if ($this->isAnon) { return $this->processAnonUserRequest(); }
		return $this->processRegUserRequest();
	}


	protected function processRegUserRequest() {
		$set = array('realname' => $this->realname,
			'lastname' => ltrim(strrchr($this->realname, ' ')),
			'email' => $this->email, 'news' => $this->news,
			'opts' => $this->makeOptionsOutput());
		if ( $this->oldusername != $this->username ) {
			$key = array('username' => $this->username);
			if ( $this->db->exists(User::MAIN_DB_TABLE, $key) ) {
				$this->addMessage("За съжаление името „{$this->username}“ вече е заето.", true);
				return $this->buildContent();
			}
			$set['username'] = $this->username;
		}
		if ( !empty($this->password) && !empty($this->passwordRe) ) {
			// change password
			if ( strcmp($this->password, $this->passwordRe) !== 0 ) {
				$this->addMessage('Двете въведени пароли се различават.', true);
				return $this->buildContent();
			}
			$set['password']= $this->db->encodePasswordDB($this->password);
		}
		$key = array('id' => $this->userId);
		if ( $this->db->update(User::MAIN_DB_TABLE, $set, $key) !== false ) {
			$this->addMessage("Данните ви бяха променени.");
			if ( isset($set['username']) ) {
				$this->user->username = $set['username'];
				$this->addMessage("Обърнете внимание, че отсега нататък потребителското ви име е „{$set['username']}“. Старото ви име „{$this->oldusername}“ е вече невалидно.");
			}
			$this->setNewUserOptions();
		} else {
			$this->addMessage('Имаше някакъв проблем при промяната на данните.', true);
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
		$this->addAltStylesheets();
		if ($this->isAnon) {
			$this->initAnonUserData();
			return $this->makeAnonUserForm();
		}
		$this->initRegUserData();
		return $this->makeRegUserForm();
	}


	protected function makeRegUserForm() {
		$formBegin = $this->makeFormBegin();
		$oldusername = $this->out->hiddenField('oldusername', $this->username);
		$username = $this->out->textField('username', '', $this->username, 25, 60, $this->tabindex++);
		$password = $this->out->passField('password', '', '', 25, 40, $this->tabindex++);
		$passwordRe = $this->out->passField('passwordRe', '', '', 25, 40, $this->tabindex++);
		$realname = $this->out->textField('realname', '', $this->realname, 25, 60, $this->tabindex++);
		$email = $this->out->textField('email', '', $this->email, 25, 60, $this->tabindex++);
		$common = $this->makeCommonInput();
		$news = $this->out->checkbox('news', '', false,
			'Получаване на месечно новинарско писмо', '', $this->tabindex++);
		$formEnd = $this->makeFormEnd();
		return <<<EOS

$formBegin
	<legend>Данни и настройки</legend>
	<table>
	<tr>
		<td><label for="username">Потребителско име:</label></td>
		<td>$oldusername $username</td>
	</tr><tr>
		<td><label for="password">Нова парола<a id="nb1" href="#n1">*</a>:</label></td>
		<td>$password</td>
	</tr><tr>
		<td><label for="passwordRe">Новата парола още веднъж:</label></td>
		<td>$passwordRe</td>
	</tr><tr>
		<td><label for="realname">Истинско име:</label></td>
		<td>$realname</td>
	</tr><tr>
		<td><label for="email">Е-поща:</label></td>
		<td>$email</td>
	</tr>$common
	<tr>
		<td colspan="2">$news</td>
	</tr>
$formEnd
<p><a id="n1" href="#nb1">*</a> <em>Нова парола</em> — въведете нова парола само ако искате да смените сегашната си.</p>
EOS;
	}


	protected function makeAnonUserForm() {
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
		return <<<EOS

	<tr>
		<td><label for="skin">Облик:</label></td>
		<td>$skin</td>
	</tr><tr>
		<td><label for="navpos">Навигация:</label></td>
		<td>$navpos</td>
	</tr><tr>
		<td><label for="mainpage">Начална страница:</label></td>
		<td>$mainpage</td>
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


	protected function makeOptionsOutput() {
		$o = '';
		foreach ($this->optKeys as $key) {
			$o .= $key .'=' . $this->opts[$key] .';';
		}
		return rtrim($o, ';');
	}


	protected function initRegUserData() {
		$sel = array('username', 'realname', 'email', 'news', 'opts');
		$key = array('id' => $this->userId);
		$res = $this->db->select(User::MAIN_DB_TABLE, $key, $sel);
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) {
			$this->addMessage("Не съществува потребител с номер $this->userId.", true);
			return;
		}
		$this->opts = User::extractOptions($data['opts']);
		unset($data['opts']);
		extract2object($data, $this);
	}


	protected function initAnonUserData() {
		extract2object($this->user->options(), $this);
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
