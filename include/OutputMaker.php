<?php

class OutputMaker {

	public
		$inencoding = Setup::IN_ENCODING, $outencoding = Setup::IN_ENCODING;
	protected
		$defArgSeparator = '&', $argSeparator = '&',
		$queryStart = '?',
		$hasPathInfo = false;

	public function textField($name, $id = '', $value = '', $size = 30,
			$maxlength = 255, $tabindex = null, $title = '', $attrs = array()) {
		fillOnEmpty($id, $name);
		$attrs = array(
			'type' => 'text', 'name' => $name, 'id' => $id,
			'size' => $size, 'maxlength' => $maxlength,
			'value' => $value, 'title' => $title, 'tabindex' => $tabindex
		) + $attrs;
		return $this->xmlElement('input', null, $attrs);
	}

	public function textarea($name, $id = '', $value = '', $rows = 5, $cols = 80,
			$tabindex = null, $attrs = array()) {
		fillOnEmpty($id, $name);
		$attrs = array(
			'name' => $name, 'id' => $id,
			'cols' => $cols, 'rows' => $rows, 'tabindex' => $tabindex
		) + $attrs;
		return $this->xmlElement('textarea', htmlspecialchars($value), $attrs);
	}

	public function checkbox($name, $id = '', $checked = false, $label = '',
			$value = null, $tabindex = null, $attrs = array()) {
		fillOnEmpty($id, $name);
		$attrs = array(
			'type' => 'checkbox', 'name' => $name, 'id' => $id,
			'value' => $value, 'tabindex' => $tabindex
		) + $attrs;
		if ($checked) { $attrs['checked'] = 'checked'; }
		if ( !empty($label) ) {
			$label = $this->label($label, $id);
		}
		return $this->xmlElement('input', null, $attrs) . $label;
	}

	public function hiddenField($name, $value = '') {
		$attrs = array('type' => 'hidden', 'name' => $name, 'value' => $value);
		return $this->xmlElement('input', null, $attrs);
	}

	public function passField($name, $id = '', $value = '', $size = 30,
			$maxlength = 255, $tabindex = null, $attrs = array()) {
		fillOnEmpty($id, $name);
		$attrs = array(
			'type' => 'password', 'name' => $name, 'id' => $id,
			'size' => $size, 'maxlength' => $maxlength, 'value' => $value,
			'tabindex' => $tabindex
		) + $attrs;
		return $this->xmlElement('input', null, $attrs);
	}


	public function fileField($name, $id = '', $size = 30, $tabindex = null,
			$title = '', $attrs = array()) {
		fillOnEmpty($id, $name);
		$attrs = array(
			'type' => 'file', 'name' => $name, 'id' => $id,
			'size' => $size, 'title' => $title, 'tabindex' => $tabindex
		) + $attrs;
		return $this->xmlElement('input', null, $attrs);
	}


	public function submitButton($value, $title = '', $tabindex = null,
			$putname = true, $attrs = array()) {
		$attrs = array(
			'type' => 'submit', 'value' => $value, 'title' => $title,
			'tabindex' => $tabindex
		) + $attrs;
		if ( is_string($putname) ) {
			$attrs['name'] = $putname;
		} else if ($putname) {
			$attrs['name'] = 'submitButton';
		}
		return $this->xmlElement('input', null, $attrs);
	}

	public function selectBox($name, $id = '', $opts = array(), $selId = 0,
			$tabindex = null, $attrs = array()) {
		$o = '';
		foreach ($opts as $key => $opt) {
			if ( is_object($opt) ) {
				$key = $opt->id;
				$opt = $opt->name;
			}
			$oattrs = array('value' => $key);
			if ($key == $selId) $oattrs['selected'] = 'selected';
			$o .= "\n\t". $this->xmlElement('option', $opt, $oattrs);
		}
		fillOnEmpty($id, $name);
		$attrs = array(
			'name' => $name, 'id' => $id, 'tabindex' => $tabindex
		) + $attrs;
		return $this->xmlElement('select', $o, $attrs);
	}


	public function link($url, $text = '', $title = '', $attrs = array(),
			$args = array()) {
		$q = array();
		foreach ($args as $field => $value) {
			$q[] = $field .'='. $value;
		}
		if ( !empty($q) ) {
			$url .= implode($this->argSeparator, $q);
		}
		fillOnEmpty($text, $url);
		$attrs = array('href' => $url, 'title' => $title) + $attrs;
		return $this->xmlElement('a', $this->escape($text), $attrs);
	}


	public function listItem($item) {
		return "\n\t<li>$item</li>";
	}

	public function image($url, $alt, $title = '', $attrs = array()) {
		fillOnEmpty($title, $alt);
		$attrs = array(
			'src' => $url, 'alt' => $alt, 'title' => $title
		) + $attrs;
		return $this->xmlElement('img', null, $attrs);
	}

	public function label($text, $for, $title = '', $attrs = array()) {
		$attrs = array(
			'for' => $for, 'title' => $title
		) + $attrs;
		return $this->xmlElement('label', $text, $attrs);
	}


	public function internLink($text, $params, $ignorePos = 1, $title = '',
			$attrs = array(), $anchor = '') {
		$attrs = array(
			'href' => $this->internUrl($params, $ignorePos, $anchor),
			'title' => $title
		) + $attrs;
		return $this->xmlElement('a', $text, $attrs);
	}

