<?php
header('Content-type: text/javascript');
?>
/*
Begin functions copied from MediaWiki
License: GPL
*/
function showTocToggle() {
	if (!document.createTextNode) return;

	var linkHolder = document.getElementById('toctitle')
	if (!linkHolder) return;

	var outerSpan = document.createElement('span');
	outerSpan.className = 'toctoggle';

	var toggleLink = document.createElement('a');
	toggleLink.id = 'togglelink';
	toggleLink.href = 'javascript:toggleToc()';
	toggleLink.appendChild(document.createTextNode(tocHideText));

	outerSpan.appendChild(document.createTextNode('['));
	outerSpan.appendChild(toggleLink);
	outerSpan.appendChild(document.createTextNode(']'));

	linkHolder.appendChild(document.createTextNode(' '));
	linkHolder.appendChild(outerSpan);
}

function changeText(el, newText) {
	// Safari work around
	if (el.innerText)
		el.innerText = newText;
	else if (el.firstChild && el.firstChild.nodeValue)
		el.firstChild.nodeValue = newText;
}

function toggleToc() {
	var toc = document.getElementById('toc').getElementsByTagName('ul')[0];
	var toggleLink = document.getElementById('togglelink')

	if (toc && toggleLink && toc.style.display == 'none') {
		changeText(toggleLink, tocHideText);
		toc.style.display = 'block';
	} else {
		changeText(toggleLink, tocShowText);
		toc.style.display = 'none';
	}
}
/* End functions copied from MediaWiki */

/*
copied from A List Apart Magazine, No. 126,
“Alternative Style: Working With Alternate Style Sheets” by Paul Sowden
*/
function setActiveStyleSheet(title) {
	var i, a, main;
	for (i=0; (a = document.getElementsByTagName("link")[i]); i++) {
		if (a.getAttribute("rel").indexOf("style") != -1
				&& a.getAttribute("title")) {
			a.disabled = true;
			if (a.getAttribute("title") == title) a.disabled = false;
		}
	}
}
