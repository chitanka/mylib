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


	public static function newFromId($id, $reader = 0) {
		return self::newFromDB( array('t.id'=>$id), $reader );
	}

	public static function newFromTitle($title, $reader = 0) {
		return self::newFromDB( array('t.title'=>$title), $reader );
	}

	protected static function newFromDB($dbkey, $reader) {
		$db = Setup::db();
		$q = "SELECT t.*, s.name series, r.user isRead
			FROM /*p*/text t
			LEFT JOIN /*p*/series s ON t.series = s.id
			LEFT JOIN /*p*/reader_of r ON (t.id = r.text AND r.user = $reader)";
		$q .= $db->makeWhereClause($dbkey);
		$fields = $db->fetchAssoc( $db->query($q) );
		if ( empty($fields) ) return null;
		$fields['collection'] = $db->s2b($fields['collection']);

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
