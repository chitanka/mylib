<?php

class Request {

	protected
		$bots = array('bot', 'search', 'crawl', 'spider', 'fetch', 'reader',
			'subscriber', 'google', 'rss'),
		// hash of the request
		$hash;

	public function __construct() {
		$specialVars = array('cache');
		if ( empty($_SERVER['PATH_INFO']) ) {
			$this->path = isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : '';
		} else {
			$this->path = $_SERVER['PATH_INFO'];
		}

		// buggy Apache 2.0 — PHP 5.0 collaboration, I think
		if ($this->path == 'php5') { $this->path = ''; }

		$this->params = explode('/', ltrim(urldecode($this->path), '/'));
		foreach ($this->params as $key => $param) {
			if ( empty($param) ) {
				continue;
			}
			if ( strpos($param, '=') === false ) {
				$param = $this->normalizeParamValue($param);
				$this->params[$key] = $param;
			} else {
				list($var, $value) = explode('=', $param);
				$value = $this->normalizeParamValue($value);
				if ( preg_match('/(\w+)\[(.*)\]/', $var, $match) ) {
					// the parameter is an array element
					list(, $arr, $key) = $match;
					if ( empty($key) ) {
						$_REQUEST[$arr][] = $_GET[$arr][] = $value;
					} else {
						$_REQUEST[$arr][$key] = $_GET[$arr][$key] = $value;
					}
				} else {
					$_REQUEST[$var] = $_GET[$var] = $value;
				}
				#if ( in_array($var, $specialVars) ) {
					unset($this->params[$key]);
				#}
			}
		}

		// normalize keys to start from 0
		$this->params = array_values($this->params);
		if ( empty($this->params) ) {
			$this->params[] = '';
		}
		if ( empty($_REQUEST[Page::FF_ACTION]) ) {
			$this->action = PageManager::validatePage( $this->params[0] );
			$_REQUEST[Page::FF_ACTION] = $_GET[Page::FF_ACTION] = $this->action;
		} else {
			$this->action = $_REQUEST[Page::FF_ACTION];
		}
		if ( $this->params[0] != $this->action ) {
			array_unshift($this->params, $this->action);
		}

		// not needed $_GET (but not $_REQUEST) vars
		unset( $_GET[SESSION_NAME] );
		unset( $_GET['cache'] );

		$this->unescapeGlobals();

		$encodingCookie = $this->value(ENC_COOKIE, Setup::IN_ENCODING);
		$this->outputEncoding = $this->value('enc', $encodingCookie);
		if ( empty($this->outputEncoding) ) {
			$this->outputEncoding = Setup::IN_ENCODING;
		}
// 		$this->encoding = $this->value('enc', $encodingCookie);
// 		if ( $this->outputEncoding != $encodingCookie ) {
// 			setcookie(ENC_COOKIE, $this->outputEncoding, COOKIE_EXP);
// 		}
// 		$this->normalizeGlobalsEncoding();

		$this->cookiePath = Setup::setting('path');
		$this->ua = strtolower(@$_SERVER['HTTP_USER_AGENT']);
	}


	public function action() {
		return $this->action;
	}


	/**
		Fetch a field value from the request.
		Return default value if $name isn’t set in the request, or if $allowed
		is an array and does not contain $name as a key.
		@param $name
		@param $default
		@param $paramno
		@param $allowed Associative array
	*/
	public function value($name, $default = null, $paramno = null, $allowed = null) {
		if ( isset($_REQUEST[$name]) ) {
			$val = $_REQUEST[$name];
		} else if ( empty($paramno) ) {
			return $default;
		} else if ( isset($this->params[$paramno]) ) {
			$val = $_REQUEST[$name] = $_GET[$name] = $this->params[$paramno];
		} else {
			return $default;
		}
		return is_array($allowed) ? normKey($val, $allowed, $default) : $val;
	}

	public function setValue($name, $value) {
		$_REQUEST[$name] = $_GET[$name] = $value;
	}


