<?php

function cb_quotes($matches) {
	return '„'. strtr($matches[1], array('„'=>'«', '“'=>'»', '«'=>'„', '»'=>'“')) .'“';
}

function my_replace($cont) {
	$chars = array("\r" => '',
		'„' => '"', '“' => '"', '”' => '"', '«' => '"', '»' => '"', '&quot;' => '"',
		'&bdquo;' => '"', '&ldquo;' => '"', '&rdquo;' => '"', '&laquo;' => '"',
		'&raquo;' => '"', '&#132;' => '"', '&#147;' => '"', '&#148;' => '"',
		'&lt;' => '&amp;lt;', '&gt;' => '&amp;gt;', '&nbsp;' => '&amp;nbsp;',
		"'" => '’', '...' => '…',
		'</p>' => '', '</P>' => '',
		#"\n     " => "<p>", "\n" => ' ',
		'<p>' => "\n\t", '<P>' => "\n\t",
	);
	$reg_chars = array(
		'/(\s|&nbsp;)(-|–|­){1,2}(\s)/' => '$1—$3', # mdash
		'/([\s(][\d,.]*)-([\d,.]+[\s)])/' => '$1–$2', # ndash между цифри
		'/(\d)x(\d)/' => '$1×$2', # знак за умножение
		'/\n +/' => "\n\t", # абзаци
		'/(?<!\n)\n\t\* \* \*\n(?!\n)/' => "\n\n\t* * *\n\n",
	);

	$cont = preg_replace('/([\s(]\d+ *)-( *\d+[\s),.])/', '$1–$2', "\n".$cont);
	$cont = str_replace(array_keys($chars), array_values($chars), $cont);
	#$cont = html_entity_decode($cont, ENT_NOQUOTES, 'UTF-8');
	$cont = preg_replace(array_keys($reg_chars), array_values($reg_chars), $cont);

	# кавички
	$qreg = '/(?<=[([\s|\'"_\->\/])"(\S?|\S[^"]*[^\s"([])"/m';
	#$cont = preg_replace($qreg, '„$1“', $cont);
	$i = 0;
	$maxIters = 6;
	while ( strpos($cont, '"') !== false ) {
		if ( ++$i > $maxIters ) {
			log_error("ВЕРОЯТНА ГРЕШКА: Повече от $maxIters итерации при вътрешните кавички.");
			break;
		}
		$cont = preg_replace_callback($qreg, 'cb_quotes', $cont);
	}

	return ltrim($cont, "\n");
}


function log_error($s, $loud = false) {
	file_put_contents('./log/error', date('d-m-Y H:i:s'). "  $s\n", FILE_APPEND);
	if ($loud) { echo $s."\n"; }
}

/*$con = 'Той каза:
"Това е "моята голяма "прекрасна, страхотна, "жестока", кавична"" проба.
Нали? "Да, така е!" Край."
"Да, добре", отговори тя.
Ами, добре щом така "искате", оставям ви. "М", "Ф", "", "v"
';
echo my_replace($con) ."\n";*/
