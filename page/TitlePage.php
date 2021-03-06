<?php

class TitlePage extends ViewPage {

	const
		FF_TYPE = 'type', FF_ORIGLANG = 'orig_lang',
		FF_LICENSE_ORIG = 'license_orig', FF_LICENSE_TRANS = 'license_trans';
	protected
		$titles = array('simple' => 'Списък на заглавия — $1'),
		$whereFields = array(
			self::FF_TYPE, self::FF_ORIGLANG,
			self::FF_LICENSE_ORIG, self::FF_LICENSE_TRANS);


	public function __construct() {
		$this->titles['extended'] = $this->titles['simple'];
		parent::__construct();
		$this->action = 'title';
		foreach ($this->whereFields as $field) {
			$this->$field = $this->request->value($field, '');
		}
		$this->woTransl = $this->request->value('woTransl', 0);
		$this->woLabel = $this->request->value('woLabel', 0);
		if ($this->order == 'time' && empty($this->startwith)) {
			$this->showHeaders = false;
		}
	}


	protected function makeSimpleList() {
		$this->userCanEdit = $this->user->canExecute('edit');
		$this->curch = '';
		$items = $this->db->iterateOverResult($this->makeSimpleListQuery(),
			'makeSimpleListItem', $this);
		if ( empty($items) ) {
			return false;
		}
		$listHack = $this->showHeaders ? ' style="display:none"><li></li>' : '>';
		$o = '<ul'.$listHack. $items .'</ul>';
		if ($this->showDlForm) {
			$action = $this->out->hiddenField(self::FF_ACTION, 'download');
			$submit = $this->out->submitButton('Сваляне на избраните текстове');
			$o = <<<EOS

<form action="$this->root" method="post">
	$action
$o
	$submit
</form>
EOS;
		}
		$o .= $this->makeColorLegend();
		return $o;
	}


