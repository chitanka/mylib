<?php

class CacheManager {

	const SFBZIP_FILE = 'sfbzip';
	private static $cacheDir = 'cache/';


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
}
?>
