<?php

class CacheManager {

	const SFBZIP_DIR = 'sfbzip';
	private static $cacheDir = 'cache/';
	private static $dlDir = 'cache/dl/';
	/** Time to Live for download cache (in hours) */
	private static $dlTtl = 1;


	public static function cacheExists($action, $id) {
		return file_exists( self::getPath($action, $id) );
	}

	public static function getCache($action, $id, $compressed = false) {
		$c = file_get_contents( self::getPath($action, $id) );
		return $compressed ? gzinflate($c) : $c;
	}

	public static function setCache($action, $id, $content, $compressed = false) {
		$file = self::getPath($action, $id);
		return myfile_put_contents($file,
			$compressed ? gzdeflate($content) : $content);
	}

	public static function clearCache($action, $id) {
		$file = self::getPath($action, $id);
		return file_exists($file) ? unlink($file) : true;
	}


	public static function getDlFile($fname) {
		return self::$dlDir . $fname;
	}

	public static function setDlFile($fname, $fcontent) {
		return myfile_put_contents(self::$dlDir . $fname, $fcontent);
	}


	public static function dlFileExists($fname) {
		return file_exists(self::$dlDir . $fname);
	}

	/**
	Deletes all download files older than the time to live.
	*/
	public static function deleteOldDlFiles() {
		$thresholdTime = time() - self::$dlTtl * 3600;
		$dh = opendir(self::$dlDir);
		if (!$dh) return;
		while (($file = readdir($dh)) !== false) {
			if ( $file{0} == '.' ) { continue; }
			$fullname = self::$dlDir . $file;
			if (filemtime($fullname) < $thresholdTime) {
				unlink($fullname);
			}
		}
		closedir($dh);
	}

	public static function getPath($action, $id) {
		$subdir = $action . '/';
		settype($id, 'string');
		$subsubdir = $id{0} . '/';
		return self::$cacheDir . $subdir . $subsubdir . $id;
	}
}
