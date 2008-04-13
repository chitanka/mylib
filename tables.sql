SET NAMES utf8;

DROP TABLE IF EXISTS /*$prefix*/author_of;
CREATE TABLE /*$prefix*/author_of (
  `person` mediumint(8) unsigned NOT NULL default '0',
  `text` mediumint(8) unsigned NOT NULL default '0',
  `pos` tinyint(2) unsigned NOT NULL default '0',
  `year` smallint(4) unsigned NOT NULL,
  PRIMARY KEY  (`person`,`text`),
  KEY `text` (`text`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/book;
CREATE TABLE /*$prefix*/book (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) NOT NULL,
  `orig_title` varchar(255) NOT NULL,
  `lang` varchar(2) character set latin1 collate latin1_general_ci NOT NULL default 'bg',
  `year` smallint(4) NOT NULL,
  `type` enum('book','collection','poetry') NOT NULL default 'book',
  PRIMARY KEY  (`id`),
  KEY `name` (`title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/book_part;
CREATE TABLE /*$prefix*/book_part (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `book` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Book parts';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/book_text;
CREATE TABLE /*$prefix*/book_text (
  `book` smallint(5) unsigned NOT NULL,
  `part` smallint(5) unsigned NOT NULL,
  `text` mediumint(8) unsigned NOT NULL,
  `pos` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`book`,`text`),
  KEY `text` (`text`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Relation: book contains text';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/comment;
CREATE TABLE /*$prefix*/comment (
  `id` int(11) unsigned NOT NULL auto_increment,
  `text` mediumint(8) unsigned NOT NULL COMMENT 'Text ID',
  `rname` varchar(160) NOT NULL COMMENT 'Reader (or user) name',
  `user` mediumint(8) unsigned NOT NULL COMMENT 'User ID',
  `ctext` text NOT NULL COMMENT 'Text of the comment',
  `ctexthash` varchar(32) character set latin1 NOT NULL COMMENT 'MD5 hash of the comment',
  `time` datetime NOT NULL COMMENT 'Entry ime of the comment',
  `replyto` int(11) unsigned NOT NULL COMMENT 'In reply to the comment ...',
  `show` enum('false','true') character set latin1 NOT NULL COMMENT 'Show this comment?',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `title` (`text`,`rname`,`ctexthash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Reader comments';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/edit_history;
CREATE TABLE /*$prefix*/edit_history (
  `id` int(10) unsigned NOT NULL auto_increment,
  `text` mediumint(8) unsigned NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `comment` varchar(255) NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `date` (`date`),
  KEY `text` (`text`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Edit history of texts';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/header;
CREATE TABLE /*$prefix*/header (
  `text` mediumint(8) unsigned NOT NULL default '0',
  `nr` smallint(3) unsigned NOT NULL default '1',
  `level` tinyint(1) unsigned NOT NULL default '1' COMMENT 'Header level',
  `name` varchar(255) NOT NULL,
  `fpos` int(10) unsigned NOT NULL COMMENT 'File position in bytes',
  `linecnt` smallint(6) unsigned NOT NULL COMMENT 'Lines count',
  PRIMARY KEY  (`text`,`nr`,`level`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/label;
CREATE TABLE /*$prefix*/label (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(80) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/label_log;
CREATE TABLE /*$prefix*/label_log (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `text` mediumint(8) unsigned NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `author` varchar(200) NOT NULL,
  `action` char(1) NOT NULL,
  `labels` varchar(255) NOT NULL,
  `time` timestamp NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/license;
CREATE TABLE /*$prefix*/license (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `code` varchar(20) NOT NULL,
  `name` varchar(15) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `free` enum('false','true') NOT NULL COMMENT 'Free license?',
  `copyright` enum('false','true') NOT NULL default 'true' COMMENT 'Contains any copyright?',
  `uri` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=22;

INSERT INTO /*$prefix*/license (`id`, `code`, `name`, `fullname`, `free`, `copyright`, `uri`) VALUES
(1, 'pd', 'PD', 'Обществено достояние', 'true', 'false', 'http://bg.wikipedia.org/wiki/Public_domain'),
(2, 'fc', 'COPY', 'Пълни авторски права', 'false', 'true', ''),
(3, 'gfdl-1.2', 'GFDL-1.2', 'Лиценз за свободна документация на ГНУ, версия 1.2 или следваща', 'true', 'true', 'http://www.gnu.org/copyleft/fdl.html'),
(4, 'cc-by-2.5', 'CC-BY-2.5', 'Криейтив Комънс — Позоваване, версия 2.5', 'true', 'true', 'http://creativecommons.org/licenses/by/2.5/'),
(5, 'cc-by-sa-2.5', 'CC-BY-SA-2.5', 'Криейтив Комънс — Позоваване — Споделяне на споделеното, версия 2.5', 'true', 'true', 'http://creativecommons.org/licenses/by-sa/2.5/'),
(6, 'cc-by-nc-2.5', 'CC-BY-NC-2.5', 'Криейтив Комънс — Позоваване — Некомерсиално, версия 2.5', 'false', 'true', 'http://creativecommons.org/licenses/by-nc/2.5/'),
(7, 'cc-by-nc-sa-2.5', 'CC-BY-NC-SA-2.5', 'Криейтив Комънс — Позоваване — Некомерсиално — Споделяне на споделеното, версия 2.5', 'false', 'true', 'http://creativecommons.org/licenses/by-nc-sa/2.5/'),
(8, 'cc-by-nd-2.5', 'CC-BY-ND-2.5', 'Криейтив Комънс — Позоваване — Без производни, версия 2.5', 'false', 'true', 'http://creativecommons.org/licenses/by-nd/2.5/'),
(9, 'cc-by-nc-nd-2.5', 'CC-BY-NC-ND-2.5', 'Криейтив Комънс — Позоваване — Некомерсиално — Без производни, версия 2.5', 'false', 'true', 'http://creativecommons.org/licenses/by-nc-nd/2.5/'),
(10, 'cc-by-2.0', 'CC-BY-2.0', 'Криейтив Комънс — Позоваване, версия 2.0', 'true', 'true', 'http://creativecommons.org/licenses/by/2.0/'),
(11, 'cc-by-sa-2.0', 'CC-BY-SA-2.0', 'Криейтив Комънс — Позоваване — Споделяне на споделеното, версия 2.0', 'true', 'true', 'http://creativecommons.org/licenses/by-sa/2.0/'),
(12, 'cc-by-nc-2.0', 'CC-BY-NC-2.0', 'Криейтив Комънс — Позоваване — Некомерсиално, версия 2.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nc/2.0/'),
(13, 'cc-by-nc-sa-2.0', 'CC-BY-NC-SA-2.0', 'Криейтив Комънс — Позоваване — Некомерсиално — Споделяне на споделеното, версия 2.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nc-sa/2.0/'),
(14, 'cc-by-nd-2.0', 'CC-BY-ND-2.0', 'Криейтив Комънс — Позоваване — Без производни, версия 2.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nd/2.0/'),
(15, 'cc-by-nc-nd-2.0', 'CC-BY-NC-ND-2.0', 'Криейтив Комънс — Позоваване — Некомерсиално — Без производни, версия 2.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nc-nd/2.0/'),
(16, 'cc-by-3.0', 'CC-BY-3.0', 'Криейтив Комънс — Позоваване, версия 3.0', 'true', 'true', 'http://creativecommons.org/licenses/by/3.0/'),
(17, 'cc-by-sa-3.0', 'CC-BY-SA-3.0', 'Криейтив Комънс — Позоваване — Споделяне на споделеното, версия 3.0', 'true', 'true', 'http://creativecommons.org/licenses/by-sa/3.0/'),
(18, 'cc-by-nc-3.0', 'CC-BY-NC-3.0', 'Криейтив Комънс — Позоваване — Некомерсиално, версия 3.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nc/3.0/'),
(19, 'cc-by-nc-sa-3.0', 'CC-BY-NC-SA-3.0', 'Криейтив Комънс — Позоваване — Некомерсиално — Споделяне на споделеното, версия 3.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nc-sa/3.0/'),
(20, 'cc-by-nd-3.0', 'CC-BY-ND-3.0', 'Криейтив Комънс — Позоваване — Без производни, версия 3.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nd/3.0/'),
(21, 'cc-by-nc-nd-3.0', 'CC-BY-NC-ND-3.0', 'Криейтив Комънс — Позоваване — Некомерсиално — Без производни, версия 3.0', 'false', 'true', 'http://creativecommons.org/licenses/by-nc-nd/3.0/');

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/liternews;
CREATE TABLE /*$prefix*/liternews (
  `id` int(11) unsigned NOT NULL auto_increment,
  `username` varchar(160) NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `text` text NOT NULL,
  `texthash` varchar(32) character set latin1 NOT NULL,
  `src` varchar(255) character set latin1 NOT NULL,
  `time` datetime NOT NULL,
  `show` enum('false','true') NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `title` (`title`,`texthash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Literature news';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/news;
CREATE TABLE /*$prefix*/news (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `user` mediumint(8) unsigned NOT NULL,
  `text` mediumtext NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='News about the site';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/person;
CREATE TABLE /*$prefix*/person (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `name` varchar(100) NOT NULL default '',
  `orig_name` varchar(100) NOT NULL default '',
  `real_name` varchar(100) NOT NULL default '',
  `oreal_name` varchar(100) NOT NULL,
  `last_name` varchar(50) NOT NULL default '',
  `country` varchar(2) NOT NULL default '-',
  `role` set('a','t') NOT NULL default 'a' COMMENT 'author, translator',
  `info` enum('w','f') NOT NULL COMMENT 'Extern encyclopedic information',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `last_name` (`last_name`),
  KEY `country` (`country`),
  KEY `orig_name` (`orig_name`),
  KEY `role` (`role`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/person_alt;
CREATE TABLE /*$prefix*/person_alt (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `person` mediumint(8) unsigned NOT NULL default '0' COMMENT 'Person ID',
  `name` varchar(100) NOT NULL default '',
  `last_name` varchar(50) NOT NULL default '',
  `orig_name` varchar(100) NOT NULL default '',
  `type` enum('p','r','a') NOT NULL default 'p' COMMENT '{pseudonym, real name, alternate transliteration}',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `person` (`person`,`name`),
  KEY `last_name` (`last_name`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/question;
CREATE TABLE /*$prefix*/question (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `question` varchar(255) NOT NULL,
  `answers` varchar(255) NOT NULL COMMENT 'One or more answers, separated with commas',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=6;

INSERT INTO /*$prefix*/question VALUES (1, 'Колко прави 2 по 2 (словом)?', 'четири'),
(2, 'Колко прави 2 по 4 (словом)?', 'осем'),
(3, 'Колко прави 4 по 5 (словом)?', 'двайсет,двадесет'),
(4, 'Колко прави 3 по 3 (словом)?', 'девет'),
(5, 'Колко прави 5 минус 3 (словом)?', 'две');

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/reader_of;
CREATE TABLE /*$prefix*/reader_of (
  `user` mediumint(8) unsigned NOT NULL default '0',
  `text` mediumint(8) unsigned NOT NULL default '0',
  `date` date NOT NULL,
  PRIMARY KEY (`user`,`text`),
  KEY `text` (`text`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Relation: user read a title';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/ser_author_of;
CREATE TABLE /*$prefix*/ser_author_of (
  `person` mediumint(8) unsigned NOT NULL default '0',
  `series` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`person`,`series`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Relation: is author of series';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/series;
CREATE TABLE /*$prefix*/series (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `orig_name` varchar(255) NOT NULL default '',
  `type` enum('series','collection','poetry') NOT NULL default 'series',
  PRIMARY KEY  (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/subseries;
CREATE TABLE /*$prefix*/subseries (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `orig_name` varchar(255) NOT NULL,
  `series` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `name` (`name`),
  KEY `series` (`series`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/text;
CREATE TABLE /*$prefix*/text (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `title` varchar(255) NOT NULL default '',
  `subtitle` varchar(200) NOT NULL,
  `lang` varchar(2) NOT NULL default 'bg',
  `trans_year` smallint(4) NOT NULL COMMENT 'Year of translation',
  `trans_year2` smallint(4) NOT NULL COMMENT 'Last year of a translation period',
  `orig_title` varchar(255) NOT NULL default '',
  `orig_subtitle` varchar(200) NOT NULL,
  `orig_lang` varchar(3) NOT NULL default 'en',
  `year` smallint(4) NOT NULL COMMENT 'Year of publication or creation',
  `year2` smallint(4) NOT NULL COMMENT 'Last year of creation period',
  `license_orig` smallint(5) unsigned NOT NULL COMMENT 'License of the original work',
  `license_trans` smallint(5) unsigned NOT NULL COMMENT 'License of the translated work',
  `type` varchar(12) NOT NULL default 'shortstory',
  `cover` mediumint(8) NOT NULL COMMENT 'use this cover if no cover in COVERDIR',
  `series` smallint(5) unsigned NOT NULL default '0',
  `sernr` tinyint(2) unsigned NOT NULL default '0',
  `subseries` smallint(5) unsigned NOT NULL default '0',
  `collection` enum('false','true') NOT NULL default 'false',
  `headlevel` tinyint(1) unsigned NOT NULL COMMENT 'Maximal header level',
  `size` mediumint(8) unsigned NOT NULL default '0' COMMENT 'File size',
  `zsize` mediumint(8) unsigned NOT NULL default '0' COMMENT 'Zip file size',
  `entrydate` date NOT NULL default '0000-00-00',
  `lastedit` int(10) unsigned NOT NULL COMMENT 'Last edit comment ID',
  `dl_count` bigint(20) unsigned NOT NULL default '0' COMMENT 'Number of downloads',
  `read_count` bigint(20) unsigned NOT NULL default '0' COMMENT 'Number of readings',
  `comment_count` smallint(5) unsigned NOT NULL COMMENT 'Number of comments for this text',
  `rating` float(3,1) NOT NULL COMMENT 'Average rating of the text',
  `has_anno` enum('false','true') NOT NULL default 'false' COMMENT 'Does the text has annotation?',
  PRIMARY KEY  (`id`),
  KEY `title` (`title`),
  KEY `series` (`series`),
  KEY `lastedit` (`lastedit`),
  KEY `entrydate` (`entrydate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/text_label;
CREATE TABLE /*$prefix*/text_label (
  `text` mediumint(8) unsigned NOT NULL,
  `label` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`text`,`label`),
  KEY `label` (`label`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='A text has a label';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/translator_of;
CREATE TABLE /*$prefix*/translator_of (
  `person` mediumint(8) unsigned NOT NULL default '0',
  `text` mediumint(8) unsigned NOT NULL default '0',
  `pos` tinyint(2) unsigned NOT NULL default '0',
  `year` smallint(4) unsigned NOT NULL,
  PRIMARY KEY  (`person`,`text`),
  KEY `text` (`text`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Relation: translator has translated a text';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/user;
CREATE TABLE /*$prefix*/user (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `username` varchar(100) NOT NULL default '',
  `realname` varchar(120) NOT NULL default '',
  `lastname` varchar(60) NOT NULL default '',
  `password` varchar(32) character set latin1 NOT NULL,
  `newpassword` varchar(32) character set latin1 NOT NULL,
  `email` varchar(100) character set latin1 NOT NULL default '',
  `allowemail` enum('false','true') NOT NULL default 'false' COMMENT 'Allow email from other users',
  `group` enum('nu','c0','c','a','mod') NOT NULL default 'nu',
  `news` enum('false','true') NOT NULL default 'false' COMMENT 'Receive a monthly newsletter?',
  `opts` blob NOT NULL,
  `login_tries` tinyint(3) unsigned NOT NULL,
  `registration` datetime NOT NULL COMMENT 'Registration date',
  `touched` datetime NOT NULL COMMENT 'Last user visit',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/user_text;
CREATE TABLE /*$prefix*/user_text (
  `user` mediumint(8) unsigned NOT NULL,
  `text` mediumint(8) unsigned NOT NULL,
  `size` mediumint(8) unsigned NOT NULL,
  `percent` tinyint(4) unsigned NOT NULL default '100',
  PRIMARY KEY  (`user`,`text`),
  KEY `text` (`text`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='User has scanned title';

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/work;
CREATE TABLE /*$prefix*/work (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `type` tinyint(4) NOT NULL,
  `title` varchar(100) NOT NULL,
  `author` varchar(100) NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `comment` text NOT NULL,
  `date` datetime NOT NULL,
  `status` tinyint(1) NOT NULL,
  `progress` tinyint(3) unsigned NOT NULL,
  `frozen` enum('false','true') NOT NULL default 'false',
  `tmpfiles` varchar(255) NOT NULL,
  `tfsize` smallint(5) unsigned NOT NULL COMMENT 'Size of the temporary files',
  `uplfile` varchar(255) NOT NULL COMMENT 'Uploaded file',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `title` (`title`,`author`,`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

DROP TABLE IF EXISTS /*$prefix*/work_multi;
CREATE TABLE /*$prefix*/work_multi (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `pid` smallint(5) unsigned NOT NULL,
  `user` mediumint(8) unsigned NOT NULL,
  `comment` text NOT NULL,
  `progress` tinyint(3) unsigned NOT NULL,
  `frozen` enum('false','true') NOT NULL,
  `date` datetime NOT NULL,
  `uplfile` varchar(255) NOT NULL COMMENT 'Uploaded file',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `pid` (`pid`,`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