	protected function makeSimpleListQuery() {
		$chrono = $this->order == 'time' ? 't.year,' : '';
		$qa = array(
			'SELECT' => 'GROUP_CONCAT(a.name ORDER BY aof.pos) author,
				t.id textId, t.title, t.orig_title, t.year, t.type, t.size,
				t.zsize, t.lang, t.orig_lang, t.collection, t.entrydate date,
				UNIX_TIMESTAMP(t.entrydate) datestamp,
				s.name series, s.orig_name orig_series',
			'FROM' => DBT_AUTHOR_OF .' aof',
			'LEFT JOIN' => array(
				DBT_TEXT .' t' => 'aof.text = t.id',
				DBT_PERSON .' a' => 'aof.person = a.id',
				DBT_SERIES .' s' => 't.series = s.id',
			),
			'WHERE' => array(),
			'GROUP BY' => 't.id',
			'ORDER BY' => "$chrono t.title",
// 			'LIMIT' => array($this->qstart, $this->qlimit)
		);
		if ($this->user->id > 0) {
			$qa['SELECT'] .= ', r.user reader';
			$qa['LEFT JOIN'][DBT_READER_OF .' r'] =
				't.id = r.text AND r.user = '. $this->user->id;
		}
		if ($this->woTransl) {
			$qa['LEFT JOIN'][DBT_TRANSLATOR_OF .' tof'] = 't.id = tof.text';
			$qa['WHERE']['t.orig_lang'] = array('!=', 'bg');
			$qa['WHERE'][] = 'tof.text IS NULL';
			$this->addMessage('Това е списък на произведенията, за които липсва
				информация за преводача. Ще е чудесно, ако можете да ми
				помогнете да я добавя!');
		}
		if ($this->woLabel) {
			$qa['LEFT JOIN'][DBT_TEXT_LABEL .' h'] = 'h.text = t.id';
			$qa['WHERE'][] = 'h.label IS NULL';
		}
		if ( !empty($this->startwith) ) {
			$qa['WHERE']['t.title'] = array('LIKE', $this->startwith .'%');
		}
		foreach ($this->whereFields as $field) {
			if ( !empty($this->$field) ) {
				$qa['WHERE']["t.$field"] = $this->$field;
			}
		}
		return $this->db->extselectQ($qa);
	}


	public function makeSimpleListItem($dbrow) {
		if ( !$this->isShownSimpleListItem($dbrow) ) {
			return;
		}
		$o = '';
		$lcurch = $this->firstChar($dbrow['title']);
		if ($this->showHeaders && $this->curch != $lcurch) {
			$this->curch = $lcurch;
			$o .= "</ul>\n<h2>$this->curch</h2>\n<ul>";
		}
		$o .= $this->makeListItemForTitle($dbrow);
		return $o;
	}


	protected function isShownSimpleListItem($dbrow) {
		return true;
	}


	protected function makeNavElements() {
		$toc = $this->makeNavButtons(array(self::FF_ORDER => $this->defOrder,
			self::FF_TYPE => '', self::FF_DLMODE => $this->defDlMode,
			self::FF_ORIGLANG => '', self::FF_LICENSE_ORIG => '',
			self::FF_LICENSE_TRANS => ''));
		$orderInput = $this->makeOrderInput(false);
		$typeInput = $this->makeTypeInput();
		$origLangInput = $this->makeOrigLangInput();
		$licenseOrigInput = $this->makeLicenseInput(self::FF_LICENSE_ORIG, $this->license_orig, 'Лиценз на оригинала:');
		$licenseTransInput = $this->makeLicenseInput(self::FF_LICENSE_TRANS, $this->license_trans, 'Лиценз на превода:');
		$dlModeInput = $this->makeDlModeInput(false);
		$inputFields = $this->request->makeInputFieldsForGetVars(
			array(self::FF_ORDER, self::FF_TYPE, self::FF_DLMODE, self::FF_ORIGLANG));
		$submit = $this->out->submitButton('Обновяване');
		return <<<EOS
<p class="buttonlinks" style="margin-bottom:1em"
	title="Това са препратки към списъци на заглавията, започващи със съответната буква">
$toc
</p>
<form action="$this->root" style="text-align:center"><div>
	$inputFields
$orderInput &nbsp;
$typeInput &nbsp;
$origLangInput &nbsp;
$dlModeInput &nbsp;
$licenseOrigInput &nbsp;
$licenseTransInput
	<div style="display:inline">$submit</div>
</div></form>
EOS;
	}


	protected function makeExplanations() {
		$typeExpl = $this->makeTypeExplanation();
		$origLangExpl = $this->makeOrigLangExplanation();
		$dlModeExpl = $this->makeDlModeExplanation();
		$random = $this->out->internLink('случайно заглавие',
			array(self::FF_ACTION=>'text', 'textId'=>'random'), 2);
		return <<<EOS
<p>Горните връзки водят към списъци на заглавията, които започват със съответната буква. Чрез препратката „Всички“ (такава има и в навигационното меню) можете да разгледате всички заглавия наведнъж. Страницата обаче е големичка.</p>

<p>Ако нямате представа какво ви се чете, можете да изпробвате късмета си с някое $random.</p>

$typeExpl

$origLangExpl

$dlModeExpl
EOS;
	}


	protected function makeTypeExplanation() {
		return <<<EOS
<p>От падащото меню „Форма“ можете да изберете формата на показваните произведения. По подразбиране се показват текстовете от всички форми.</p>
EOS;
	}


	protected function makeOrigLangExplanation() {
		return <<<EOS
<p>Чрез менюто „Оригинален език“ се указва показването само на тези произведения, чийто оригинален език е избраният.</p>
EOS;
	}


	protected function makeTypeInput() {
		$opts = array_merge(array('' => '(Всички)'), workTypes(false));
		$type = $this->out->selectBox(self::FF_TYPE, '', $opts, $this->type);
		$label = $this->out->label('Форма:', self::FF_TYPE);
		return $label .'&nbsp;'. $type;

	}


	protected function makeOrigLangInput() {
		$opts = array_merge(array('' => '(Всички)'), $GLOBALS['langs']);
		$lang = $this->out->selectBox(self::FF_ORIGLANG, '', $opts, $this->orig_lang);
		$label = $this->out->label('Оригинален език:', self::FF_ORIGLANG);
		return $label .'&nbsp;'. $lang;
	}


	protected function makeLicenseInput($id, $value, $label) {
		if ( !isset($this->_licenses) ) {
			$this->_licenses = array('' => '(Всички)');
			$sel = array('id', 'name', 'fullname');
			$res = $this->db->select(DBT_LICENSE, array(), $sel, 'name');
			while ($row = $this->db->fetchRow($res)) {
				$rid = array_shift($row);
				$this->_licenses[$rid] = $row;
			}
		}
		$box = $this->out->selectBox($id, '', $this->_licenses, $value);
		$label = $this->out->label($label, $id);
		return $label .'&nbsp;'. $box;
	}

	protected function addEmptyListMessage() {
		$this->addMessage('Няма намерени заглавия.', true);
	}
}
