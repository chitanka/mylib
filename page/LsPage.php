<?php

class LsPage extends Page {

	protected
		$maxSaveSize = 20000000,
		$dirs = array('text', 'text-info', 'text-anno', 'cover', 'book', 'book-anno', 'wiki', 'info', 'user', 'img');


	public function __construct() {
		parent::__construct();
		$this->action = 'ls';
		$this->title = 'Преглед на файлове';
		$this->dir = $this->request->value('dir', 'text', 1);
		$this->days = (float) $this->request->value('days', 7, 2);
		$this->copy = (int) $this->request->value('copy', 0);
		if ( $this->copy ) {
			set_time_limit(600); // 10 минути за копиране на файлове
		}
	}


	protected function buildContent() {
		global $contentDirs;
		$dirs = $this->dir == '*' ? $this->dirs : array($this->dir);
		$o = '';
		foreach ($dirs as $dir) {
			$dir = $contentDirs[$dir];
			$this->files = array();
			$this->starttime = time() - $this->days * 24*60*60;
			$this->processDir($dir);

			ksort($this->files, SORT_NUMERIC);
			$files = array_reverse($this->files, true);
			$o .= "<h2>$dir</h2>";
			foreach ($this->files as $mtime => $tfiles) {
				$date = date('Y-m-d H:i:s', $mtime);
				foreach ($tfiles as $file) {
					$link = $this->out->link($this->rootd.'/'.$file, $file);
					$o .= "$date  $link\n";
				}
			}
		}
		return $this->makeForm() . '<pre>'. $o .'</pre>';
	}


	protected function processDir($dir) {
		$dir = rtrim($dir, '/');
		$tfiles = scandir($dir);
		foreach ($tfiles as $tfile) {
			if ($tfile{0} == '.') { continue; }
			$fullname = $dir .'/'. $tfile;
			if (is_dir($fullname)) {
				$this->processDir($fullname);
				continue;
			}
			$mtime = filemtime($fullname);
			if ($mtime > $this->starttime) {
				$this->files[$mtime][] = $fullname;
				if ( $this->copy ) {
					$destfile = './update'.strstr($fullname, '/');
					if ( filesize($fullname) > $this->maxSaveSize ) {
						$this->splitCopyFile($fullname, $destfile);
					}# else {
						mycopy($fullname, $destfile);
					#}
				}
			}
		}
	}

	protected function splitCopyFile($srcfile, $destfile) {
		$fp = fopen($srcfile, 'r');
		$i = 1;
		$cursize = 0;
		$cont = '';
		while ( !feof($fp) ) {
			$line = fgets($fp);
			$cursize += strlen($line);
			$cont .= $line;
			if ( $cursize > $this->maxSaveSize ) {
				file_put_contents($destfile.'.'.$i, $cont);
				$cont = '';
				$cursize = 0;
				$i++;
			}
		}
		fclose($fp);
		if ( !empty($cont) ) {
			file_put_contents($destfile.'.'.$i, $cont);
		}
	}


	protected function makeForm() {
		return <<<EOS

<form action="{FACTION}" method="get">
<div>
	{HIDDEN_ACTION}
	Файловете, променени през последните
	<input type="" id="days" name="days" size="2" value="$this->days" />
	<label for="days">дни</label>
</div>
</form>

EOS;
	}
}
