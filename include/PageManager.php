<?php

class PageManager {

	private static $pageDir = 'page/';
	private static $defaultPage = 'main';
	private static $cachedPagesServer = array('main', 'about', 'links', 'help',
		'blacklist', 'title', 'series', 'author', 'translator', 'label',
		'statistics');
	private static $cachedPagesClient = array('css', 'user', 'text', 'comment',
		'download', 'history', 'sendNewPassword', 'add', 'liternews',
		'suggestData');


	/**
	 * Tests whether a given page exists
	 * @return bool
	 */
	public static function pageExists($page) {
		return file_exists(self::$pageDir."$page.php");
	}


	public static function pageCanBeCachedServer($action) {
		return Setup::canUseCache() && in_array($action, self::$cachedPagesServer);
	}


	public static function pageCanBeCachedClient($action) {
		return in_array($action, self::$cachedPagesClient) ||
			in_array($action, self::$cachedPagesServer);
	}


	public static function dontCacheServer($action) {
		$key = array_search($action, self::$cachedPagesServer);
		unset(self::$cachedPagesServer[$key]);
	}


	/**
	 * Returns a page class name for a given action.
	 * @param string $action
	 * @return string
	 */
	public static function getPageClass($action) {
		return ucfirst($action) .'Page';
	}


	/**
	 * Creates a new page and executes it or sets its content from cache
	 * @param string $action
	 * @param bool $useCache
	 * @param string hash
	 * @return Page
	 */
	public static function executePage($action, $useCache = false, $hash = '') {
		global $user;
		if ( PageManager::pageCanBeCachedServer($action) && $user->isAnon() && !empty($hash) ) {
			if ( $useCache && CacheManager::cacheExists($hash) ) {
				$page = new Page($action);
				$data = unserialize( CacheManager::getCache($hash) );
				$page->setTitle( $data['title'] );
				$page->setContent( $data['content'] );
				$page->setMessages( $data['messages'] );
			} else {
				$page = PageManager::buildPage($action);
				$data = array(
					'title' => $page->title(),
					'content' => $page->execute(),
					'messages' => $page->messages()
				);
				CacheManager::setCache( $hash, serialize($data) );
			}
		} else {
			$page = PageManager::buildPage($action);
			$page->execute();
		}
		return $page;
	}


	/**
	 * Loads class file for a given page name and create the page object
	 * @param string $action Action (or the page name)
	 * @return Page
	 */
	public static function buildPage($action) {
		$pageClass = PageManager::loadPage($action);
		return new $pageClass();
	}


	/**
	 * Loads class file for a given page name
	 * @param string $action Action (or the page name)
	 * @return string Page class name
	 */
	public static function loadPage($action) {
		$page = PageManager::getPageClass($action);
// 		if ( ! PageManager::pageExists($page) ) {
// 			$page = PageManager::getPageClass(self::$defaultPage);
// 		}
// 		require_once self::$pageDir . "$page.php";
		return $page;
	}


	public static function validatePage($action) {
		if ( empty($action) ) return self::$defaultPage;
		$page = PageManager::getPageClass($action);
		return PageManager::pageExists($page) ? $action : self::$defaultPage;
	}

	public static function defaultPage() { return self::$defaultPage; }
	public static function pageDir() { return self::$pageDir; }
}
?>
