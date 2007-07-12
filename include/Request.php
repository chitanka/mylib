<?php

class Request {

	public function __construct() {
		$specialVars = array('cache');
		if ( empty($_SERVER['PATH_INFO']) ) {
			$this->path = isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : '';
		} else {
			$this->path = $_SERVER['PATH_INFO'];
		}
		if ($this->path == 'php5') { $this->path = ''; }
		$this->params = explode('/', ltrim(urldecode($this->path), '/'));
		foreach ($this->params as $key => $param) {
			if ( empty($param) ) { continue; }
			if ( strpos($param, '=') !== false ) {
				list($var, $value) = explode('=', $param);
				$value = $this->normalizeParamValue($value);
				$_REQUEST[$var] = $_GET[$var] = $value;
				#if ( in_array($var, $specialVars) ) {
					unset($this->params[$key]);
				#}
			} else {
				$param = $this->normalizeParamValue($param);
				$this->params[$key] = $param;
				$_REQUEST[] = $_GET[] = $param;
			}
		}

		// normalize keys to start from 0
		$this->params = array_values($this->params);
		if ( empty($this->params) ) { $this->params[] = ''; }
		if ( empty($_REQUEST['action']) ) {
			$this->action = PageManager::validatePage( $this->params[0] );
			$_REQUEST['action'] = $_GET['action'] = $this->action;
		} else {
			$this->action = $_REQUEST['action'];
		}
		if ( $this->params[0] != $this->action ) {
			array_unshift($this->params, $this->action);
		}

		// not needed $_GET (but not $_REQUEST) vars
		unset( $_GET[SESSION_NAME] );
		unset( $_GET['cache'] );

		$this->unescapeGlobals();

		$encodingCookie = $this->value(ENC_COOKIE, Setup::$masterEncoding);
		$this->outputEncoding = $this->value('enc', $encodingCookie);
		if ( empty($this->outputEncoding) ) {
			$this->outputEncoding = Setup::$masterEncoding;
		}
// 		$this->encoding = $this->value('enc', $encodingCookie);
// 		if ( $this->outputEncoding != $encodingCookie ) {
// 			setcookie(ENC_COOKIE, $this->outputEncoding, COOKIE_EXP);
// 		}
// 		$this->normalizeGlobalsEncoding();

		// put encoding in hash in order to generate/retrieve the right cache
		#$_GET['enc'] = $this->outputEncoding;
		ksort($_GET);
		$this->hash = $this->action . md5( serialize($_GET) );
		$this->cookiePath = Setup::setting('path');
		#unset( $_GET['enc'] );
	}


	public function action() { return $this->action; }


	/**
	 * Fetch a field value from the request.
	 *
	 * @param string $name
	 * @param string $default Return this if $name isn't set in the request
	 * @return mixed
	 */
	public function value($name, $default = NULL, $paramno = NULL) {
		if ( isset($_REQUEST[$name]) ) { return $_REQUEST[$name]; }
		return isset($paramno) ? $this->param($paramno, $default) : $default;
	}

	public function setValue($name, $value) {
		$_REQUEST[$name] = $_GET[$name] = $value;
	}


	public function checkbox($name, $dims = NULL) {
		if ( !isset($_REQUEST[$name]) ) return false;
		$val = $_REQUEST[$name];
		if ( is_array($dims) && !empty($dims) ) {
			foreach ($dims as $dim) { $val = $val[$dim]; }
		}
		return $val == 'on';
	}


	/** @return bool */
	public function wasPosted() { return $_SERVER['REQUEST_METHOD'] == 'POST'; }


	public function referer() {
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	}

	public function requestUri() {
		return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	}

	public function addUrlQuery($key, $value) {
		$newUrl = isset($_SERVER['REQUEST_URI']) ? rtrim($_SERVER['REQUEST_URI'], '/') : '.';
		$newUrl = preg_replace("!/$key=[^/]*!", '', $newUrl);
		$newUrl .= "/$key=$value";
		return $newUrl;
	}


	/**
	 * Gets a parameter by its position from the PATH_INFO query
	 * @param int $level Number greater than zero
	 * $param string $default Return this if the parameter is not set
	 * @return string The parameter value
	 */
	public function param($level, $default = NULL) {
		return isset($this->params[$level]) ? $this->params[$level] : $default;
	}


	public function hash() { return $this->hash; }


	public function setCookie($name, $value) {
		setcookie($name, $value, COOKIE_EXP, $this->cookiePath);
	}

	public function deleteCookie($name) {
		setcookie($name, '', time() - 3600, $this->cookiePath);
	}


	public function makeInputFieldsForGetVars($exclude=array()) {
		$c = '';
		foreach ($_GET as $name => $value) {
			if ( in_array($name, $exclude) || is_numeric($name) ) { continue; }
			$c .= "<input type='hidden' name='$name' value='$value' />\n";
		}
		return $c;
	}


	public function isMSIE() {
		return strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false;
	}


	public function isCompleteSubmission() {
		return $this->value('submitButton') !== NULL;
	}


	protected function normalizeParamValue($val) {
		if ( !empty($val) && $val{0} == '!' ) {
			// replace latin chars if it starts with "!"
			$val = lat2cyr( ltrim($val, '!') );
		}
		return $val;
	}

	/**
	 * Remove slashes from some global arrays if magic_quotes_gpc option is on
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
		if ( $this->encoding != Setup::$masterEncoding ) {
			$_GET = $this->changeArrayEncoding($_GET);
			$_POST = $this->changeArrayEncoding($_POST);
			$_REQUEST = $this->changeArrayEncoding($_REQUEST);
		}
	}


	/**
	 * Recursively strips slashes from a given array.
	 * @param array $arr
	 * @return array The modified array
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
	 * Changes the array encoding to the global master encoding.
	 * @param array $arr
	 * @return array The modified array
	 */
	protected function changeArrayEncoding($arr) {
		$narr = array();
		foreach ($arr as $key => $val) {
			$narr[$key] = is_array($val)
				? $this->changeArrayEncoding($val)
				: iconv($this->encoding, Setup::$masterEncoding, $val);
		}
		return $narr;
	}

}
