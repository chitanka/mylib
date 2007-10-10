/**
Begin functions copied from MediaWiki
License: GNU GPL
*/

// add any onload functions in this hook (please don't hard-code any events in the xhtml source)
var doneOnloadHook;

if (!window.onloadFuncts) {
	var onloadFuncts = [];
}

function addOnloadHook(hookFunct) {
	// Allows add-on scripts to add onload functions
	onloadFuncts[onloadFuncts.length] = hookFunct;
}

function hookEvent(hookName, hookFunct) {
	if (window.addEventListener) {
		window.addEventListener(hookName, hookFunct, false);
	} else if (window.attachEvent) {
		window.attachEvent("on" + hookName, hookFunct);
	}
}

function runOnloadHook() {
	// don't run anything below this for non-dom browsers
	if (doneOnloadHook || !(document.getElementById && document.getElementsByTagName)) {
		return;
	}

	// set this before running any hooks, since any errors below
	// might cause the function to terminate prematurely
	doneOnloadHook = true;

	// Run any added-on functions
	for (var i = 0; i < onloadFuncts.length; i++) {
		onloadFuncts[i]();
	}
}

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


/**
Copied from A List Apart Magazine, No. 126,
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

/*
	Written by Jonathan Snook, http://www.snook.ca/jonathan
	Add-ons by Robert Nyman, http://www.robertnyman.com
	Author says "The credit comment is all it takes, no license. Go crazy with it!:-)"
	From http://www.robertnyman.com/2005/11/07/the-ultimate-getelementsbyclassname/
*/
function getElementsByClassName(oElm, strTagName, oClassNames){
	var arrElements = (strTagName == "*" && oElm.all)? oElm.all : oElm.getElementsByTagName(strTagName);
	var arrReturnElements = new Array();
	var arrRegExpClassNames = new Array();
	if(typeof oClassNames == "object"){
		for(var i=0; i<oClassNames.length; i++){
			arrRegExpClassNames.push(new RegExp("(^|\\s)" + oClassNames[i].replace(/\-/g, "\\-") + "(\\s|$)"));
		}
	}
	else{
		arrRegExpClassNames.push(new RegExp("(^|\\s)" + oClassNames.replace(/\-/g, "\\-") + "(\\s|$)"));
	}
	var oElement;
	var bMatchesAll;
	for(var j=0; j<arrElements.length; j++){
		oElement = arrElements[j];
		bMatchesAll = true;
		for(var k=0; k<arrRegExpClassNames.length; k++){
			if(!arrRegExpClassNames[k].test(oElement.className)){
				bMatchesAll = false;
				break;
			}
		}
		if(bMatchesAll){
			arrReturnElements.push(oElement);
		}
	}
	return (arrReturnElements)
}

/**
Uses a global variable postform.
*/
function initReply(cid) {
	if ( typeof(postform) == "undefined" ) {
		return;
	}
	postform.style.display = "block";
	postform.replyto.value = cid;
	if (cid > 0) {
		var replyto = document.getElementById("replyto"+cid);
		replyto.appendChild(postform);
	}
}
