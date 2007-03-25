<?php

/*
TODO Не ми харесва името на класа - да се преименува или да се обедини със Skin
*/
class OutputMaker {

	protected $argSeparator = '&amp;', $hasPathInfo = true;

	public function textField($name, $id = '', $value = '', $size = 30,
			$maxlength = 255, $tabindex = 0, $title = '', $extraAttr = '') {
		if ( empty($id) ) $id = $name;
		$tab = $this->tabindex($tabindex);
		$value = htmlspecialchars($value);
		return "<input type='text' name='$name' id='$id' size='$size' maxlength='$maxlength'$tab value=\"$value\" title='$title' $extraAttr />";
	}

	public function textarea($name, $id = '', $value = '', $rows = 5, $cols = 80,
			$tabindex = 0, $extraAttr = '') {
		if ( empty($id) ) $id = $name;
		$tab = $this->tabindex($tabindex);
		$value = htmlspecialchars($value);
		return "<textarea name='$name' id='$id' cols='$cols' rows='$rows'$tab $extraAttr>$value</textarea>";
	}

	public function checkbox($name, $id = '', $checked = false, $label = '',
			$value = '', $tabindex = 0) {
		if ( empty($id) ) $id = $name;
		$ch = $checked ? ' checked="checked"' : '';
		$tab = $this->tabindex($tabindex);
		if ( !empty($value) ) $value = " value=\"$value\"";
		if ( !empty($label) ) $label = "<label for='$id'>$label</label>";
		return "<input type='checkbox' name='$name' id='$id'$value$ch$tab />$label";
	}

	public function hiddenField($name, $value = '') {
		$value = htmlspecialchars($value);
		return "<input type='hidden' name='$name' value=\"$value\" />";
	}

	public function passField($name, $id = '', $value = '', $size = 30,
			$maxlength = 255, $tabindex = 0, $extraAttr = '') {
		if ( empty($id) ) $id = $name;
		$tab = $this->tabindex($tabindex);
		$value = htmlspecialchars($value);
		return "<input type='password' name='$name' id='$id' size='$size' maxlength='$maxlength'$tab value=\"$value\" $extraAttr />";
	}


	public function fileField($name, $id = '', $size = 30, $tabindex = 0,
			$title = '', $extraAttr = '') {
		if ( empty($id) ) $id = $name;
		$tab = $this->tabindex($tabindex);
		return "<input type='file' name='$name' id='$id' size='$size'$tab title='$title' $extraAttr />";
	}


	public function submitButton($value, $title = '', $tabindex = 0, $name = '',
			$extraAttr = '') {
		$value = htmlspecialchars($value);
		$title = htmlspecialchars($title);
		$tab = $this->tabindex($tabindex);
		if ( empty($name) ) $name = 'submitButton';
		return "<input type='submit' name='$name' value=\"$value\" title='$title'$tab $extraAttr />";
	}

	public function selectBox($name, $id = '', $opts = array(), $selId = 0,
			$tabindex = 0, $extraAttr = '') {
		$o = '';
		foreach ($opts as $key => $opt) {
			if ( is_object($opt) ) {
				$key = $opt->id; $opt = $opt->name;
			}
			$sel = $key == $selId ? ' selected="selected"' : '';
			$o .= "\n\t<option value='$key'$sel>$opt</option>";
		}
		if ( empty($id) ) $id = $name;
		$tab = $this->tabindex($tabindex);
		return "<select id='$id' name='$name'$tab $extraAttr>$o\n</select>";
	}

	public function link($text, $action = '', $qfields = array(), $title = '', $tabindex = 0) {
		return $this->genlink("{ROOT}$action", $text, $qfields, $title, $tabindex);
	}

	public function genlink($url, $text = '', $qfields = array(), $title = '', $tabindex = 0) {
		$q = array();
		foreach ($qfields as $field => $value) { $q[] = "$field=$value"; }
		$q = implode($this->argSeparator, $q);
		$url = rtrim("$url?$q", '?/');
		if ( empty($text) ) $text = $url;
		$title = htmlspecialchars($title);
		$tab = $this->tabindex($tabindex);
		return "<a href='$url' title='$title'$tab>$text</a>";
	}

	protected function tabindex($tabindex) {
		return empty($tabindex) ? '' : ' tabindex="'. ((int) $tabindex) .'"';
	}

	public function listItem($item) {
		return "\n\t<li>$item</li>";
	}

	public function image($url, $alt, $title = '', $extraAttr = '') {
		if ( empty($title) ) $title = $alt;
		return "<img src='$url' alt='$alt' title='$title' $extraAttr />";
	}

	public function label($text, $for, $title = '', $extraAttr = '') {
		return "<label for='$for' title='$title' $extraAttr>$text</label>";
	}

	/**
	@param $params array Associative array (param => value)
	@param $ignorePos int Field names can be ignored up to this position
	@return string

	Usage:
	$params = array('action' => 'text', 'textId' => 2, 'chunkId' => 3);
	OutputMaker::link($params, 0) returns ROOT?action=text&textId=2&chunkId=3
	OutputMaker::link($params, 1) returns ROOT/text?textId=2&chunkId=3
	OutputMaker::link($params, 2) returns ROOT/text/2?chunkId=3
	OutputMaker::link($params, 3) returns ROOT/text/2/3

	If OutputMaker::$hasPathInfo is false, $ignorePos is set to 0
	*/
	public function internUrl($params, $ignorePos = 0) {
		$url = '{ROOT}';
		if ($this->hasPathInfo) {
			for ($i = 0; $i < $ignorePos; $i++) {
				list($param, $value) = each($params);
				unset($params[$param]);
				$url .= $value .'/';
			}
		}
		$url = rtrim($url, '/') .'?';
		$q = array();
		foreach ($params as $param => $value) { $q[] = "$param=$value"; }
		$url .= implode($this->argSeparator, $q);
		return rtrim($url, '?');
	}
}
?>
