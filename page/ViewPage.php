<?php

class ViewPage extends Page {

	protected $FF_MODE = 'mode', $FF_ORDER = 'order', $FF_COUNTRY = 'country',
		$FF_DLMODE = 'dlMode';
	protected $titles = array('simple' => '', 'extended' => '');
	protected $showHeaders = true;

	public function __construct() {
		parent::__construct();
		$this->action = 'view';

		$defMode = 'simple-toc';
		$this->startwith = $this->request->value('startwith',
			$this->request->value($this->FF_QUERY, ''));
		$this->param1 = $this->request->param(1);
		if ( !empty($this->param1) && strpos($this->param1, '=') === false ) {
			$this->startwith = $this->param1;
			$defMode = 'extended';
		}
		$this->startwith = str_replace('\'', '’', $this->startwith);
		$this->prefix = $this->request->value('prefix', '');
		if ($this->prefix == '%') { $this->expandSearchString(); }
		else { $this->startwith = $this->prefix . $this->startwith; }

		$this->order = $this->request->value($this->FF_ORDER);
		$this->country = $this->request->value($this->FF_COUNTRY);
		$this->dlMode = $this->request->value($this->FF_DLMODE, 'one');
		$this->showDlForm = $this->dlMode == 'both';

		$this->mode = $this->request->value($this->FF_MODE, $defMode);
		$modes = explode('-', $this->mode);
		$this->mode1 = isset($this->titles[ $modes[0] ]) ? $modes[0] : '';
		$this->mode2 = isset( $modes[1] ) ? $modes[1] : '';

		$this->title = $this->titles[$this->mode1];
		$this->title .= $this->makeTitleSuffix($this->mode2);
	}


	protected function expandSearchString($prefix = '%') {
		$this->startwith = $prefix . str_replace(' ', '% ', $this->startwith);
	}


	protected function buildContent() {
		if ($this->mode2 == 'toc') {
			return $this->makeNavigation();
		}
		$list = $this->mode1 == 'extended'
			? $this->makeExtendedList() : $this->makeSimpleList();
		if ($list == false) {
			$this->addEmptyListMessage();
			return $this->makeNavigation();
		}
		$navElem = $this->makeNavElements();
		$o = <<<EOS
<p class="non-graphic">Към <a href="#right-before-lists">показаните списъци</a></p>
$navElem
<p id="right-before-lists"><a name="right-before-lists"> </a></p>
$list
EOS;
		return $o;
	}

	// should be overridden by the children classes
	protected function makeSimpleList() { return ''; }
	protected function makeExtendedList() { return $this->makeSimpleList(); }
	protected function makeNavElements() { return ''; }
	protected function makeExplanations() { return ''; }


	protected function makeNavigation() {
		$navElem = $this->makeNavElements();
		$expl = $this->makeExplanations();
		return <<<EOS
<p class="non-graphic">По-долу има
<a href="#explanations">разяснения</a> на следващите връзки и на формуляра.</p>
$navElem

<div id="explanations" style="margin-top:1em"><a name="explanations"> </a>
$expl
</div>
EOS;
	}


	/**
	 * Create navigational buttons from the alphabet letters
	 * @param array $extraParams Associative array (var name => default value)
	 *        The variables with these names should be added to the query
	 *        string if their values aren't equal the values from this array,
	 *        or if the array value starts with "!"
	 * @param bool $checkSelfLink Check if the global page url is the same
	 *        as the current processed; if so don't create link
	 * @return string
	 */
	protected function makeNavButtons($extraParams = array(), $checkSelfLink = true) {
		$q = '';
		foreach ($extraParams as $name => $defval) {
			if (!empty($defval) && $defval{0} == '!') {
				$q .= "/$name=". substr($defval, 1);
			} elseif ($this->$name != $defval) {
				$q .= "/$name={$this->$name}";
			}
		}
		$o = '';
		$isFirst = true;
		$mode = $this->mode == 'extended-toc' ? 'extended' : 'simple';
		foreach ( explode(' ', $GLOBALS['cyrUppers']) as $let ) {
			$o .= !$checkSelfLink || $this->mode2 == 'toc' || $this->startwith != $let
				? "\n<a href=\"$this->root/$this->action".
					"/$this->FF_QUERY=". $this->urlencode($let) .
					"/$this->FF_MODE=$mode$q\">$let</a>"
				: "\n". ($isFirst ? '' : '– ') ."<strong>$let</strong> –" ;
			$isFirst = false;
		}
		$o .= !$checkSelfLink || $this->mode2 == 'toc' || !empty($this->startwith)
			? "\n<a href=\"$this->root/$this->action".
				"/$this->FF_MODE=$mode$q\">Всички</a>"
			: "\n– <strong>Всички</strong>";
		return $o;
	}


	protected function makeTitleSuffix($mode) {
		if ($mode == 'toc') { return 'Съдържание'; }
		if ( empty($this->startwith) ) { return 'Всички'; }
		return trim( strtr($this->startwith, '%', ' ') );
	}


