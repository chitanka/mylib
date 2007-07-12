<?php

/** Cookie name for the user ID */
define('UID_COOKIE', 'mylibUserID');
/** Cookie name for the encrypted user password */
define('TOKEN_COOKIE', 'mylibToken');
/** Cookie name for the user options */
define('OPTS_COOKIE', 'mylibOptions');
/** Cookie name for the selected encoding */
define('ENC_COOKIE', 'mylibEncoding');
define('ONEDAYSECS', 60*60*24); // number of seconds in a day
/** Timelife for cookies */
define('COOKIE_EXP', time()+ONEDAYSECS*30); // 30 days

define('SESSION_NAME', 'MYLIB_SESSION');
/** Session key for the User object */
define('U_SESSION', 'user');

function addIncludePath($path) {
	if ( is_array($path) ) {
		$path = implode(PATH_SEPARATOR, $path);
	}
	ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . $path);
}


$contentDir = 'content/';
$oldcontentDir = 'oldcontent/';
$contentDirs = array(
	'text' => 'text/',
	'text-info' => 'text-info/',
	'text-anno' => 'text-anno/',
	'user' => 'user/',
	'wiki' => 'wiki/',
	'info' => 'info/',
	'img' => 'img/',
	'cover' => 'cover/',
);
foreach ($contentDirs as $key => $dir) {
	$contentDirs[$key] = $contentDir . $dir;
	$contentDirs['old'.$key] = $oldcontentDir . $dir;
}

$latUppers = 'A B C D E F G H I J K L M N O P Q R S T U V W X Y Z';
$cyrs = array(
	'а', 'б', 'в', 'г', 'д', 'е', 'ж', 'з', 'и', 'й',
	'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у',
	'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ь', 'ю', 'я',
	'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ж', 'З', 'И', 'Й',
	'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У',
	'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ь', 'Ю', 'Я',
);
$types = array(
	'fable' => 'Басни',
	'essay' => 'Есе',
	'playbook' => 'Книга-игра',
	'science' => 'Научно',
	'novelette' => 'Новела',
	'shortstory' => 'Разказ',
	'novel' => 'Роман',
	'play' => 'Пиеса',
	'poetry' => 'Поезия',
	'poem' => 'Поема',
	'novella' => 'Повест',
	'intro' => 'Предговор',
	'tale' => 'Приказка',
	'travelnotes' => 'Пътепис',
	'speech' => 'Реч',
	'article' => 'Статия',
	'screenplay' => 'Сценарий',
	'textbook' => 'Учебник',
	'other' => 'Разни',
);
$typesPl = array(
	'fable' => 'Басни',
	'essay' => 'Есета',
	'playbook' => 'Книги-игри',
	'science' => 'Научни',
	'novelette' => 'Новели',
	'shortstory' => 'Разкази',
	'novel' => 'Романи',
	'play' => 'Пиеси',
	'poetry' => 'Поезия',
	'poem' => 'Поеми',
	'novella' => 'Повести',
	'intro' => 'Предговори',
	'tale' => 'Приказки',
	'speech' => 'Речи',
	'travelnotes' => 'Пътеписи',
	'article' => 'Статии',
	'screenplay' => 'Сценарии',
	'textbook' => 'Учебници',
	'other' => 'Разни',
);

$langs = array(
	'sq' => 'Албански',
	'en' => 'Английски',
	'ar' => 'Арабски',
	'hy' => 'Арменски',
	'bg' => 'Български',
	'el' => 'Гръцки',
	'da' => 'Датски',
	'he' => 'Иврит',
	'es' => 'Испански',
	'it' => 'Италиански',
	'zh' => 'Китайски',
	'ko' => 'Корейски',
	'de' => 'Немски',
	'no' => 'Норвежки',
	'pl' => 'Полски',
	'pt' => 'Португалски',
	'ro' => 'Румънски',
	'ru' => 'Руски',
	'sa' => 'Санскрит',
	'sk' => 'Словашки',
	'sl' => 'Словенски',
	'sr' => 'Сръбски',
	'grc' => 'Старогръцки',
	'hr' => 'Хърватски',
	'tr' => 'Турски',
	'hu' => 'Унгарски',
	'fi' => 'Фински',
	'fr' => 'Френски',
	'hi' => 'Хинди',
	'nl' => 'Холандски',
	'cs' => 'Чешки',
	'sv' => 'Шведски',
	'jp' => 'Японски',
);
function langName($code, $asUpper = true) {
	global $langs;
	if ( !array_key_exists($code, $langs) ) return '';
	$name = $langs[$code];
	return $asUpper ? $name : mystrtolower($name);
}

