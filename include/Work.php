<?php

class Work {

	public function __construct($fields = array()) {
		extract2object($fields, $this);
		if ( empty($this->year) ) {
			$this->year = $this->author_year;
		}
		if ( empty($this->trans_year) ) {
			$this->trans_year = $this->translator_year;
		}
		$this->subtitle = strtr($this->subtitle, array('\n' => "<br />\n"));
	}


	public function getYear() {
		return $this->year . (empty($this->year2) ? '' : '–'.$this->year2);
	}

	public function getTransYear() {
		return $this->trans_year . (empty($this->trans_year2) ? '' : '–'.$this->trans_year2);
	}


	public function getTitleAsSfb() {
		$title = "|\t". $this->title;
		if ( !empty($this->subtitle) ) {
			$title .= "\n|\t". strtr($this->subtitle, array("<br />\n"=>"\n|\t"));
		}
		return $title;
	}


	public function getOrigTitleAsSfb() {
		if ( $this->orig_lang == $this->lang ) {
			return '';
		}
		$authors = '';
		foreach ($this->authors as $author) {
			$authors .= ', '. $author['orig_name'];
		}
		$authors = ltrim($authors, ', ');
		$orig_title = $this->orig_title;
		if ( !empty($this->orig_subtitle) ) {
			$orig_title .= " ({$this->orig_subtitle})";
		}
		$orig_title .= ', '. $this->getYear();
		$orig_title = rtrim($orig_title, ', ');
		return "\t$authors\n\t$orig_title";
	}


	public function getCopyright($out = null) {
		if ( is_object($out) ) {
			$lis = '<li>'; $lie = '</li>';
		} else {
			$lis = $lie = '';
		}
		$c = '';
		if ($this->lo_copyright) {
			if ( count($this->authors) > 1 ) {
				foreach ($this->authors as $author) {
					$year = empty($author['year']) ? $this->getYear() : $author['year'];
					$name = is_object($out)
						? $out->makeAuthorLink($author['name'], 'first')
						: $author['name'];
					$c .= "\n\t{$lis}© $year $name$lie";
				}
			} else {
				$name = is_object($out)
					? $out->makeAuthorLink($this->author_name, 'first',
						"\n\t{$lis}© ". $this->getYear() .' ', $lie)
					: "\n\t© ". $this->getYear() .' '. $this->author_name;
				$c .= $name;
			}
		}
		if ( $this->lt_copyright && !empty($this->translators) ) {
			$lang = langName($this->orig_lang, false);
			if ( !empty($lang) ) $lang = ' от '.$lang;
			$name = is_object($out)
				? $out->makeTranslatorLink($this->translator_name, 'first',
					"\n\t{$lis}© ". $this->getTransYear() .' ', ", превод$lang$lie")
				: "\n\t© ". $this->getTransYear() .' '. $this->translator_name .", превод$lang";
			$c .= $name;
		}
		return $c;
	}


	public static function newFromId($id, $reader = 0) {
		return self::newFromDB( array('t.id'=>$id), $reader );
	}

	public static function newFromTitle($title, $reader = 0) {
		return self::newFromDB( array('t.title'=>$title), $reader );
	}

	protected static function newFromDB($dbkey, $reader = 0) {
		$db = Setup::db();
		$q = "SELECT t.*, s.name series,
				lo.code lo_code, lo.name lo_name, lo.copyright lo_copyright,
				lt.code lt_code, lt.name lt_name, lt.copyright lt_copyright,
				r.user isRead
			FROM /*p*/text t
			LEFT JOIN /*p*/series s ON t.series = s.id
			LEFT JOIN /*p*/license lo ON t.license_orig = lo.id
			LEFT JOIN /*p*/license lt ON t.license_trans = lt.id
			LEFT JOIN /*p*/reader_of r ON (t.id = r.text AND r.user = $reader)";
		$q .= $db->makeWhereClause($dbkey);
		$fields = $db->fetchAssoc( $db->query($q) );
		if ( empty($fields) ) return null;
		$fields['collection'] = $db->s2b($fields['collection']);
		$fields['lo_copyright'] = $db->s2b($fields['lo_copyright']);
		$fields['lt_copyright'] = $db->s2b($fields['lt_copyright']);

		$roles = array('author', 'translator');
		foreach ($roles as $role) {
			$pl = $role .'s';
			$query = "SELECT p.*, of.year FROM /*p*/{$role}_of of
				LEFT JOIN /*p*/person p ON of.{$role} = p.id
				WHERE of.text = $fields[id]
				ORDER BY of.pos ASC";
			$res = $db->query($query);
			$persons = array();
			$string_name = $string_year = '';
			while ( $data = $db->fetchAssoc($res) ) {
				$persons[] = $data;
				$string_name .= ', '. $data['name'];
				$string_year .= ', '. $data['year'];
			}
			$fields[$pl] = $persons;
			$fields[$role.'_name'] = ltrim($string_name, ', ');
			$fields[$role.'_year'] = ltrim($string_year, ', 0');
		}
		return new Work($fields);
	}
}
?>
