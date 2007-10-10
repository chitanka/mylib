<?php

class Work {

	const DB_TABLE = DBT_TEXT;
	protected static $exts = array('.jpg', '.png');


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
		return rtrim("\t$authors\n\t$orig_title");
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


	public static function getCovers($id, $defCover = null) {
		$bases = array( getContentFilePath('cover', $id) );
		if ( !empty($defCover) ) {
			$bases[] = getContentFilePath('cover', $defCover);
		}
		$coverFiles = cartesian_product($bases, self::$exts);
		$covers = array();
		foreach ($coverFiles as $file) {
			if ( file_exists( $file ) ) {
				$covers[] = $file;
				// search for more images of the form “ID-DIGIT.EXT”
				for ($i = 2; /* infinite */; $i++) {
					$efile = strtr($file, array('.' => "-$i."));
					if ( file_exists( $efile ) ) {
						$covers[] = $efile;
					} else {
						break;
					}
				}
				break; // don’t check other exts
			}
		}
		return $covers;
	}


	public static function renameCover($cover, $newname) {
		$rexts = strtr(implode('|', self::$exts), array('.'=>'\.'));
		return preg_replace("/\d+(-\d+)?($rexts)/", "$newname$1$2", $cover);
	}


	public static function newFromId($id, $reader = 0) {
		return self::newFromDB( array('t.id'=>$id), $reader );
	}

	public static function newFromTitle($title, $reader = 0) {
		return self::newFromDB( array('t.title'=>$title), $reader );
	}


	public static function incReadCounter($id) {
		Setup::db()->update(DBT_TEXT, array('read_count=read_count+1'), compact('id'));
	}

	public static function incDlCounter($id) {
		Setup::db()->update(DBT_TEXT, array('dl_count=dl_count+1'), compact('id'));
	}

	protected static function newFromDB($dbkey, $reader = 0) {
		$db = Setup::db();
		$qa = array(
			'SELECT' => 't.*,
				s.name series, s.orig_name seriesOrigName, s.type seriesType,
				lo.code lo_code, lo.name lo_name, lo.copyright lo_copyright, lo.uri lo_uri,
				lt.code lt_code, lt.name lt_name, lt.copyright lt_copyright, lt.uri lt_uri,
				r.user isRead',
			'FROM' => self::DB_TABLE .' t',
			'LEFT JOIN' => array(
				DBT_SERIES .' s' => 't.series = s.id',
				DBT_LICENSE .' lo' => 't.license_orig = lo.id',
				DBT_LICENSE .' lt' => 't.license_trans = lt.id',
				DBT_READER_OF .' r' => "t.id = r.text AND r.user = $reader",
			),
			'WHERE' => $dbkey,
		);
		$fields = $db->fetchAssoc( $db->extselect($qa) );
		if ( empty($fields) ) {
			return null;
		}
		$fields['collection'] = $db->s2b($fields['collection']);
		$fields['lo_copyright'] = $db->s2b($fields['lo_copyright']);
		$fields['lt_copyright'] = $db->s2b($fields['lt_copyright']);

		$tables = array('author' => DBT_AUTHOR_OF, 'translator' => DBT_TRANSLATOR_OF);
		foreach ($tables as $role => $table) {
			$qa = array(
				'SELECT' => 'p.*, of.year',
				'FROM' => $table .' of',
				'LEFT JOIN' => array(DBT_PERSON .' p' => "of.$role = p.id"),
				'WHERE' => array('of.text' => $fields['id']),
				'ORDER BY' => 'of.pos ASC',
			);
			$res = $db->extselect($qa);
			$persons = array();
			$string_name = $string_year = '';
			while ( $data = $db->fetchAssoc($res) ) {
				$persons[] = $data;
				$string_name .= ', '. $data['name'];
				$string_year .= ', '. $data['year'];
			}
			$fields[$role.'s'] = $persons;
			$fields[$role.'_name'] = ltrim($string_name, ', ');
			$fields[$role.'_year'] = ltrim($string_year, ', 0');
		}
		return new Work($fields);
	}
}
