<?php
/** @file
 * The main entry point of the application
 */

/** @mainpage
 * Software for an on-line digital library.
 */

/**
 * Load a class file
 * @param $class Class name
 */
function __autoload($class) { require_once $class .'.php'; }

ini_set('error_log', './log/error');
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$startTime = microtime(true);

require_once 'globals.php';
addIncludePath('include');

Setup::doSetup();
$request = Setup::request();
$action = $request->action();
Setup::startSession($action);
$user = Setup::user(); // session must be already started
if ( !$user->canExecute($action) ) { $action = PageManager::defaultPage(); }

$useCache = (bool) $request->value('cache', 1);
$page = PageManager::executePage($action, $useCache, $request->hash());
$elapsedTime = number_format( microtime(true) - $startTime, 4, ',', '' );
$page->output($elapsedTime); // Output all page content
