<?php
$defUser = 'root';
$defPass = '';
$defServer = 'localhost';
$defDbname = 'mylib2';
$initSql = 'tables.sql';
$fillSql = 'mylib-db.sql';
$validCommands = array('init', 'fill');

if ( !in_array($argv[1], $validCommands) ) {
	$script = basename($argv[0]);
	print <<<EOS
Usage:

php $script <command> [<db_user>] [<db_pass>] [<db_server>] [<database>]

All arguments except <command> are optional.
  <command>	What to do. There are two options:
  	init	- Initialize database; uses the file MYLIB_DIR/$initSql
  	fill	- Fill a database; uses a file MYLIB_DIR/$fillSql
  <db_user>	User for login in mysql (default "$defUser")
  <db_pass>	Password for the mysql user (default no password)
  <db_server>	Database server (default "$defServer")
  <db_name>	Database name (default "$defDbname")

EOS;
	exit;
}

$i = 0;
$path = dirname( realpath($argv[0]) );
$command = isset($argv[++$i]) ? $argv[$i] : $defCommand;
$dbuser = isset($argv[++$i]) ? $argv[$i] : $defUser;
$dbpass = isset($argv[++$i]) ? $argv[$i] : $defPass;
$server = isset($argv[++$i]) ? $argv[$i] : $defServer;
$dbname = isset($argv[++$i]) ? $argv[$i] : $defDbname;
$charset = 'utf8';


function loadSourceInMysql($src, $msg = 'Loading source to MySQL') {
	global $mysqlBinDir, $dbuser, $dbpass, $dbname;
	echo $msg . "\n";
	`{$mysqlBinDir}mysql --user=$dbuser --password=$dbpass $dbname < $src`;
}

function dbExists($db, $connection) {
	$result = mysql_list_dbs($connection);
	while ($row = mysql_fetch_row($result)) {
		if ($row[0] == $db) {
			return true;
		}
	}
	return false;
}


$conn = mysql_connect($server, $dbuser, $dbpass)
	or die("Could not connect to database server $server for $dbuser.\n");
if ( !dbExists($dbname, $conn) ) {
	mysql_query("CREATE DATABASE $dbname", $conn)
		or die("Could not create database $dbname.");
}
// mysql_select_db($dbname, $conn)
// 	or die("Could not select database $dbname.");
// mysql_query("SET names $charset", $conn)
// 	or die("Could not set names to '$charset'.");


$mysqlBinDir = ''; // may be not in PATH
if ( preg_match('|(.+/lampp/)htdocs|', $argv[0], $match) ) {
	$mysqlBinDir = $match[1] . 'bin';
} else if ( preg_match('/(.+\\\\xampp\\\\)htdocs/', $argv[0], $match) ) {
	$mysqlBinDir = $match[1] . 'mysql\\bin\\';
}

loadSourceInMysql("$path/$initSql", 'Initializing database tables...');

if ($command == 'fill') {
	loadSourceInMysql("$path/$fillSql", 'Filling database tables...');
}

echo "Done.\n";
