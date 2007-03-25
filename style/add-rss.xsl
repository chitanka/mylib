<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:output method="html"/>
<xsl:template match="/">
<html>
<head>
<title><xsl:value-of select="/rss/channel/title"/></title>
<style>@import "/mylib/css/main";</style>
<style>
h1 { margin-top: 0.5em; }
dd {margin:0.5ex 10px 0.5ex 50px;text-align:left}
dt {margin:0 10px 0 10px;text-align:left;}
.extra {font-size:0.8em;}
</style>
</head>
<body>
<xsl:apply-templates/>
</body>
<script><![CDATA[
function sr(s,f,r)
{
  var ret = s;
  var start = ret.indexOf(f);
  while (start>=0)
  {
    ret = ret.substring(0,start) + r + ret.substring(start+f.length,ret.length);
    start = ret.indexOf(f,start+r.length);
  }
  return ret;
}
function moz()
{
  var i, o, d, t;
  for( i = 1; i; i++)
  {
    d = "d_" + i;
    o = document.getElementById(d);
    if( o == null ) break;
    if( null != o.innerText ) break; // IE ok
    t = unescape( o.innerHTML );
    t = sr( t, "&gt;", ">" );
    t = sr( t, "&lt;", "<" );
    t = sr( t, "&amp;", "&" );
    o.innerHTML = t;
  }
}
moz();
]]></script>
</html>
</xsl:template>
<xsl:template match="/rss/channel">
<h1>
<a><xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>
<xsl:value-of select="title"/></a></h1>
<dl>
<xsl:for-each select="item">
    <dt>
        <a><xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>
        <xsl:value-of select="title" disable-output-escaping = "yes"/></a>
		<span>
        <xsl:attribute name="class">extra</xsl:attribute>
        (<xsl:value-of select="pubDate"/>)</span>
    </dt>
    <dd><xsl:attribute name="id">d_<xsl:value-of select="position()"/></xsl:attribute>
        <xsl:value-of select="description" disable-output-escaping = "yes"/>
      </dd>
</xsl:for-each></dl>
 </xsl:template>
</xsl:stylesheet>
