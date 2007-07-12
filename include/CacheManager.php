<?php

class CacheManager {

	const SFBZIP_FILE = 'sfbzip';
	private static $cacheDir = 'cache/';
	private static $dlDir = 'cache/dl/';
	/** Time to Live for download cache (in hours) */
	private static $dlTtl = 1;


	public static function cacheExists($id) {
		return file_exists(self::$cacheDir . $id);
	}

	public static function getCache($id, $compressed = true) {
		$c = file_get_contents(self::$cacheDir . $id);
		return $compressed ? gzinflate($c) : $c;
	}

	public static function setCache($id, $content, $compressed = true) {
		return file_put_contents(self::$cacheDir . $id,
			$compressed ? gzdeflate($content) : $content);
	}

	public static function clearCache($id) {
		$file = self::$cacheDir . $id;
		return file_exists($file) ? unlink($file) : true;
	}


	public static function getDlFile($fname) {
		return self::$dlDir . $fname;
	}

	public static function setDlFile($fname, $fcontent) {
		if ( !file_exists(self::$dlDir) ) {
			mkdir(self::$dlDir);
		}
		return file_put_contents(self::$dlDir . $fname, $fcontent);
	}


	public static function dlFileExists($fname) {
		return file_exists(self::$dlDir . $fname);
	}

	/**
	 * delete all files older than a hour
	 */
	public static function deleteOldDlFiles() {
		$thresholdTime = time() - self::$dlTtl * 3600;
		$dh = opendir(self::$dlDir);
		if (!$dh) return;
		while (($file = readdir($dh)) !== false) {
			$fullname = self::$dlDir . $file;
			if (filemtime($fullname) < $thresholdTime) {
				unlink($fullname);
			}
		}
		closedir($dh);
	}
}