$countries = array(
	'au' => 'Австралия',
	'at' => 'Австрия',
	'al' => 'Албания',
	'ar' => 'Аржентина',
	'am' => 'Армения',
	'be' => 'Белгия',
	'br' => 'Бразилия',
	'bg' => 'България',
	'gb' => 'Великобритания',
	'de' => 'Германия',
	'gr' => 'Гърция',
	'dk' => 'Дания',
	'in' => 'Индия',
	'ie' => 'Ирландия',
	'es' => 'Испания',
	'it' => 'Италия',
	'ca' => 'Канада',
	'mx' => 'Мексико',
	'md' => 'Молдова',
	'no' => 'Норвегия',
	#'uk' => 'Обединено кралство',
	'pl' => 'Полша',
	'pt' => 'Португалия',
	'ro' => 'Румъния',
	'ru' => 'Русия',
	'us' => 'САЩ',
	'sk' => 'Словакия',
	'si' => 'Словения',
	'rs' => 'Сърбия',
	'tr' => 'Турция',
	'ua' => 'Украйна',
	'hu' => 'Унгария',
	'fr' => 'Франция',
	'nl' => 'Холандия',
	'hr' => 'Хърватия',
	'cz' => 'Чехия',
	'ch' => 'Швейцария',
	'se' => 'Швеция',
	'yu' => 'Югославия',
	'jp' => 'Япония',
);
function countryName($code, $default = '') {
	global $countries;
	return isset($countries[$code]) ? $countries[$code] : $default;
}


$months = array(
	1 => 'Януари', 'Февруари', 'Март', 'Април', 'Май', 'Юни',
	'Юли', 'Август', 'Септември', 'Октомври', 'Ноември', 'Декември'
);
function monthName($m, $asUpper = true) {
	$name = $GLOBALS['months'][(int)$m];
	return $asUpper ? $name : mystrtolower($name);
}

$serSuffices = array('series' => '',
	'collection' => ' (сборник)',
	'book' => ' (книга)');
function seriesSuffix($seriesType) {
	return isset($GLOBALS['serSuffices'][$seriesType]) ? $GLOBALS['serSuffices'][$seriesType] : '';
}


$cyrUppers = 'А Б В Г Д Е Ж З И Й К Л М Н О П Р С Т У Ф Х Ц Ч Ш Щ Ъ Ю Я';
$cyrLowers = 'а б в г д е ж з и й к л м н о п р с т у ф х ц ч ш щ ъ ю я';
function mystrtolower($s) {
	global $cyrUppers, $cyrLowers;
	return str_replace(explode(' ', $cyrUppers), explode(' ', $cyrLowers), $s);
}


$cyrlats = array(
	'щ' => 'sht', 'ш' => 'sh', 'ю' => 'ju', 'я' => 'ja', 'ч' => 'ch',
	'ц' => 'ts',
	'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
	'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j',
	'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
	'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
	'ф' => 'f', 'х' => 'h', 'ъ' => 'y', 'ь' => 'x',

	'Щ' => 'Sht', 'Ш' => 'Sh', 'Ю' => 'Ju', 'Я' => 'Ja', 'Ч' => 'Ch',
	'Ц' => 'Ts',
	'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
	'Е' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'J',
	'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
	'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
	'Ф' => 'F', 'Х' => 'H', 'Ъ' => 'Y', 'Ь' => 'X',

	'„' => ',,', '“' => '"', '«' => '<', '»' => '>', ' — ' => ' - ',
	'№' => 'No.', '…' => '...',
);
$latcyrs = array_flip($cyrlats);
function cyr2lat($s) { return strtr($s, $GLOBALS['cyrlats']); }
function lat2cyr($s) { return strtr($s, $GLOBALS['latcyrs']); }