	/**
	Generates an intern URL.

	@param $params Associative array (param => value), or string
	@param $ignorePos Field names can be ignored up to this position
	@param $anchor The link should point to this anchor
	@return string

	Usage:
	$params = array('action' => 'text', 'textId' => 2, 'chunkId' => 3);
	OutputMaker::internUrl($params, 0) returns ROOT/action=text/textId=2/chunkId=3
	OutputMaker::internUrl($params, 1) returns ROOT/text/textId=2/chunkId=3
	OutputMaker::internUrl($params, 2) returns ROOT/text/2/chunkId=3
	OutputMaker::internUrl($params, 3) returns ROOT/text/2/3

	If OutputMaker::$hasPathInfo is false, $ignorePos is set to 0
	*/
	public function internUrl($params, $ignorePos = 1, $anchor = '') {
		$url = '{ROOT}';
		if ( is_string($params) ) {
			$params = array(Page::FF_ACTION => $params);
		}
		if ($this->hasPathInfo) {
			for ($i = 0; $i < $ignorePos; $i++) {
				list($param, $value) = each($params);
				unset($params[$param]);
				$url .= $this->argSeparator . $this->urlencode($value);
			}
			if ( !empty($params) ) { // there are some other parameters
				$url .= $this->argSeparator;
			}
		} else {
			$url .= $this->queryStart;
		}
		$q = array();
		foreach ($params as $param => $value) {
			$q[] = $param .'='. $this->urlencode($value);
		}
		$url .= implode($this->argSeparator, $q);
		if ( !empty($anchor) ) {
			$url .= '#'. $anchor;
		}
		return $url;
	}


	public function scriptInclude($script) {
		$attrs = array(
			'type' => 'text/javascript', 'src' => '{DOCROOT}script/'. $script
		);
		return $this->xmlElement('script', '', $attrs);
	}


	public function xmlElement($name, $content = '', $attrs = array()) {
		$end = is_null($content) ? ' />' : ">$content</$name>";
		return '<'.$name . $this->makeAttribs($attrs) . $end;
	}

	public function makeAttribs($attrs) {
		$o = '';
		foreach ($attrs as $attr => $value) {
			$o .= $this->attrib($attr, $value);
		}
		return $o;
	}

	public function attrib($attrib, $value) {
		if ( is_null($value) ) {
			return '';
		}
		return ' '. $attrib .'="'. htmlspecialchars($value) .'"';
	}


	public function simpleTable($caption, $data, $attrs = array()) {
		$ext = $this->makeAttribs($attrs);
		$t = "<table class='content'$ext><caption>$caption</caption>";
		$curRowClass = '';
		foreach ($data as $row) {
			$curRowClass = $this->nextRowClass($curRowClass);
			$t .= "\n<tr class='$curRowClass'>";
			foreach ($row as $cell) {
				$t .= "<td>$cell</td>";
			}
			$t .= "\n</tr>";
		}
		return $t.'</table>';
	}


	public function multicolTable($data, $colcount = 3, $width = '100%', $class = '') {
		$t = '';
		$curRowClass = '';
		$count = count($data);
		for ($i = 0; $i < $count; $i += $colcount) { // rows
			$curRowClass = $this->nextRowClass($curRowClass);
			$t .= "\n<tr class='$curRowClass'>";
			for ($j = 0; $j < $colcount; $j++) { // cols
				$c = isset($data[$i+$j]) ? $data[$i+$j] : '';
				$t .= "\n\t<td>$c</td>";
			}
			$t .= "\n</tr>";
		}
		$colwidth = floor(100 / $colcount);
		$cols = '';
		for ($i = 0; $i < $colcount; $i++) {
			$cols .= "<col width='{$colwidth}%' />";
		}
		return <<<EOS
<table class="$class" border="0" style="width:$width">
	<colgroup>
		$cols
	</colgroup>
$t
</table>
EOS;
	}


	public function nextRowClass($curRowClass = '') {
		return $curRowClass == 'odd' ? 'even' : 'odd';
	}


	public function obfuscateEmail($email) {
		return strtr($email,
			array('@' => '&nbsp;<span title="при сървъра">(при)</span>&nbsp;'));
	}


	public function addUrlQuery($url, $args) {
		if ( strpos($url, $this->queryStart) === false ) {
			$url .= $this->queryStart;
		}
		foreach ((array) $args as $key => $val) {
			$sep = $this->getArgSeparator($url);
			$url = preg_replace("!$sep$key=[^$sep]*!", '', $url);
			$url .= $sep . $key .'='. $this->urlencode($val);
		}
		return $url;
	}

	public function setHasPathInfo($hasPathInfo) {
		$this->hasPathInfo = $hasPathInfo;
		if ($this->hasPathInfo) {
			$this->argSeparator = $this->queryStart = '/';
		}
	}

	public function getArgSeparator($url = '') {
		if ( empty($url) || strpos($url, $this->defArgSeparator) === false ) {
			return $this->argSeparator;
		}
		return $this->defArgSeparator;
	}

	public function hasPathInfo() {
		return $this->hasPathInfo;
	}

	public function escape($s) {
		return strtr($s, array('&'=>'&amp;'));
	}

	public function urlencode($str) {
		#$str = iconv($this->inencoding, $this->outencoding, $str);
		$enc = urlencode($str);
		if ( strpos($str, '/') !== false ) {
			$enc = strtr($enc, array('%2F' => '/'));
		}
		return $enc;
	}
}
