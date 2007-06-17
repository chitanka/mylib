<?php

class Database {

	/**#@+ @var string */
	/** Database server name */
	protected $server;
	/** Database user name */
	protected $user;
	/** Database user password */
	protected $pass;
	/** Database name */
	protected $dbName;
	protected $prefix = '';
	protected $charset = 'utf8';
	protected $collationConn = 'utf8_general_ci';
	/**#@-*/
	/**
	 * Connection to the database
	 * @var resource
	 */
	protected $conn = NULL;
	protected $logFile = 'log/db-DAY.sql';
	protected $errLogFile = 'log/db-error-DAY';
	protected $errno;


	public function Database($server, $user, $pass, $dbName) {
		$this->server = $server;
		$this->user = $user;
		$this->pass = $pass;
		$this->dbName = $dbName;
		$date = date('Y-m-d');
		$this->logFile = str_replace('DAY', $date, $this->logFile);
		$this->errLogFile = str_replace('DAY', $date, $this->errLogFile);
	}


	public function exists($table, $keys = array()) {
		return $this->getCount($table, $keys) > 0;
	}

	public function getObjects($table, $nfield = null, $kfield = null, $dbkey = array()) {
		if ( is_null($nfield) ) $nfield = 'name';
		if ( is_null($kfield) ) $kfield = 'id';
		$sel = array($kfield, $nfield);
		$res = $this->select($table, $dbkey, $sel, $nfield);
		$objs = array();
		while ( $row = mysql_fetch_row($res) ) {
			$objs[ $row[0] ] = $row[1];
		}
		return $objs;
	}


	public function getCount($table, $keys = array()) {
		$res = $this->select($table, $keys, 'COUNT(*)');
		list($count) = mysql_fetch_row($res);
		return (int) $count;
	}


	public function iterateOverResult($query, $func, $obj = null, $buffered = false) {
		$result = $this->query($query, $buffered);
		$out = '';
		while ( $row = mysql_fetch_assoc($result) ) {
			$out .= is_null($obj) ? $func($row) : $obj->$func($row);
		}
		return $out;
	}


	public function select($table, $keys = array(), $fields = array(),
			$orderby = '', $offset = 0, $limit = 0, $groupby = '') {
		$q = $this->selectQ($table, $keys, $fields, $orderby, $offset, $limit);
		return $this->query($q);
	}

	public function selectQ($table, $keys = array(), $fields = array(),
			$orderby = '', $offset = 0, $limit = 0, $groupby = '') {
		settype($fields, 'array');
		$sel = empty($fields) ? '*' : implode(', ', $fields);
		$sorder = empty($orderby) ? '' : ' ORDER BY '.$orderby;
		$sgroup = empty($groupby) ? '' : ' GROUP BY '.$groupby;
		$slimit = $limit > 0 ? " LIMIT $offset, $limit" : '';
		return "SELECT $sel FROM /*p*/$table".$this->makeWhereClause($keys).
			$sgroup . $sorder . $slimit;
	}

	public function insert($table, $data, $ignore = false) {
		if ( empty($data) ) return true;
		return $this->query($this->insertQ($table, $data, $ignore));
	}

	public function insertQ($table, $data, $ignore = false) {
		if ( empty($data) ) return '';
		$signore = $ignore ? ' IGNORE' : '';
		return "INSERT$signore INTO /*p*/$table". $this->makeSetClause($data);
	}

	public function update($table, $data, $keys) {
		return $this->query($this->updateQ($table, $data, $keys));
	}

	public function updateQ($table, $data, $keys) {
		return 'UPDATE /*p*/'. $table . $this->makeSetClause($data) .
			$this->makeWhereClause($keys);
	}


	public function insertOrUpdate($table, $data, $key = NULL, $keyname = 'id') {
		$q = $this->makeInsertOrUpdateQuery($table, $data, $key, $keyname);
		return $this->query($q);
	}


	public function replace($table, $data) {
		if ( empty($data) ) return true;
		return $this->query($this->replaceQ($table, $data));
	}

	public function replaceQ($table, $data) {
		if ( empty($data) ) return '';
		return 'REPLACE /*p*/'.$table.$this->makeSetClause($data);
	}

	public function delete($table, $keys, $limit = 0) {
		if ( empty($keys) ) return true;
		return $this->query($this->deleteQ($table, $keys, $limit));
	}

	public function deleteQ($table, $keys, $limit = 0) {
		if ( empty($keys) ) return '';
		if ( !is_array($keys) ) $keys = array('id' => $keys);
		$q = 'DELETE FROM /*p*/'. $table . $this->makeWhereClause($keys);
		if ( !empty($limit) ) $q .= " LIMIT $limit";
		return $q;
	}

	public function makeInsertOrUpdateQuery($table, $data, $key = NULL, $keyname = 'id') {
		if ( empty($key) ) {
			$act = 'INSERT IGNORE INTO ';
			$qext = '';
		} else {
			$act = 'UPDATE ';
			$qext = " WHERE `$keyname` = '$key'";
		}
		return $act .'/*p*/'. $table . $this->makeSetClause($data) . $qext;
	}