$patternsBeforeRegExp = array(
	#'&' => '&amp;',
	"\\\n" => '<br />', // backslash at the end of a line
	"\t" => '    ',
);
$curImgDir = 'img/';
$regPatterns = array(
	'/\n *====== (.+)/' => "\n<h6>$1</h6>",
	'/\n *===== (.+)/' => "\n<h5>$1</h5>",
	'/\n *==== (.+)/' => "\n<h4>$1</h4>",
	'/\n *=== (.+)/' => "\n<h3>$1</h3>",
	'/\n *== (.+)/' => "\n<h2>$1</h2>",
	#'/\n *= (.+)/' => "\n<h1>$1</h1>",
	'/(?<=[\s([„>])__(.+)__(?=[]\s.,;:!?)“])/Ums' => '<strong>$1</strong>',
	'/(?<=[\s([„>])_(.+)_(?=[]\s.,;:!?)“])/Ums' => '<em>$1</em>',
	#'/__(.+)__/U' => '<strong>$1</strong>',
	#'/_(.+)_/U' => '<em>$1</em>',
	'/\[\[(.+)\|(.+)\]\]/Us' => '<a href="$1" title="$1 — $2">$2</a>',
	'#(?<=[\s>])(\w+://[^])\s"<]+)([^])\s"<,.;!?])#' => '<a href="$1$2" title="$1$2">$1$2</a>',
	'/\n *--+\n/' => "\n<hr />\n",
	'/\n    +\* \* \*/' => "\n<p class='separator'>* * *</p>",
	#'/\n\n(?=\n+)/' => "\n<p class=\"separator\">&nbsp;</p>",
	'/\n *(?=\n)/' => "\n<p>&nbsp;</p>",
	// four spaces start a paragraph
	#'%\n    (.+)(?=\n    |\n\t|</?div *|</?blockquote *|</?pre *|<p *|<h\d *|\n<!--|\n-->)%Ums' => "\n<p>$1</p>",
	'%\n    (.+)(?=\n    |</?div|</?blockquote|</?pre|<p|<h|\n<!--|\n-->)%Ums' => "\n<p>$1</p>",
	// notes
	'/\[\*(\d+)\]/' => '<sup><a id="n-$1" name="n-$1" href="#nb-$1">[$1]</a></sup>',
	'/\[\*\*(\d+)\]/' => '<a id="nb-$1" name="nb-$1" href="#n-$1">[$1]</a>',
);
function wiki2html($s, $full = false) {
	global $patternsBeforeRegExp, $regPatterns, $curImgDir;
	$s = "\n". $s ."\n     ";
	$s = strtr($s, $patternsBeforeRegExp);
	$regPatterns['/{img:(.+)}/U'] =
		'<p class="img"><img src="'.$curImgDir.'$1" alt="$1" /></p>';
	$s = preg_replace(array_keys($regPatterns), array_values($regPatterns), $s);
	if ($full) {
		$s = expandTemplates($s);
		$s = explainAcronyms($s);
	}
	return $s;
}


function formatNumber($num, $decPl = 2, $decPoint = ',', $tousandDelim = ' ') {
	return number_format($num, $decPl, $decPoint, $tousandDelim);
}

$acronyms = array(
	'CSS' => 'Cascading Style Sheets',
	'HTML' => 'Hypertext Markup Language',
	'XHTML' => 'Extended Hypertext Markup Language',
	'UTF-8' => '8-bit Unicode Transformation Format',
	'RTF' => 'Rich Text Format',
);
function explainAcronyms($s) {
	global $acronyms;
	foreach ($acronyms as $acronym => $expl) {
		$s = preg_replace("/(?<=[\s(])$acronym/", "<acronym title='$expl'>$0</acronym>", $s);
	}
	return $s;
}
$templates = array(
	'{SITENAME}' => '{SITENAME}',
);
function expandTemplates($s) {
	return strtr($s, $GLOBALS['templates']);
}
function addTemplate($key, $val) {
	$GLOBALS['templates']['{'.$key.'}'] = $val;
}

