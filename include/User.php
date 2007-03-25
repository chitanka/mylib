<?php

class User {

	const MAIN_DB_TABLE = 'user';
	public static $userRights;
	public $id, $username, $realname, $group;
	private $rights = array();
	private $options = array();


	/**
	 * @param int $id
	 * @param string $username
	 * @param string $realname
	 * @param string $group
	 */
	private function __construct($id = 0, $username = '', $realname = '',
			$group = 'anon', $options = array()) {
		$this->id = $id;
		$this->username = $username;
		$this->realname = $realname;
		$this->group = $group;
		foreach (self::$userRights as $group => $grights) {
			$this->rights = array_merge($this->rights, $grights);
			if ( $this->group == $group ) { break; }
		}
		if ( isset($_COOKIE[OPTS_COOKIE]) ) {
			$this->options = self::extractOptions( $_COOKIE[OPTS_COOKIE] );
		}
		$this->options = array_merge($this->options, (array) $options);
	}


	/**
	 * @return User
	 */
	public static function initUser() {
		if ( User::isSetSession() ) {
			$user = User::newFromSession();
		} elseif ( User::isSetCookie() ) {
			$user = User::newFromCookie();
		} else {
			$user = new User();
		}
		$user->checkMainPage();
		return $user;
	}


	public static function randomPassword($passLength = 8) {
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz123456789';
		$max = strlen($chars) - 1;
		$password = '';
		for ($i=0; $i < $passLength; $i++) {
			$password .= $chars{mt_rand(0, $max)};
		}
		return $password;
	}


	/**
	 * Check a user name for invalid chars
	 * @param string $username
	 * @return mixed true if the user name is ok, or the invalid character
	 */
	public static function isValidUsername($username) {
		$forbidden = '/+#{}[]';
		$len = strlen($forbidden);
		for ($i=0; $i < $len; $i++) {
			if ( strpos($username, $forbidden{$i}) !== false ) {
				return $forbidden{$i};
			}
		}
		return true;
	}


	public static function getLoginTries($username) {
		$db = Setup::db();
		$key = array('username' => $username);
		$res = $db->select(self::MAIN_DB_TABLE, $key, 'login_tries');
		if ( $db->numRows($res) ==  0) return 0;
		list($cnt) = $db->fetchRow($res);
		return $cnt;
	}

	public static function incLoginTries($username) {
		$db = Setup::db();
		$key = array('username' => $username);
		return $db->update(self::MAIN_DB_TABLE, array('login_tries = login_tries+1'), $key);
	}


	public static function getData($userId) {
		$db = Setup::db();
		$key = array('id' => $userId);
		$res = $db->select(self::MAIN_DB_TABLE, $key);
		if ( $db->numRows($res) ==  0) return array();
		return $db->fetchAssoc($res);
	}


	/**
	 * Is the user anonymous?
	 * @return bool
	 */
	public function isAnon() { return empty($this->username); }

	public function showName() {
		return empty($this->realname) ? $this->username : $this->realname;
	}
	public function userName() { return $this->username; }

	public function options() { return $this->options; }
	public function option($opt) {
		return isset($this->options[$opt]) ? $this->options[$opt] : '';
	}
	public function setOption($name, $val) { $this->options[$name] = $val; }
	public function setOptions($opts) {
		$this->options = array_merge($this->options, (array) $opts);
	}


	public function canExecute($action) {
		return in_array($action, $this->rights) || $this->isSuperUser();
	}

	public function isSuperUser() {
		return in_array('*', $this->rights);
	}


	public function saveNewPassword($username, $password) {
		$db = Setup::db();
		$set = array('newpassword' => $db->encodePasswordDB($password));
		$key = array('username' => $username);
		$db->update(self::MAIN_DB_TABLE, $set, $key);
		return $db->affectedRows() > 0;
	}


	public function activateNewPassword($uid) {
		$db = Setup::db();
		$res = $db->select(self::MAIN_DB_TABLE, array('id' => $uid), 'newpassword');
		$data = $db->fetchAssoc($res);
		if ( empty($data) ) { return false; }
		extract($data);
		$set = array('password' => $newpassword);
		$db->update(self::MAIN_DB_TABLE, $set, array('id' => $uid));
		return $db->affectedRows() > 0;
	}


	public static function login($uid, $upass, $remember = false) {
		$db = Setup::db();
		// delete a previously generated new password, login_tries
		$set = array('newpassword' => '', 'login_tries' => 0);
		$db->update(self::MAIN_DB_TABLE, $set, array('id' => $uid));
		$_COOKIE[UID_COOKIE] = $uid;
		$_COOKIE[TOKEN_COOKIE] = $db->encodePasswordCookie($upass);
		if ($remember) {
			$request = Setup::request();
			$request->setCookie(UID_COOKIE, $_COOKIE[UID_COOKIE]);
			$request->setCookie(TOKEN_COOKIE, $_COOKIE[TOKEN_COOKIE]);
		}
		return $_SESSION[U_SESSION] = User::newFromDB($uid);
	}


	public function logout() {
		unset($_SESSION[U_SESSION]);
		unset($_COOKIE[UID_COOKIE]);
		unset($_COOKIE[TOKEN_COOKIE]);
		$request = Setup::request();
		$request->deleteCookie(UID_COOKIE);
		$request->deleteCookie(TOKEN_COOKIE);
		$this->__construct();
	}


	public function updateSession() {
		$_SESSION[U_SESSION] = $this;
	}


	protected function checkMainPage() {
		if ( $this->option('mainpage') == 'd' ) {
			PageManager::dontCacheServer('main');
		}
	}


	public static function extractOptions($optstr) {
		if ( empty($optstr) ) { return array(); }
		$rawopts = explode(';', $optstr);
		$opts = array();
		foreach ($rawopts as $rawopt) {
			if ( empty($rawopt) ) continue;
			list($name, $val) = explode('=', $rawopt);
			$opts[$name] = $val;
		}
		return $opts;
	}


	/** @return bool */
	protected static function isSetSession() {
		return isset($_SESSION[U_SESSION]);
	}


	/** @return bool */
	protected static function isSetCookie() {
		return isset($_COOKIE[UID_COOKIE]) && isset($_COOKIE[TOKEN_COOKIE]);
	}

	/** @return User */
	protected static function newFromSession() {
		return $_SESSION[U_SESSION];
	}

	/** @return User */
	protected static function newFromCookie() {
		return User::newFromDB( $_COOKIE[UID_COOKIE] );
	}


	/**
	 *
	 * @param integer $uid
	 * @return User
	 */
	protected static function newFromDB($uid) {
		$db = Setup::db();
		$res = $db->select(self::MAIN_DB_TABLE, array('id' => $uid));
		if ($db->numRows($res) > 0) {
			extract( $db->fetchAssoc($res) );
			if ( $db->encodePasswordCookie($password) === $_COOKIE[TOKEN_COOKIE] ) {
				$opts = self::extractOptions($opts);
				$opts['news'] = $news;
				$user = new User($uid, $username, $realname, $group, $opts);
				return $_SESSION[U_SESSION] = $user;
			}
		}
		return new User();
	}

}
?>
