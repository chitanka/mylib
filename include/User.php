<?php

class User {

	const
		DB_TABLE = DBT_USER,
		PASS_RAW2DB_LOOPS = 10,
		PASS_RAW2COOKIE_LOOPS = 5;
	public static $defOptions = array(
		'skin' => 'orange',
		'nav' => 'right', // navigation position
		'mainpage' => 's', // main page type: s - static, d - dynamic
		'news' => false, // receive montly newsletter
		'allowemail' => true, // allow email from other users
		'dlcover' => false, // add covers to downloads
	);
	public static $userRights;
	public
		$id, $username, $password, $realname, $email, $group;
	protected
		$rights = array(), $options = array(),
		$readTexts = array(), $dlTexts = array(),
		$isHuman = false;


	public function __construct($id = 0, $username = '', $password = '',
			$realname = '', $email = '', $group = 'anon', $options = array()) {
		$this->id = $id;
		$this->setIsHuman($id > 0);
		$this->username = $username;
		$this->password = $password; // hash value of the password
		$this->realname = $realname;
		$this->email = $email;
		$this->group = $group;
		foreach (self::$userRights as $group => $grights) {
			$this->rights = array_merge($this->rights, $grights);
			if ( $this->group == $group ) { break; }
		}
		$this->options = self::$defOptions;
		if ( isset($_COOKIE[OPTS_COOKIE]) ) {
			$this->options = array_merge($this->options,
				self::unpackOptions( $_COOKIE[OPTS_COOKIE] ));
		}
		$this->options = array_merge($this->options, (array) $options);
	}


	/**
		@return User
	*/
	public static function initUser() {
		if ( self::isSetSession() ) {
			$user = self::newFromSession();
		} elseif ( self::isSetCookie() ) {
			$user = self::newFromCookie();
		} else {
			$user = new User();
		}
		$user->checkMainPage();
		return $_SESSION[U_SESSION] = $user;
	}


	public static function randomPassword($passLength = 8) {
		$chars = 'abcdefghijkmnopqrstuvwxyz123456789';
		$max = strlen($chars) - 1;
		$password = '';
		for ($i=0; $i < $passLength; $i++) {
			$password .= $chars{mt_rand(0, $max)};
		}
		return $password;
	}


	/**
		Check a user name for invalid chars.

		@param string $username
		@return mixed true if the user name is ok, or the invalid character
	*/
	public static function isValidUsername($username) {
		$forbidden = '/+#"{}[]';
		$len = strlen($forbidden);
		for ($i=0; $i < $len; $i++) {
			if ( strpos($username, $forbidden{$i}) !== false ) {
				return $forbidden{$i};
			}
		}
		return true;
	}


	/**
		Encrypt a password in order to save it in the database.

		@param string $password
		@return string Encryptеd password
	*/
	public static function encodePasswordDB($password) {
		return md5_loop($password, self::PASS_RAW2DB_LOOPS);
	}


	/**
		Encrypt a password in order to save it in a cookie.

		@param string $password
		@return string Encryptеd password
	*/
	public static function encodePasswordCookie($password) {
		return md5_loop($password, self::PASS_RAW2COOKIE_LOOPS);
	}


	/**
		Validate an entered password.
		Encrypts an entered password and compares it to the password token in
		the database.

		@param string $inputPass The password from the input
		@param string $dbPass The password stored in the database
		@return bool
	*/
	public static function validatePassword($inputPass, $dbPass) {
		return strcmp(self::encodePasswordDB($inputPass), $dbPass) === 0;
	}


	/**
		Validate a token from a cookie.
		Properly encrypts the cookie token and compares it to the password token
		in the database.

		@param string $cookieToken The token from the cookie
		@param string $dbToken The encrypted password stored in the database
		@return bool
	*/
	public static function validateCookieToken($cookieToken, $dbToken) {
		$loopDiff = self::PASS_RAW2DB_LOOPS - self::PASS_RAW2COOKIE_LOOPS;
		return strcmp($dbToken, md5_loop($cookieToken, $loopDiff)) === 0;
	}


	public static function getLoginTries($username) {
		$db = Setup::db();
		$key = array('username' => $username);
		$res = $db->select(self::DB_TABLE, $key, 'login_tries');
		if ( $db->numRows($res) ==  0) return 0;
		list($cnt) = $db->fetchRow($res);
		return $cnt;
	}

	public static function incLoginTries($username) {
		$db = Setup::db();
		$key = array('username' => $username);
		$set = array('login_tries = login_tries+1');
		return $db->update(self::DB_TABLE, $set, $key);
	}


	public static function getDataByName($username) {
		return self::getData( array('username' => $username) );
	}

	public static function getDataById($userId) {
		return self::getData( array('id' => $userId) );
	}