	public function checkbox($name, $dims = null) {
		if ( !isset($_REQUEST[$name]) ) {
			return false;
		}
		$val = $_REQUEST[$name];
		if ( is_array($dims) && !empty($dims) ) {
			foreach ($dims as $dim) { $val = $val[$dim]; }
		}
		return $val == 'on';
	}


	/** @return bool */
	public function wasPosted() {
		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}


	public function isBotRequest() {
		foreach ($this->bots as $bot) {
			if ( strpos($this->ua, $bot) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
		Tests whether a given set of parameters corresponds to the GET request.

		@param $reqData Associative array
		@return bool
	*/
	public function isCurrentRequest($reqData) {
		if (	!is_array($reqData) ||
				count(array_diff_assoc($_GET, $reqData)) > 0 ||
				count(array_diff_assoc($reqData, $_GET)) > 0 ) {
			return false;
		}
		foreach ($_GET as $param => $val) {
			if ($reqData[$param] != $val) {
				return false;
			}
		}
		return true;
	}

	public function referer() {
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	}

	public function requestUri() {
		return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	}


	public function hash() {
		if ( empty($this->hash) ) {
			ksort($_GET);
			$this->hash = md5( serialize($_GET + $this->params) );
		}
		return $this->hash;
	}


	public function setCookie($name, $value) {
		setcookie($name, $value, COOKIE_EXP, $this->cookiePath);
	}

	public function deleteCookie($name) {
		setcookie($name, '', time() - 3600, $this->cookiePath);
	}


	public function makeInputFieldsForGetVars($exclude=array()) {
		$c = '';
		foreach ($_GET as $name => $value) {
			if ( in_array($name, $exclude) || is_numeric($name) ) {
				continue;
			}
			$c .= "<input type='hidden' name='$name' value='$value' />\n";
		}
		return $c;
	}


	public function isMSIE() {
		return strpos($this->ua, 'msie') !== false;
	}


	public function isCompleteSubmission() {
		return $this->value('submitButton') !== null;
	}


	protected function normalizeParamValue($val) {
		if ( !empty($val) && $val{0} == '!' ) {
			// replace latin chars if it starts with "!"
			$val = lat2cyr( ltrim($val, '!') );
		}
		return $val;
	}

	/**
		Remove slashes from some global arrays if magic_quotes_gpc option is on.
	*/
	protected function unescapeGlobals() {
		if ( get_magic_quotes_gpc() ) {
			$_GET = $this->unescapeArray($_GET);
			$_POST = $this->unescapeArray($_POST);
			$_COOKIE = $this->unescapeArray($_COOKIE);
			$_REQUEST = $this->unescapeArray($_REQUEST);
		}
	}

	protected function normalizeGlobalsEncoding() {
		if ( $this->encoding != Setup::IN_ENCODING ) {
			$_GET = $this->changeArrayEncoding($_GET);
			$_POST = $this->changeArrayEncoding($_POST);
			$_REQUEST = $this->changeArrayEncoding($_REQUEST);
		}
	}


	/**
		Recursively strips slashes from a given array.

		@param array $arr
		@return array The modified array
	*/
	protected function unescapeArray($arr) {
		$narr = array();
		// normalize line delimiter
		$repl = array("\r\n" => "\n", "\r" => "\n");
		foreach ($arr as $key => $val) {
			$narr[ stripslashes($key) ] = is_array($val)
				? $this->unescapeArray($val)
				: strtr(stripslashes($val), $repl);
		}
		return $narr;
	}


	/**
		Changes the array encoding to the global master encoding.

		@param array $arr
		@return array The modified array
	*/
	protected function changeArrayEncoding($arr) {
		$narr = array();
		foreach ($arr as $key => $val) {
			$narr[$key] = is_array($val)
				? $this->changeArrayEncoding($val)
				: iconv($this->encoding, Setup::IN_ENCODING, $val);
		}
		return $narr;
	}

}