	/**
	 * Send a query to the database
	 * @param string $query
	 * @param bool $useBuffer Use buffered or unbuffered query
	 * @return resource Or false by failure
	 */
	public function query($query, $useBuffer = true) {
		if ( !isset($this->conn) ) { $this->connect(); }
		$query = str_replace('/*p*/', $this->prefix, $query);
		$res = $useBuffer
			? mysql_query($query, $this->conn)
			: mysql_unbuffered_query($query, $this->conn);
		if ( !$res ) {
			$this->errno = mysql_errno();
			$this->error = mysql_error();
			$this->log("Error $this->errno: $this->error\nQuery: $query\n".
				"Backtrace\n". print_r(debug_backtrace(), true), true);
			return false;
		}
		if ( preg_match('/UPDATE|INSERT|REPLACE|DELETE/', $query) ) {
			$this->log("/*U={$GLOBALS['user']->id}*/ $query;", false);
		}
		return $res;
	}


	public function transaction($queries) {
		$res = array();
		$this->query('START TRANSACTION');
		foreach ( (array) $queries as $query) {
			$lres = $this->query($query);
			if ($lres === false) return false;
			$res[] = $lres;
		}
		$this->query('COMMIT');
		return $res;
	}


	public function makeSetClause($data, $putKeyword = true) {
		if ( empty($data) ) { return ''; }
		$keyword = $putKeyword ? ' SET ' : '';
		$cl = array();
		foreach ($data as $field => $value) {
			if ( is_numeric($field) ) { // take the value as is
				$cl[] = $value;
			} else {
				$value = $this->normalizeValue($value);
				$cl[] = "`$field` = '$value'";
			}
		}
		return $keyword . implode(', ', $cl);
	}


	public function makeWhereClause($keys, $join = 'AND', $putKeyword = true) {
		if ( empty($keys) ) {
			return $putKeyword ? ' WHERE 1' : '';
		}
		$cl = $putKeyword ? ' WHERE ' : '';
		$whs = array();
		foreach ($keys as $field => $rawvalue) {
			if ( is_numeric($field) ) { // take the value as is
				$field = $rel = '';
				$value = $rawvalue;
			} elseif ( is_array($rawvalue) ) {
				list($rel, $value) = $rawvalue;
			} else {
				$rel = '='; // default relation
				$value = $rawvalue;
			}
			if ( $value{0} != '(' ) {
				$value = '\''. $this->normalizeValue($value) .'\'';
			}
			$whs[] = "$field $rel $value";
		}
		$cl .= implode(" $join ", $whs);
		return $cl;
	}


	public function normalizeValue($value) {
		if ( is_bool($value) ) {
			$value = $value ? 'true' : 'false';
		} else {
			$value = $this->escape($value);
		}
		return $value;
	}

	public function setPrefix($prefix) { $this->prefix = $prefix; }


	public function escape($string) {
		if ( !isset($this->conn) ) { $this->connect(); }
		return mysql_real_escape_string($string, $this->conn);
	}


	/**
	 * string to boolean: 'true' returns true, everything else - false
	 * @param string $str
	 * @return bool
	 */
	public function s2b($str) { return $str == 'true'; }


	/** @return int Current MySQL error number */
	public function errno() { return $this->errno; }

	/** @return string Current MySQL error text */
	public function error() { return $this->error; }


	/** @return array Associative array */
	public function fetchAssoc($result) {
		return mysql_fetch_assoc($result);
	}

	/** @return array */
	public function fetchRow($result) {
		return mysql_fetch_row($result);
	}

	/** @return integer */
	public function numRows($result) {
		return mysql_num_rows($result);
	}

	/** @return integer */
	public function affectedRows() {
		return mysql_affected_rows($this->conn);
	}

	/** @return int */
	public function insertId() {
		return mysql_insert_id($this->conn);
	}


	/**
	 * Return next autoincrement for a table
	 * @param string $tableName
	 * @return integer
	 */
	public function autoIncrementId($tableName) {
		$res = $this->query('SHOW TABLE STATUS LIKE "'.$this->prefix.$tableName.'"');
		$row = mysql_fetch_assoc($res);
		return $row['Auto_increment'];
	}


	/**
	 * Encode a password in order to save it in the database
	 * @param string $password
	 * @return string Encoded password
	 */
	public function encodePasswordDB($password) {
		return crypt( $password, md5($password) );
	}


	/**
	 * Encode a database password in order to save it in a cookie
	 * @param string $dbPassword
	 * @return string Encoded password
	 */
	public function encodePasswordCookie($dbPassword) { return md5($dbPassword); }


	protected function connect() {
		$this->conn = @mysql_connect($this->server, $this->user, $this->pass)
			or die( 'Could not connect to database server '. $this->server .
			' for '. $this->user .'! '. mysql_error() );
		@mysql_select_db($this->dbName, $this->conn)
			or die( 'Could not select database '. $this->dbName .'! '.
			mysql_error() );
		@mysql_query("SET CHARACTER SET $this->charset")
			or die("Could not set character set to '$this->charset': " . mysql_error());
		@mysql_query("SET SESSION collation_connection ='$this->collationConn'")
			or die("Could not set collation_connection to '$this->collationConn': " .
			mysql_error());
	}


	protected function log($msg, $isError = true) {
		file_put_contents($isError ? $this->errLogFile : $this->logFile,
			'/*['.date('Y-m-d H:i:s').']*/ '. $msg."\n", FILE_APPEND);
	}

}

?>
