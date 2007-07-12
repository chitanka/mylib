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
		define('ADMIN_EMAIL', self::$config['admin_email']);
		define('ADMIN_EMAIL_ENC', header_encode(ADMIN) .' <'. ADMIN_EMAIL .'>');
		define('SITENAME', self::$config['sitename']);
		define('SITE_EMAIL', self::$config['site_email']);
		define('SITE_EMAIL_ENC', header_encode(SITENAME) .' <'. SITE_EMAIL .'>');
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

	public static function hasPathInfo() {
		return self::$config['has_path_info'] == '1';
	}

	public static function setting($settingName) {
		return isset(self::$config[$settingName])
			? self::$config[$settingName] : '';
	}


	public static function request() { return self::setupRequest(); }

	public static function user() { return self::setupUser(); }

	public static function skin() { return self::setupSkin(); }

	public static function db() { return self::setupDB(); }

	public static function outputMaker() { return self::setupOutputMaker(); }

	public static function mailer() { return self::setupMailer(); }


	/**
	 * Change some INI-settings
	 */
	protected static function setupIni() {
		ini_set('zlib.output_compression', 'On');
		ini_set('zlib.output_compression_level', 5);
		ini_set('session.use_trans_sid', 0);
		ini_set('session.use_only_cookies', 1);
		ini_set('arg_separator.output', '&amp;');
		ini_set('date.timezone', self::$config['default_timezone']);
	}


	protected static function setupUserRights() {
		foreach (self::$config['rights'] as $group => $grights ) {
			$grights = preg_replace('/\s/', '', $grights);
			self::$config['rights'][$group] = explode(',', $grights);
		}
		User::$userRights = self::$config['rights'];
	}


	private static function setupDB() {
		if ( !isset(self::$db) ) {
			extract(self::$config['db']);
			self::$db = new Database($server, $user, $pass, $name);
			if ( !empty($prefix) ) { self::$db->setPrefix($prefix.'_'); }
		}
		return self::$db;
	}


	private static function setupRequest() {
		if ( !isset(self::$request) ) {
			self::$request = new Request();
		}
		return self::$request;
	}


	private static function setupUser() {
		if ( !isset(self::$user) ) {
			self::$user = User::initUser();
		}
		return self::$user;
	}


	private static function setupSkin() {
		if ( !isset(self::$skin) ) {
			self::setupUser();
			$name = self::$user->option('skin');
			$skinDir = $name == 'neg' ? $name .'/' : 'main/';
			self::$skin = new Skin($skinDir);
		}
		return self::$skin;
	}


	private static function setupOutputMaker() {
		if ( !isset(self::$outputMaker) ) {
			self::$outputMaker = new OutputMaker();
			self::$outputMaker->setHasPathInfo(self::hasPathInfo());
		}
		return self::$outputMaker;
	}


	private static function setupMailer() {
		if ( !isset(self::$mailer) ) {
			extract(self::$config['mail']);
			require_once 'Mail.php';
			$params = array(
				'host' => $host, 'port' => $port, 'auth' => $auth == '1',
				'username' => $user, 'password' => $pass,
				'persist' => $persist == '1',
			);
			self::$mailer = Mail::factory($backend, $params);
		}
		return self::$mailer;
	}

}
