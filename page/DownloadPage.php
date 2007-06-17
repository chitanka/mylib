<?php

class DownloadPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'download';
		$this->title = 'Сваляне на текст';
		$this->binaryDir = './content/img/';
		$this->textIds = (array) $this->request->value('textId', null, 1);
		require_once 'include/myzipfile.php';
		$this->zf = new myzipfile();
		$this->zipFileName = $this->request->value('filename', '');
		// track here how many times a filename occurs
		$this->fnameCount = array();
	}


	// Функцията изглежда ужасно. Трябва да се оправи, но това е с нисък приоритет
	protected function buildContent() {
		$setZipFileName = ($fileCount = count($this->textIds)) == 1
			&& empty($this->zipFileName) ? true : false;
		foreach ($this->textIds as $textId) {
			if ( !$textId || !is_numeric($textId) ) {
				$fileCount--; continue; // invalid text ID
			}
			$cacheFile = CacheManager::SFBZIP_FILE .$textId;
			if ( CacheManager::cacheExists($cacheFile) ) {
				$fEntry = CacheManager::getCache($cacheFile, false);
				$fEntry = unserialize($fEntry);
				$this->zf->addFileEntry($fEntry);
				if ($setZipFileName) {
					$this->zipFileName = $fEntry['name'];
				}
			} else {
				$mainFileData = $this->makeMainFileData($textId);
				if (!$mainFileData) { $fileCount--; continue; }
				list($this->filename, $this->fPrefix, $this->fSuffix) = $mainFileData;
				$this->filename .= '.txt';
				if ($setZipFileName) { $this->zipFileName = $this->filename; }
				$this->addTextFileEntry($textId, $cacheFile);
			}
		}
		if ( $fileCount < 1 ) {
			$this->addMessage('Не е посочен валиден номер на текст за сваляне!', true);
			return '';
		}
		if ( !$setZipFileName && empty($this->zipFileName) ) {
			$this->zipFileName = "Архив от Моята библиотека - $fileCount файла_".time();
		}
		$this->addBinaryFileEntries($textId);
		$this->zipFileName = cyr2lat($this->cleanFileName($this->zipFileName));
		$fullZipFileName = $this->zipFileName .'.zip';
		CacheManager::setDlFile($fullZipFileName, $this->zf->file());
		header('Location: '. $this->rootd .'/'. CacheManager::getDlFile($fullZipFileName));
		CacheManager::deleteOldDlFiles();
		$this->outputDone = true;
	}


	protected function addTextFileEntry($textId, $cacheFile) {
		$fEntry = $this->zf->newFileEntry($this->fPrefix .
			$this->makeContentData($textId) ."\n\n\tКРАЙ".
			$this->fSuffix, $this->filename);
		CacheManager::setCache($cacheFile, serialize($fEntry), false);
		$this->zf->addFileEntry($fEntry);
	}


	protected function addBinaryFileEntries($textId) {
		$dir = $this->binaryDir . $textId;
		if ( !is_dir($dir) ) { return; }
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				$fullname = "$dir/$file";
				if ( $file{0} == '.' || $file{0} == '_' ||
					isArchive($file) || is_dir($fullname) ) { continue; }
				$fEntry = $this->zf->newFileEntry(file_get_contents($fullname), $file);
				$this->zf->addFileEntry($fEntry);
			}
			closedir($dh);
		}
	}


	protected function makeContentData($textId) {
		$fname = $GLOBALS['contentDirs']['text'] . $textId;
		if ( file_exists($fname) ) {
			return file_get_contents($fname);
		}
		return '';
	}


	protected function makeMainFileData($textId) {
		// text data
		$sel = array('title textTitle', 'subtitle', 'orig_title', 'orig_subtitle',
			'sernr', 'copy');
		$res = $this->db->select('text', array('id' => $textId), $sel);
		$data = $this->db->fetchAssoc($res);
		if ( empty($data) ) { return false; }
		extract($data);

		// authors
		$query = "SELECT a.name FROM /*p*/author_of aof
			LEFT JOIN /*p*/person a ON aof.author = a.id
			WHERE aof.text = $textId ORDER BY aof.pos ASC";
		$result = $this->db->query($query);
		$authors = array();
		$copyrights = '';
		while ( $data = $this->db->fetchAssoc($result) ) {
			extract($data);
			if ( empty($name) ) continue;
			$authors[] = $name;
			if ($copy) $copyrights .= "\n\t© ". $name;
		}
		$authors = implode(', ', $authors);

		// translators
		$query = "SELECT t.name FROM /*p*/translator_of tof
			LEFT JOIN /*p*/person t ON tof.translator = t.id
			WHERE tof.text = $textId ORDER BY tof.pos ASC";
		$result = $this->db->query($query);
		while ( $data = $this->db->fetchAssoc($result) ) {
			extract($data);
			if ( empty($name) ) continue;
			$copyrights .= "\n\t© $name, превод";
		}

		if ( !empty($subtitle) ) {
			$textTitle .= $subtitle{0} == '(' ? ' '.$subtitle : " ($subtitle)";
		}
		$prefix = "\xEF\xBB\xBF". // Byte order mark for some windows software
			"\t[Kodirane UTF-8]\n\n|\t$authors\n|\t$textTitle\n\n\n";
		$anno = $this->makeAnnotation($textId);
		if ( !empty($anno) ) { $prefix .= "A>\n$anno\nA$\n\n\n"; }

		$filename = (empty($authors) ? '' : cyr2lat($authors) .' - ').
			(empty($sernr)?'':"$sernr. ") . cyr2lat($textTitle);
		$filename = substr($filename, 0, 200);
		$filename = $this->cleanFileName($filename);
		if ( isset( $this->fnameCount[$filename] ) ) {
			$this->fnameCount[$filename]++;
			$filename .= $this->fnameCount[$filename];
		} else {
			$this->fnameCount[$filename] = 1;
		}
		$suffix = "\nI>".$copyrights . $this->makeExtraInfo($textId) ."\n\n".
			"\tСвалено от „{$this->sitename}“ [$this->purl/text/$textId]\nI$\n";
		return array($filename, $prefix, $suffix);
	}


	protected function makeAnnotation($textId) {
		$file = $GLOBALS['contentDirs']['text-anno'] . $textId;
		if ( !file_exists($file) ) { return ''; }
		return file_get_contents($file);
	}


	protected function makeExtraInfo($textId) {
		$file = $GLOBALS['contentDirs']['text-info'] . $textId;
		if ( !file_exists($file) ) { return ''; }
		$con = file_get_contents($file);
		if ( empty($con) ) { return ''; }
		return "\n\n". rtrim($con);
	}


	protected function cleanFileName($fname) {
		$repl = array('"'=>'', '\''=>'', ':'=>' -', '\\'=>'', '/'=>'');
		return strtr($fname, $repl);
	}
}
?>
