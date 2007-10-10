<?php
class GenSitemapPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'genSitemap';
		$this->title = 'Генериране на сайтова карта';
		$this->outfile = './sitemap';
	}


	protected function buildContent() {
		$o = '';
		$o .= $this->getUrls(DBT_PERSON, 'name', array('author'), array('(role&1)'));
		$o .= $this->getUrls(DBT_PERSON, 'last_name', array('author'), array('(role&1)'));
		$o .= $this->getUrls(DBT_PERSON, 'name', array('translator'), array('(role&2)'));
		$o .= $this->getUrls(DBT_PERSON, 'last_name', array('translator'), array('(role&2)'));
		$o .= $this->getUrls(DBT_SERIES, 'name');
		$o .= $this->getUrls(DBT_LABEL, 'name');
		$o .= $this->getUrls(DBT_TEXT, 'title', array('title', 'text'));
		$o .= $this->getUrls(DBT_TEXT, 'id');
		file_put_contents($this->outfile, $o);
		return "<p>Сайтовата карта беше създадена ($this->outfile).</p>";
	}


	protected function getUrls($table, $field, $keys = array(), $dbkey = array()) {
		$slashRepl = array('%2F' => '/');
		$res = $this->db->select($table, $dbkey, $field);
		$o = '';
		settype($keys, 'array');
		if ( empty($keys) ) {
			$keys[] = $table;
		}
		while ($row = $this->db->fetchAssoc($res)) {
			if ( empty($row[$field]) ) {
				continue;
			}
			$enc = urlencode($row[$field]);
			$encL = urlencode(cyr2lat($row[$field]));
			if ( strpos($row[$field], '/') !== false ) {
				$enc = strtr($enc, $slashRepl);
				$encL = strtr($encL, $slashRepl);
			}
			foreach ($keys as $key) {
				$o .= $key .'/'. $enc ."\n";
				if ($enc != $encL) $o .= $key .'/!'. $encL ."\n";
			}
		}
		return $o;
	}

}
