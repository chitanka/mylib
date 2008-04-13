<?php

class Setup {

	// the encoding used for internal representation
	const IN_ENCODING = 'utf-8';

	private static
		$configFile = './config.php',
		$cfg = array();

	private static
	/** @var Request */		$request,
	/** @var User */		$user,
	/** @var Skin */		$skin,
	/** @var Database */	$db,
	/** @var OutputMaker */	$outputMaker,
	/** @var PHPMailer */	$mailer;


	public static function doSetup() {
		// Read configuration file
		if ( !file_exists(self::$configFile) ) {
			echo 'The configuration file <strong>'.self::$configFile.'</strong> does not exist.';
			exit(0);
		}
		require_once self::$configFile;
		self::$cfg = $cfg;
		if ( !empty(self::$cfg['db']['prefix']) ) {
			self::$cfg['db']['prefix'] .= '_';
		}
		// Път, използван за бисквитките
		self::$cfg['path'] = dirname(self::$cfg['webroot']);

		self::defineDbTableConsts( self::$cfg['db']['prefix'] );
		define('ADMIN', self::$cfg['admin']);
		define('ADMIN_EMAIL', self::$cfg['admin_email']);
		define('ADMIN_EMAIL_ENC', header_encode(ADMIN) .' <'. ADMIN_EMAIL .'>');
		define('SITENAME', self::$cfg['sitename']);
		define('SITE_EMAIL', self::$cfg['site_email']);
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
		return self::$cfg['use_cache'];
	}

	public static function hasPathInfo() {
		return self::$cfg['has_path_info'];
	}

	public static function setting($settingName) {
		return isset(self::$cfg[$settingName])
			? self::$cfg[$settingName] : '';
	}


	public static function request() { return self::setupRequest(); }

	public static function user() { return self::setupUser(); }

	public static function skin() { return self::setupSkin(); }

	public static function db() { return self::setupDb(); }

	public static function outputMaker() { return self::setupOutputMaker(); }

	public static function mailer() { return self::setupMailer(); }


	/**
	Change some INI-settings
	*/
	protected static function setupIni() {
		ini_set('zlib.output_compression', 'On');
		ini_set('zlib.output_compression_level', 5);
		ini_set('session.use_trans_sid', 0);
		ini_set('session.use_only_cookies', 1);
		ini_set('arg_separator.output', '&amp;');
		ini_set('date.timezone', self::$cfg['default_timezone']);
	}


	protected static function setupUserRights() {
		User::$userRights = self::$cfg['rights'];
	}


	private static function setupDb() {
		if ( !isset(self::$db) ) {
			extract(self::$cfg['db']);
			self::$db = new Database($server, $user, $pass, $name);
			self::$db->setPrefix($prefix);
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
			extract(self::$cfg['mail']);
			require_once 'Mail.php';
			$params = array(
				'host' => $host, 'port' => $port, 'auth' => $auth,
				'username' => $user, 'password' => $pass,
				'persist' => $persist,
			);
			// @ to avoid strict error:
			// Non-static method Mail::factory() should not be called statically
			self::$mailer = @Mail::factory($backend, $params);
		}
		return self::$mailer;
	}


	private static function defineDbTableConsts($prefix) {
		$tables = array(
			'AUTHOR_OF' => 'author_of',
			'BOOK' => 'book',
			'BOOK_PART' => 'book_part',
			'BOOK_TEXT' => 'book_text',
			'COMMENT' => 'comment',
			'EDIT_HISTORY' => 'edit_history',
			'HEADER' => 'header',
			'LABEL' => 'label',
			'LABEL_LOG' => 'label_log',
			'LICENSE' => 'license',
			'LITERNEWS' => 'liternews',
			'NEWS' => 'news',
			'PERSON' => 'person',
			'PERSON_ALT' => 'person_alt',
			'QUESTION' => 'question',
			'READER_OF' => 'reader_of',
			'SER_AUTHOR_OF' => 'ser_author_of',
			'SERIES' => 'series',
			'SUBSERIES' => 'subseries',
			'TEXT' => 'text',
			'TEXT_LABEL' => 'text_label',
			'TRANSLATOR_OF' => 'translator_of',
			'USER' => 'user',
			'USER_TEXT' => 'user_text',
			'WORK' => 'work',
			'WORK_MULTI' => 'work_multi',
		);
		foreach ($tables as $constant => $table) {
			define('DBT_' . $constant, $prefix . $table);
		}
	}
}
