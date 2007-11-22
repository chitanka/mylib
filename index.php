<?php
/** @file
The main entry point of the application.
*/
// we need at least PHP 5
if ( version_compare(phpversion(), '5', '<') ) {
	die('Mylib needs PHP 5 or greater. You are using PHP '. phpversion() .'.');
}

define('MYLIB', 1);

/** @mainpage
Software for an on-line digital library.
*/

ini_set('error_log', './log/error');
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL | E_STRICT);

$startTime = microtime(true);

require_once 'globals.php';
addIncludePath('include');

Setup::doSetup();
$request = Setup::request();
$action = $request->action();
Setup::startSession($action);
$user = Setup::user(); // session must be already started
if ( !$user->canExecute($action) ) {
	$action = PageManager::defaultPage();
}

$useCache = (bool) $request->value('cache', 1);
$page = PageManager::executePage($action, $useCache, $request->hash());
$elapsedTime = number_format( microtime(true) - $startTime, 4, ',', '' );
$page->output($elapsedTime); // Output all page content