	public static function getData($dbkey) {
		$db = Setup::db();
		$res = $db->select(self::DB_TABLE, $dbkey);
		if ( $db->numRows($res) ==  0) return array();
		return $db->fetchAssoc($res);
	}


	/**
		Is the user anonymous?

		@return bool
	*/
	public function isAnon() {
		return empty($this->username);
	}

	public function showName() {
		return empty($this->realname) ? $this->username : $this->realname;
	}
	public function userName() {
		return $this->username;
	}

	public function set($field, $val) {
		if ( !isset($this->$field) ) return;
		$this->$field = $val;
	}

	public function options() {
		return $this->options;
	}
	public function option($opt) {
		return isset($this->options[$opt]) ? $this->options[$opt] : '';
	}
	public function setOption($name, $val) {
		$this->options[$name] = $val;
	}
	public function setOptions($opts) {
		$this->options = array_merge($this->options, (array) $opts);
	}


	public function canExecute($action) {
		return in_array($action, $this->rights) || $this->isSuperUser();
	}

	public function isSuperUser() {
		return in_array('*', $this->rights);
	}

	public function isHuman() {
		return $this->isHuman;
	}

	public function setIsHuman($isHuman) {
		$this->isHuman = $isHuman;
	}

	public static function saveNewPassword($username, $password) {
		$db = Setup::db();
		$set = array('newpassword' => self::encodePasswordDB($password));
		$key = array('username' => $username);
		$db->update(self::DB_TABLE, $set, $key);
		return $db->affectedRows() > 0;
	}


	public static function activateNewPassword($uid) {
		$db = Setup::db();
		$set = array('password = newpassword');
		$db->update(self::DB_TABLE, $set, array('id' => $uid));
		return $db->affectedRows() > 0;
	}


	public static function login($uid, $upass, $remember = false) {
		$db = Setup::db();
		// delete a previously generated new password, login_tries
		$set = array('newpassword' => '', 'login_tries' => 0);
		$db->update(self::DB_TABLE, $set, array('id' => $uid));
		$_COOKIE[UID_COOKIE] = $uid;
		$_COOKIE[TOKEN_COOKIE] = self::encodePasswordCookie($upass);
		if ($remember) {
			$request = Setup::request();
			$request->setCookie(UID_COOKIE, $_COOKIE[UID_COOKIE]);
			$request->setCookie(TOKEN_COOKIE, $_COOKIE[TOKEN_COOKIE]);
		}
		return $_SESSION[U_SESSION] = self::newFromDB($uid);
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


	public function markTextAsRead($textId) {
		if ( in_array($textId, $this->readTexts) ) { return; }
		$this->readTexts[] = $textId;
		if ( ! Setup::request()->isBotRequest() ) {
			Work::incReadCounter($textId);
		}
	}

	public function markTextAsDl($textId) {
		if ( in_array($textId, $this->dlTexts) ) { return; }
		$this->dlTexts[] = $textId;
		if ( ! Setup::request()->isBotRequest() ) {
			Work::incDlCounter($textId);
		}
	}


	protected function checkMainPage() {
		if ( $this->option('mainpage') == 'd' ) {
			PageManager::dontCacheServer('main');
		}
	}


	public static function packOptions($optsArr) {
		$o = '';
		foreach ($optsArr as $key => $value) {
			$o .= $key .'=' .
				(is_array($value) ? implode(',', $value) : $value) . ';';
		}
		return gzdeflate( rtrim($o, ';') );
	}


	public static function unpackOptions($optsData) {
		if ( empty($optsData) ) {
			return array();
		}
		$rawopts = explode(';', gzinflate($optsData));
		$opts = array();
		foreach ($rawopts as $rawopt) {
			if ( empty($rawopt) ) {
				continue;
			}
			list($name, $val) = explode('=', $rawopt);
			$opts[$name] = strpos($val, ',') === false ? $val : explode(',', $val);
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
		return self::newFromDB( $_COOKIE[UID_COOKIE] );
	}


	/**

	@param integer $uid
	@return User
	*/
	protected static function newFromDB($uid) {
		$db = Setup::db();
		$dbkey = array('id' => $uid);
		$res = $db->select(self::DB_TABLE, $dbkey);
		if ($db->numRows($res) > 0) {
			// touch this user
			$set = array('touched' => date('Y-m-d H:i:s'));
			$db->update(self::DB_TABLE, $set, $dbkey);
			extract( $db->fetchAssoc($res) );
			if ( self::validateCookieToken($_COOKIE[TOKEN_COOKIE], $password) ) {
				$opts = self::unpackOptions($opts);
				$opts['news'] = $db->s2b($news);
				$opts['allowemail'] = $db->s2b($allowemail);
				$user = new User($uid, $username, $password, $realname, $email, $group, $opts);
				return $user;
			}
		}
		return new User();
	}

}
