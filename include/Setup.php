<?php

class Setup {

	// the encoding used for internal representation
	public static $masterEncoding = 'utf-8';

	private static $configFile = './conf/main.ini';
	private static $config = array();

	/** @var Request */	private static $request;
	/** @var User */	private static $user;
	/** @var Skin */	private static $skin;
	/** @var Database */	private static $db;
	/** @var OutputMaker */	private static $outputMaker;
	/** @var PHPMailer */	private static $mailer;


	public static function doSetup() {
		// Read configuration file
		self::$config = parse_ini_file(self::$configFile, true);
		define('ADMIN', self::$config['admin']);
		define('ADMIN_EMAIL', header_encode(ADMIN) .' <'.
			self::$config['admin_email'] .'>');
		define('SITENAME', self::$config['sitename']);
		define('SITE_EMAIL', header_encode(SITENAME) .' <'.
			self::$config['site_email'] .'>');
		self::setupIni();
		addIncludePath( PageManager::pageDir() );
		self::setupUserRights();
	}


	public static function startSession($action) {
		session_cache_limiter(PageManager::pageCanBeCachedClient($action)
			? 'public' : 'nocache');
		session_name(SESSION_NAME);
		session_start();
	}

	public static function canUseCache() {
		return self::$config['cache'] == '1';
	}

	public static function setting($settingName) {
		return isset(self::$config[$settingName])
			? self::$config[$settingName] : '';
	}


	public static function request() {
		self::setupRequest();
		return self::$request;
	}

	public static function user() {
		self::setupUser();
		return self::$user;
	}

	public static function skin() {
		self::setupSkin();
		return self::$skin;
	}

	public static function db() {
		self::setupDB();
		return self::$db;
	}

	public static function outputMaker() {
		self::setupOutputMaker();
		return self::$outputMaker;
	}

	public static function mailer() {
		self::setupMailer();
		return self::$mailer;
	}


	/**
	 * Change some INI-settings
	 */
	protected static function setupIni() {
		ini_set('zlib.output_compression', 'On');
		ini_set('zlib.output_compression_level', 5);
		ini_set('session.use_trans_sid', 0);
		ini_set('session.use_only_cookies', 1);
		ini_set('arg_separator.output', '&amp;');
	}


	protected static function setupUserRights() {
		foreach (self::$config['rights'] as $group => $grights ) {
			$grights = preg_replace('/\s/', '', $grights);
			self::$config['rights'][$group] = explode(',', $grights);
		}
		User::$userRights = self::$config['rights'];
	}


	private static function setupDB() {
		if ( isset(self::$db) ) { return; }
		extract(self::$config['db']);
		self::$db = new Database($server, $user, $pass, $name);
		if ( !empty($prefix) ) { self::$db->setPrefix($prefix.'_'); }
	}


	private static function setupRequest() {
		if ( isset(self::$request) ) { return; }
		self::$request = new Request();
	}


	private static function setupUser() {
		if ( isset(self::$user) ) { return; }
		self::$user = User::initUser();
	}


	private static function setupSkin() {
		if ( isset(self::$skin) ) { return; }
		self::setupUser();
		$name = self::$user->option('skin');
		$skinDir = $name == 'neg' ? $name .'/' : 'main/';
		self::$skin = new Skin($skinDir);
	}


	private static function setupOutputMaker() {
		if ( isset(self::$outputMaker) ) { return; }
		self::$outputMaker = new OutputMaker();
	}


	private static function setupMailer() {
		if ( isset(self::$mailer) ) { return; }
		extract(self::$config['mail']);
		require_once 'Mail.php';
		$params = array(
			'host' => $host, 'auth' => true,
			'username' => $user, 'password' => self::decodePass($pass),
			'persist' => true,
		);
		self::$mailer = Mail::factory($backend, $params);
	}


	private static function decodePass($encPass) {
		return base64_decode(str_rot13(strrev($encPass)));
	}
}
?>