	protected function makeModeInput() {
		$this->request->setValue($this->FF_QUERY, $this->startwith);
		$ext = !empty($this->mode2) ? '-'.$this->mode2 : '';
		$opts = array('simple'.$ext=>'Само имена', 'extended'.$ext=>'Имена и заглавия');
		$box = $this->out->selectBox($this->FF_MODE, '', $opts, $this->mode1.$ext,
			0, 'onchange="this.form.submit()"');
		return "<label for='$this->FF_MODE'>Показване:</label>&nbsp;" . $box;
	}


	protected function makeOrderInput() {
		$opts = array('alpha' => 'Азбучна', 'time' => 'Хронологична');
		$box = $this->out->selectBox($this->FF_ORDER, '', $opts, $this->order,
			0, 'onchange="this.form.submit()"');
		return "<label for='$this->FF_ORDER'>Подредба:</label>&nbsp;" . $box;
	}


	protected function makeCountryInput() {
		global $countries;
		$opts = array_merge(array('' => '(Всички)'), $countries);
		$box = $this->out->selectBox($this->FF_COUNTRY, '', $opts, $this->country,
			0, 'onchange="this.form.submit()"');
		return "<label for='$this->FF_COUNTRY'>Държава:</label>&nbsp;" . $box;
	}


	protected function makeDlModeInput() {
		$opts = array('one' => 'По единично', 'both' => 'И по много');
		$box = $this->out->selectBox($this->FF_DLMODE, '', $opts, $this->dlMode,
			0, 'onchange="this.form.submit()"');
		return "<label for='$this->FF_DLMODE'>Сваляне:</label>&nbsp;" . $box;
	}


	protected function makeModeExplanation() {
		return <<<EOS

<p>От падащото меню „Показване“ можете да настроите режима на показване:
<em>само имена</em> (обикновен списък) или <em>имена и заглавия</em>
(разширен списък, включващ и заглавия на произведения).</p>

EOS;
	}


	protected function makeCountryExplanation() {
		return <<<EOS

<p>Ако изберете държава, всички препратки ще се напаснат и ще се показват само
авторите от тази държава. Чрез <em>(Всички)</em> се избират всички държави,
а чрез <em>(Без посочена)</em> се показват само авторите, при които липсват
данни за държава.</p>

EOS;
	}


	protected function makeDlModeExplanation() {
		return <<<EOS

<p>По подразбиране режимът на сваляне е „По единично“, което означава, че за
всяко показано заглавие ще има връзка за свалянето на текста. Ако обаче за
режим на сваляне изберете „И по много“, освен единичните връзки, ще се покаже
и формуляр, чрез който ще можете да свалите няколко текста наведнъж.
Формулярът се състои от полета за отмятане, намиращи се преди всяко заглавие,
и бутон за пращане на заявката.</p>

<p>Ако сте решили да сваляте по няколко текста наведнъж, е добре да правите
това само за кратките произведения, тъй като иначе много ще натоварите
сървъра и заявката ви най-вероятно няма да бъде изпълнена.</p>

EOS;
	}


	protected function makeDlCheckbox($textId) {
		return $this->showDlForm
			? $this->out->checkbox('textId[]', 'text'.$textId, false, '', $textId)
			: '';
	}


	protected function makeDlSubmit() {
		return $this->showDlForm
			? $this->out->submitButton('Сваляне на избраните текстове')
			: '';
	}


	protected function makeDlSeriesForm($ids, $serName, $button = '') {
		$inp = '';
		foreach ($ids as $id) {
			$inp .= "\n\t".$this->out->hiddenField('textId[]', $id);
		}
		if ( empty($button) ) $button = 'цялата поредица';
		$action = $this->out->hiddenField('action', 'download');
		$filename = $this->out->hiddenField('filename', $serName);
		$submit = $this->out->submitButton('Сваляне на '.$button);
		return <<<EOS

<form action="$this->root" method="post">
	$action
	$filename
	$inp
	$submit
</form>
EOS;
	}


	protected function makeColorLegend() {
		return <<<EOS

<fieldset id="legend">
<legend>Цветова легенда</legend>
<ul class="titles">
	<li class="shortstory"><a href="#">Разказ, новела</a> — късо произведение</li>
	<li class="novella"><a href="#">Повест</a> — среднодълго произведение</li>
	<li class="novel"><a href="#">Роман</a> — дълго произведение</li>
	<li class="poetry"><a href="#">Поезия</a> — поетично произведение</li>
	<li class="tale"><a href="#">Приказка, басня</a></li>
	<li class="othergenre"><a href="#">Друго</a> — друг тип произведение,
	например <em>есе</em>, <em>пиеса</em>, <em>статия</em></li>
</ul>
</fieldset>
EOS;
	}


	protected function addEmptyListMessage() {
		$this->addMessage('Няма съвпадения.', true);
	}

}
?>