function extract2object($assocArray, &$object) {
	foreach ( (array) $assocArray as $key => $val ) {
		if ( ctype_alnum($key{0}) ) {
			$object->$key = $val;
		}
	}
}

function header_encode($header) {
	return '=?UTF-8?B?'.base64_encode($header).'?=';
}

/**
 * Копира някои кирилски букви от местата им според cp866 на местата им
 * според нестандартното досовско кирилско кодиране MIK.
 * В крайна сметка въпросните букви ще се намират по два пъти в новополученото
 * кодиране, което означава, че кирилицата ще се вижда хем при cp866, хем при MIK.
 * Въобще не прави пълно прекодиране между двете кодови таблици.
 */
function cp8662mik($s) {
	return strtr($s, array(
		chr(0xB0) => chr(0xE0),
		chr(0xB1) => chr(0xE1),
		chr(0xB2) => chr(0xE2),
		chr(0xB3) => chr(0xE3),
		chr(0xB4) => chr(0xE4),
		chr(0xB5) => chr(0xE5),
		chr(0xB6) => chr(0xE6),
		chr(0xB7) => chr(0xE7),
		chr(0xB8) => chr(0xE8),
		chr(0xB9) => chr(0xE9),
		chr(0xBA) => chr(0xEA),
		chr(0xBB) => chr(0xEB),
		chr(0xBC) => chr(0xEC),
		chr(0xBD) => chr(0xED),
		chr(0xBE) => chr(0xEE),
		chr(0xBF) => chr(0xEF)
		)
	);
}

function isSpam($cont, $lc = 2) {
	return substr_count($cont, 'http://') > $lc;
}

function cartesian_product($arr1, $arr2) {
	$prod = array();
	foreach ($arr1 as $val1) {
		foreach ($arr2 as $val2) {
			$prod[] = $val1 . $val2;
		}
	}
	return $prod;
}

function normInt($val, $max, $min = 1) {
	if ($val > $max) $val = $max;
	elseif ($val < $min) $val = $min;
	return (int) $val;
}

/**
 * @param $key Key
 * @param $data Associative array
 * @param $defKey Default key
 * @return $key if it exists as key in $data, otherwise $defKey
 */
function normKey($key, $data, $defKey) {
	return array_key_exists($key, $data) ? $key : $defKey;
}

/**
 * @param $val Value
 * @param $data Associative array
 * @param $defVal Default value
 * @return $val if it exists in $data, otherwise $defVal
 */
function normVal($val, $data, $defVal) {
	return in_array($val, $data) ? $val : $defVal;
}

function isArchive($file) {
	$exts = array('zip', 'tgz', 'tar.gz');
	foreach ($exts as $ext) {
		if ( strpos($file, '.'.$ext) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Validates an e-mail address
 * Regexps are taken from http://www.iki.fi/markus.sipila/pub/emailvalidator.php
 * (author: Markus Sipilä, version: 1.0, 2006-08-02)
 * @param string $input E-mail address to be validated
 * @return int 1 if valid, 0 if not valid, -1 if valid but strange
 */
function validateEmailAddress($input) {
	if ( empty($input) ) { return 1; }
	$ct = '[a-zA-Z0-9-]';
	$cn = '[a-zA-Z0-9_+-]';
	$cr = '[a-zA-Z0-9,!#$%&\'\*+\/=?^_`{|}~-]';
	$normal = "/^$cn+(\.$cn+)*@$ct+(\.$ct+)*\.([a-z]{2,4})$/";
	$rare   = "/^$cr+(\.$cr+)*@$ct+(\.$ct+)*\.([a-z]{2,})$/";
	if ( preg_match($normal, $input) ) { return 1; }
	if ( preg_match($rare, $input) ) { return -1; }
	return 0;
}

function dpr($arr) { echo '<pre>'.print_r($arr, true).'</pre>'; }
function dprbt() { echo '<pre>'; debug_print_backtrace(); echo '</pre>'; }

