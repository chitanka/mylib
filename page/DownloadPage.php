<?php

class DownloadPage extends Page {

	public function __construct() {
		parent::__construct();
		$this->action = 'download';
		$this->title = 'Сваляне на текст';
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
			$this->user->markTextAsDl($textId);
			if ( CacheManager::dlCacheExists($textId) ) {
				$fEntry = CacheManager::getDlCache($textId);
				$fEntry = unserialize($fEntry);
				$this->zf->addFileEntry($fEntry);
				$this->filename = $this->rmFEntrySuffix($fEntry['name']);
			} else {
				$mainFileData = $this->makeMainFileData($textId);
				if (!$mainFileData) {
					$fileCount--;
					continue;
				}
				list($this->filename, $this->fPrefix, $this->fSuffix) = $mainFileData;
				$this->addTextFileEntry($textId);
			}
			if ($setZipFileName) { $this->zipFileName = $this->filename; }
			$this->addBinaryFileEntries($textId, $this->filename);
		}
		if ( $fileCount < 1 ) {
			$this->addMessage('Не е посочен валиден номер на текст за сваляне!', true);
			return '';
		}
		if ( !$setZipFileName && empty($this->zipFileName) ) {
			$this->zipFileName = "Архив от $this->sitename - $fileCount файла_".time();
		}
		$this->zipFileName = $this->cleanFileName(cyr2lat($this->zipFileName));
		$fullZipFileName = $this->zipFileName .'.zip';
		CacheManager::setDlFile($fullZipFileName, $this->zf->file());
		$dlFile = CacheManager::getDlFile($fullZipFileName);
		$this->outputLength = filesize($dlFile);
		header('Location: '. $this->rootd .'/'. $dlFile);
		CacheManager::deleteOldDlFiles();
		$this->outputDone = true;
	}


	protected function addTextFileEntry($textId) {
		$fEntry = $this->zf->newFileEntry($this->fPrefix .
			$this->makeContentData($textId) ."\n\n\tКРАЙ".
			$this->fSuffix, $this->filename .'.txt');
		CacheManager::setDlCache($textId, serialize($fEntry));
		$this->zf->addFileEntry($fEntry);
	}


	protected function addBinaryFileEntries($textId, $filename) {
		// add covers
		if ( $this->user->option('dlcover') ) {
			foreach (Work::getCovers($textId) as $file) {
				$ename = Work::renameCover(basename($file), $filename);
				$fEntry = $this->zf->newFileEntry(file_get_contents($file), $ename);
				$this->zf->addFileEntry($fEntry);
			}
		}
		// add images
		$dir = getContentFilePath('img', $textId);
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
		$fname = getContentFilePath('text', $textId);
		if ( file_exists($fname) ) {
			return file_get_contents($fname);
		}
		return '';
	}


	protected function makeMainFileData($textId) {
		$work = Work::newFromId($textId);
		$prefix = "\xEF\xBB\xBF". // Byte order mark for some windows software
			"\t[Kodirane UTF-8]\n\n|\t$work->author_name\n".
			$work->getTitleAsSfb() ."\n\n\n";
		$anno = $work->getAnnotation();
		if ( !empty($anno) ) { $prefix .= "A>\n$anno\nA$\n\n\n"; }

		$filename = (empty($work->author_name) ? '' : cyr2lat($work->author_name) .' - ').
			(empty($work->sernr)?'':"$work->sernr. ") . cyr2lat($work->title);
		$filename = substr($filename, 0, 200);
		$filename = $this->cleanFileName($filename);
		if ( isset( $this->fnameCount[$filename] ) ) {
			$this->fnameCount[$filename]++;
			$filename .= $this->fnameCount[$filename];
		} else {
			$this->fnameCount[$filename] = 1;
		}
		$suffix = "\nI>".$work->getCopyright() ."\n\n".
			$work->getOrigTitleAsSfb() . "\n\n" .
			$work->getExtraInfo() .
			"\n\n\tСвалено от „{$this->sitename}“ [$this->purl/text/$textId]\nI$\n";
		$suffix = preg_replace('/\n\n+/', "\n\n", $suffix);
		return array($filename, $prefix, $suffix);
	}


	protected function cleanFileName($fname) {
		$repl = array('"'=>'', '\''=>'', ':'=>' -', '\\'=>'', '/'=>'', '?'=>'');
		$fname = strtr($fname, $repl);
		$fname = preg_replace('/[^\w\d ,._-]/', '', $fname);
		return $fname;
	}


	protected function rmFEntrySuffix($fEntryName) {
		return strtr($fEntryName, array('.txt'=>''));
	}
}
