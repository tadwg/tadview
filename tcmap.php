<?php
$version = "tcmap.php 090704";
$url = "tcmap.php";

class	systeminfo {
	var	$welcomemessage = "";
	var	$fontpath = "font/";
	var	$pdffontname = "HeiseiMin-W3";
	var	$pdflicense = "";
}
$systeminfo =& new systeminfo();
include("env.php");

$html_header = <<<EOO
<HTMl><HEAD><TITLE>{$version}</TITLE></HEAD><BODY>
<H1>{$version}</H1>

{$systeminfo->welcomemessage}

<HR>
<P><A href="{$url}?mode=tcmap">edit tcmap table</A>
/ <A href="{$url}?mode=font">edit font table</A>
/ <A href="{$url}?mode=create">create tables</A>
/ <A href="{$url}?mode=sql">direct query</A>
</P>

EOO;


function	unquote_post($val) {
	if (get_magic_quotes_gpc())
		$val = stripslashes($val);
	return $val;
}


class	database	{
	var	$sid = null;
	function	database($path) {
		$this->sid = sqlite_open($path.".sq2", 0666, $str) or die("sqlite_open failed : ".htmlspecialchars($str));
	}
	function	gethtmlerror() {
		return htmlspecialchars(sqlite_error_string(sqlite_last_error($this->sid)));
	}
	function	close() {
		if ($this->sid !== null) {
			sqlite_close($this->sid);
			$this->sid = null;
		}
	}
	function	query($sql, $array) {
		$sql = ereg_replace("[\t\r\n ]+", " ", $sql);
		$remain = $sql;
		$sql = "";
		while (($pos = strpos($remain, "?")) !== FALSE) {
			$sql .= substr($remain, 0, $pos);	# before '?'
			$remain = substr($remain, $pos + 1);	# after '?'
			if (($val = array_shift($array)) === null)
				$sql .= "null";
			else if ($val === "")
				$sql .= "''";
			else if (is_string($val))
				$sql .= "'".sqlite_escape_string($val)."'";
			else
				$sql .= ($val + 0);
		}
		$sql .= $remain;
		return $this->directquery($sql);
	}
	function	directquery($sql) {
		return sqlite_array_query($this->sid, $sql, SQLITE_ASSOC);
	}
	function	getupdates() {
		return sqlite_changes($this->sid) + 0;
	}
	function	getlastid() {
		return sqlite_last_insert_rowid($this->sid) + 0;
	}
	function	lock($mode) {
		if ($mode < 0)
			return $this->directquery("rollback;");
		if ($mode > 0)
			return $this->directquery("begin;");
		return $this->directquery("commit;");
	}
}


class	table {
	var	$db;
	var	$tablename = null;
	function	table(&$db) {
		$this->db =& $db;
	}
	function	create($sql = "") {
		$sql = <<<EOO
create table {$this->tablename} (
	{$sql}
	id	integer primary key
);
EOO;
		return $this->db->directquery($sql);
	}
	function	gethtml($key) {
		switch ($key) {
			case	"tablename":
				return htmlspecialchars($this->tablename);
			case	"errorstring":
				return ($this->gethtml("tablename"))." : ".($this->db->gethtmlerror());
		}
		return "unknown key.";
	}
}


class	tcmaptable	extends	table {
	var	$tablename = "tcmap";
	function	create($sql = "") {
		$sql .= <<<EOO
	tc	integer not null,
	encode	varchar(20), 
	hex	varchar(16), 
EOO;
		if (($ret = parent::create($sql)) === FALSE)
			return $ret;
		
		$sql = <<<EOO
create index {$this->tablename}_index1 on {$this->tablename} (tc);
EOO;
		return $this->db->directquery($sql);
	}
	function	add($tc = 0x212121, $encode = "JISX0208", $string = "") {
		$array = array($tc + 0, $encode."", bin2hex($string.""));
		return $this->db->query("insert into {$this->tablename} (tc, encode, hex) values(?, ?, ?);", $array);
	}
}


class	fonttable	extends	table {
	var	$tablename = "font";
	function	create($sql = "") {
		$sql .= <<<EOO
	encode	varchar(20) not null, 
	fonttype	integer, 
	fontname	varchar(40), 
	fontcmap	varchar(40), 
	attr_prop	integer default 0, 
	attr_dir	integer default 0, 
	attr_line	integer default 0, 
	attr_italic	integer default 0, 
	attr_weight	integer default 0, 
	attr_width	integer default 0, 
	class_15	integer default 0, 
	class_14	integer default 1, 
	class_13	integer default 1, 
	class_12	integer default 0, 
	class_11	integer default 0, 
	class_10	integer default 0, 
	class_9	integer default 0, 
	class_8	integer default 0, 
	class_7	integer default 1, 
	class_6	integer default 1, 
	class_5	integer default 0, 
	class_4	integer default 0, 
	class_3	integer default 0, 
	class_2	integer default 1, 
	class_1	integer default 1, 
	class_0	integer default 0, 
EOO;
		if (($ret = parent::create($sql)) === FALSE)
			return $ret;
		
		$sql = <<<EOO
create index {$this->tablename}_index1 on {$this->tablename} (encode);
EOO;
		return $this->db->directquery($sql);
	}
	function	attr2val($val) {
		$val &= 7;
		if ($val >= 4)
			return $val - 3;
		return -$val;
	}
	function	val2attr($val) {
		$val += 0;
		if ($val <= 0)
			return -$val;
		return $val + 3;
	}
	function	add($encode = "JISX0208", $fonttype = -1, $fontname = "", $fontcmap = "unicode", $attr = 0, $class = 0x60c6) {
		$attr += 0;
		$class += 0;
		$array = array(
			$encode."", $fonttype + 0, $fontname."", $fontcmap."", 
			($attr & 0x8000)? 1 : 0, ($attr & 0x4000)? 1 : 0, ($attr >> 9) & 7, 
			-$this->attr2val($attr >> 6), $this->attr2val($attr >> 3), $this->attr2val($attr), 
			($class & 0x8000)? 1 : 0, ($class & 0x4000)? 1 : 0, ($class & 0x2000)? 1 : 0, ($class & 0x1000)? 1 : 0, 
			($class & 0x800)? 1 : 0, ($class & 0x400)? 1 : 0, ($class & 0x200)? 1 : 0, ($class & 0x100)? 1 : 0, 
			($class & 0x80)? 1 : 0, ($class & 0x40)? 1 : 0, ($class & 0x20)? 1 : 0, ($class & 0x10)? 1 : 0, 
			($class & 8)? 1 : 0, ($class & 4)? 1 : 0, ($class & 2)? 1 : 0, ($class & 1)? 1 : 0
		);
		return $this->db->query("insert into {$this->tablename} (
			encode, fonttype, fontname, fontcmap,
			attr_prop, attr_dir, attr_line, attr_italic, attr_weight, attr_width, 
			class_15, class_14, class_13, class_12, class_11, class_10, class_9, class_8,
			class_7, class_6, class_5, class_4, class_3, class_2, class_1, class_0
		) values(
			?, ?, ?, ?,
			?, ?, ?, ?, ?, ?,
			?, ?, ?, ?, ?, ?, ?, ?,
			?, ?, ?, ?, ?, ?, ?, ?
		);", $array);
	}
}


$tcmapdb =& new database($systeminfo->fontpath."tcmap");
$tcmaptable =& new tcmaptable($tcmapdb);
$fonttable =& new fonttable($tcmapdb);


switch (@$_GET["mode"]) {
	case	"tcmap":
		$count = 0;
		switch (@$_GET["action"]) {
			case	"delete":
				if (($encode = @$_GET["encode"]) === null)
					break;
				if (get_magic_quotes_gpc())
					$encode = stripslashes($encode);
				$tcmaptable->db->query("delete from {$tcmaptable->tablename} where encode = ?;", array(@$_GET["encode"].""));
				$count += $tcmaptable->db->getupdates();
				break;
		}
		switch (@$_POST["target"]) {
			case	"x0208":
				$tcmaptable->db->lock(1);
				for ($row=0x21; $row<=0x7e; $row++)
					for ($col=0x21; $col<=0x7e; $col++) {
						$string = chr($row | 0x80).chr($col | 0x80);
						$string = mb_convert_encoding($string, "UTF-8", "eucJP-win");
						if ((($row != 0x21)||($col != 0x29))&&($string === chr(0x3f)))
							continue;
						$tcmaptable->add(($row << 8) | $col | 0x210000, "JISX0208", $string);
						$count += $tcmaptable->db->getupdates();
					}
				$tcmaptable->db->lock(0);
				break;
			case	"x0208p":
				$tcmaptable->db->lock(1);
				for ($row=0x21; $row<=0x7e; $row++)
					for ($col=0x21; $col<=0x7e; $col++) {
						$string = chr($row | 0x80).chr($col | 0x80);
						$string = mb_convert_kana($string, "as", "EUC-JP");
						$string = mb_convert_encoding($string, "UTF-8", "eucJP-win");
						if ((($row != 0x21)||($col != 0x29))&&($string === chr(0x3f)))
							continue;
						$tcmaptable->add(($row << 8) | $col | 0x210000, "JISX0208P", $string);
						$count += $tcmaptable->db->getupdates();
					}
				$tcmaptable->db->lock(0);
				break;
		}
		if (is_uploaded_file($fn = @$_FILES["cdbook"]["tmp_name"]) === TRUE) {
			ini_set("max_execution_time", "300");	# 5 minutes
			$fp = fopen($fn, "r") or die("fopen failed.");
			$tcmaptable->db->lock(1);
			while (($line = fgets($fp, 1024)) !== FALSE) {
# <td><font size="1">000021</font></td><td rowspan="3"><font size="7" face="GT2000-01">–œ</font></td>
				if (!ereg('^<td><font[^>]*>0*([1-9][0-9]*)</font></td><td[^>]*><font[^>]*face="([^"]+)">(.+)</font></td>', $line, $array))
					continue;
				$tc = floor(($array[1] - 1) / 48400) * 0x10000 + 0x220000;
				if (($val = ($array[1] - 1) % 48400) < 94 * 94)
					$tc |= ((floor($val / 94) << 8) | ($val % 94)) + 0x2121;
				else if (($val -= 94 * 94) < 94 * 126)
					$tc |= ((floor($val / 94) << 8) | ($val % 94)) + 0x8021;
				else if (($val -= 94 * 126) < 126 * 94)
					$tc |= ((floor($val / 126) << 8) | ($val %126)) + 0x2180;
				else {
					$val -= 126 * 94;
					$tc |= ((floor($val / 126) << 8) | ($val %126)) + 0x8080;
				}
				$encode = str_replace("-", "", $array[2]);
				$tcmaptable->add($tc, $encode, mb_convert_encoding($array[3], "UTF-8", "SJIS"));
				$count += $tcmaptable->db->getupdates();
			}
			$tcmaptable->db->lock(0);
			fclose($fp);
		}
		if (is_uploaded_file($fn = @$_FILES["rtf"]["tmp_name"]) === TRUE) {
			ini_set("max_execution_time", "300");	# 5 minutes
			$fp = fopen($fn, "r") or die("fopen failed.");
			
			$fontlist = array();
			$tcmaptable->db->lock(1);
			while (($line = fgets($fp, 1024)) !== FALSE) {
				$line = trim($line);
# \f0\fs20 FE222121\tab GT1\tab\fs48\f1\u19968?\tab\f14\u19968?\tab\f27\u19968?\par
				if (ereg('^\\\\f[0-9]+\\\\fs[0-9]+ +[0-9A-Fa-f]+\\\\tab +GT([0-9]+)\\\\tab\\\\fs[0-9]+\\\\f([0-9]+)\\\\u(-?[0-9]+)\?', $line, $array)) {
					$tc = floor(($array[1] - 1) / 48400) * 0x10000 + 0x220000;
					if (($val = ($array[1] - 1) % 48400) < 94 * 94)
						$tc |= ((floor($val / 94) << 8) | ($val % 94)) + 0x2121;
					else if (($val -= 94 * 94) < 94 * 126)
						$tc |= ((floor($val / 94) << 8) | ($val % 94)) + 0x8021;
					else if (($val -= 94 * 126) < 126 * 94)
						$tc |= ((floor($val / 126) << 8) | ($val %126)) + 0x2180;
					else {
						$val -= 126 * 94;
						$tc |= ((floor($val / 126) << 8) | ($val %126)) + 0x8080;
					}
					
					$c = $array[3] + 0;
					$s = mb_convert_encoding(chr(($c >> 8) & 0xff).chr($c & 0xff), "UTF-8", "UCS-2");
					
					$tcmaptable->add($tc, $fontlist[$array[2]], $s);
					$count += $tcmaptable->db->getupdates();
					continue;
				}
# {\f1 TMincho-GT01;}
				if (ereg('^{\\\\f([0-9]+) +[-_.0-9A-Za-z]+-GT([0-9]+);', $line, $array)) {
					$fontlist[$array[1]] = "GT2000".$array[2];
					continue;
				}
# print htmlspecialchars($line)."<BR>\n";
			}
			$tcmaptable->db->lock(0);
			fclose($fp);
		}
		if (is_uploaded_file($fn = @$_FILES["togt"]["tmp_name"]) === TRUE) {
			ini_set("max_execution_time", "300");	# 5 minutes
			$fp = fopen($fn, "r") or die("fopen failed.");
			$tcmaptable->db->lock(1);
			while (($line = fgets($fp, 1024)) !== FALSE) {
#‘åŠ¿˜a	Plane	TCode	RefGT
#00001	8	2121	1
				if (!ereg("^([^\t]*)\t([0-9]+)\t([0-9a-fA-F]+)\t([0-9]+)[^0-9]*$", $line, $array))
					continue;
				$tc = floor(($array[4] - 1) / 48400) * 0x10000 + 0x220000;
				if (($val = ($array[4] - 1) % 48400) < 94 * 94)
					$tc |= ((floor($val / 94) << 8) | ($val % 94)) + 0x2121;
				else if (($val -= 94 * 94) < 94 * 126)
					$tc |= ((floor($val / 94) << 8) | ($val % 94)) + 0x8021;
				else if (($val -= 94 * 126) < 126 * 94)
					$tc |= ((floor($val / 126) << 8) | ($val %126)) + 0x2180;
				else {
					$val -= 126 * 94;
					$tc |= ((floor($val / 126) << 8) | ($val %126)) + 0x8080;
				}
				$array2 = array(0x200000 + (($array[2]) << 16) + ("0x".$array[3]), $tc);
				$tcmaptable->db->query("insert into {$tcmaptable->tablename} (tc, encode, hex) select ?, encode, hex from {$tcmaptable->tablename} where tc = ?;", $array2);
				$count += $tcmaptable->db->getupdates();
			}
			$tcmaptable->db->lock(0);
			fclose($fp);
		}
		if (is_uploaded_file($fn = @$_FILES["tab"]["tmp_name"]) === TRUE) {
			ini_set("max_execution_time", "300");	# 5 minutes
			$fp = fopen($fn, "r") or die("fopen failed.");
			$tcmaptable->db->lock(1);
			while (($line = fgets($fp, 1024)) !== FALSE) {
#TC	encode	UTF-8
#299821	ATH	 
#299830	ATH	0
				if (!ereg("^([0-9a-fA-F]+)\t([^\t]+)\t([^\r\n\t]*)[\r\n\t]*$", $line, $array))
					continue;
				$tcmaptable->add(("0x".$array[1]) + 0, $array[2], $array[3]);
				$count += $tcmaptable->db->getupdates();
			}
			$tcmaptable->db->lock(0);
			fclose($fp);
		}
		if ($count > 0)
			$count = "<P>update {$count} records.</P>";
		else
			$count = "";
		
		$array = $tcmaptable->db->query("select encode, max(tc) as max, min(tc) as min, count(*) as count from {$tcmaptable->tablename} group by encode order by encode;", array());
		$tablehtml = "";
		foreach ($array as $record) {
			$encodeurl = urlencode($record["encode"]);
			$encodehtml = htmlspecialchars($record["encode"]);
			$range = sprintf("%06x - %06x", $record["min"] + 0, $record["max"] + 0);
			$tablehtml .= <<<EOO
<TR><TD><A href="{$url}?mode=tcmap&action=delete&encode={$encodeurl}">delete</A>
	<TD>{$encodehtml}
		<TD>{$range}
			<TD>{$record["count"]}
EOO;
		}
		print <<<EOO
{$html_header}
<H2>edit tcmap table</H2>
{$count}
<TABLE border>
<TR><TH>
	<TH>encode
		<TH>TC range
			<TH>count
{$tablehtml}
</TABLE>

<H3>regist JIS</H3>

<FORM method=POST>
<P>JIS-X0208 :
<SELECT name=target size=0>
	<OPTION value=x0208>JIS-X0208</OPTION>
	<OPTION value=x0208p>0201+0208</OPTION>
</SELECT>
<INPUT type=submit></P>
</FORM>

<H3>regist GT</H3>

<FORM enctype="multipart/form-data" method=POST>
<P>CDbook*.html(SJIS) : <INPUT type=file name=cdbook><INPUT type=submit></P>
</FORM>

<FORM enctype="multipart/form-data" method=POST>
<P>TFontGTList.rtf : <INPUT type=file name=rtf><INPUT type=submit></P>
</FORM>

<H3>regist to-GT map</H3>

<FORM enctype="multipart/form-data" method=POST>
<TABLE border>
<TR><TH colspan=4>TAB separated
				<TD rowspan=3><INPUT type=file name=togt><INPUT type=submit>
<TR><TH>dummy
	<TH>plane
		<TH>TC
			<TH>GT
<TR><TD>00000
	<TD>1
		<TD>2121
			<TD>1
</TABLE>
</FORM>

<H3>regist TAB separated map</H3>

<FORM enctype="multipart/form-data" method=POST>
<TABLE border>
<TR><TH colspan=3>TAB separated
			<TD rowspan=3><INPUT type=file name=tab><INPUT type=submit>
<TR><TH>TC
	<TH>encode
		<TH>UTF-8
<TR><TD>299830
	<TD>ATH
		<TD>0
</TABLE>
</FORM>

EOO;
		break;
	case	"font":
		switch (@$_GET["action"]) {
			case	"download":
				$array = $fonttable->db->query("select * from {$fonttable->tablename};", array());
				header("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename=font_csv.bin");
				print <<<EOO
"encode","fonttype","fontname","fontcmap","attr","class"

EOO;
				foreach ($array as $record) {
					$type = $record["fonttype"] + 0;
					$attr = ($record["attr_prop"] + 0)? 0x8000 : 0;
					$attr |= ($record["attr_dir"] + 0)? 0x4000 : 0;
					$attr |= ($record["attr_line"] & 7) << 9;
					$attr |= $fonttable->val2attr(-$record["attr_italic"]) << 6;
					$attr |= $fonttable->val2attr($record["attr_weight"]) << 3;
					$attr |= $fonttable->val2attr($record["attr_width"]);
					$class = 0;
					for ($i=0; $i<16; $i++)
						$class |= $record["class_".$i] << $i;
					$attr = sprintf("0x%04x", $attr);
					$class = sprintf("0x%04x", $class);
					print <<<EOO
"{$record["encode"]}",{$type},"{$record["fontname"]}","{$record["fontcmap"]}",{$attr},{$class}

EOO;
				}
				die();
			case	"delete":
				$fonttable->db->query("delete from {$fonttable->tablename} where id = ?;", array(@$_GET["id"] + 0));
				break;
		}
		$count = 0;
		if (is_uploaded_file($fn = @$_FILES["fontcsv"]["tmp_name"]) === TRUE) {
			$fp = fopen($fn, "r") or die("fopen failed.");
			$fonttable->db->lock(1);
			while (($record = fgetcsv($fp, 1024)) !== FALSE) {
				if (!is_numeric($record[1]))
					continue;
				$fonttable->add($record[0], $record[1], $record[2], $record[3], $record[4], $record[5]);
				$count += $fonttable->db->getupdates();
			}
			$fonttable->db->lock(0);
			fclose($fp);
		}
		if ($count > 0)
			$count = "<P>update {$count} records.</P>";
		else
			$count = "";
		$array = $fonttable->db->query("select * from {$fonttable->tablename};", array());
		$tablehtml = "";
		foreach ($array as $record) {
			switch ($type = $record["fonttype"] + 0) {
				default:
					if ($type >= 0)
						$type = "ttc:".$type;
					else
						$type = "unknown";
					break;
				case	-2:
					$type = "viewer font";
					break;
				case	-1:
					$type = "ttf";
					break;
			}
			$recordhtml = array();
			foreach ($record as $key => $val)
				$recordhtml[$key] = htmlspecialchars($val);
			$tablehtml .= <<<EOO
<TR><TD><A href="{$url}?mode=font&action=delete&id={$record["id"]}">delete</A>
	<TD>{$recordhtml["encode"]}
		<TD>{$type}
			<TD>{$recordhtml["fontname"]}
				<TD>{$recordhtml["fontcmap"]}
					<TD>{$recordhtml["attr_prop"]}
						<TD>{$recordhtml["attr_dir"]}
							<TD>{$recordhtml["attr_line"]}
								<TD>{$recordhtml["attr_italic"]}
									<TD>{$recordhtml["attr_weight"]}
										<TD>{$recordhtml["attr_width"]}
	<TD>{$recordhtml["class_15"]} {$recordhtml["class_14"]} {$recordhtml["class_13"]} {$recordhtml["class_12"]} 
	<TD>{$recordhtml["class_11"]} {$recordhtml["class_10"]} {$recordhtml["class_9"]} {$recordhtml["class_8"]} 
	<TD>{$recordhtml["class_7"]} {$recordhtml["class_6"]} {$recordhtml["class_5"]} {$recordhtml["class_4"]} 
	<TD>{$recordhtml["class_3"]} {$recordhtml["class_2"]} {$recordhtml["class_1"]} {$recordhtml["class_0"]} 
EOO;
		}
		print <<<EOO
{$html_header}
<H2>edit font table</H2>
{$count}
<TABLE border>
<TR><TH rowspan=2>
	<TH rowspan=2>encode
		<TH rowspan=2>fonttype
			<TH rowspan=2>fontname
				<TH rowspan=2>fontcmap
					<TH colspan=6>attr_
											<TH colspan=4>class_
<TR>					<TH>prop
						<TH>dir
							<TH>line
								<TH>italic
									<TH>weight
										<TH>width
	<TH>15-12
	<TH>11-8
	<TH>7-4
	<TH>3-0
{$tablehtml}
</TABLE>

<FORM enctype="multipart/form-data" method=POST>
<P>CSV file : <A href="{$url}?mode=font&action=download">download</A>
/ upload: <INPUT type=file name=fontcsv><INPUT type=submit></P>
</FORM>

EOO;
		break;
	case	"create":
		print <<<EOO
{$html_header}
<H2>create tables</H2>
EOO;
		$table =& $tcmaptable;
		$table->create();
		print "<P>".$table->gethtml("errorstring")."</P>\n";
		$table =& $fonttable;
		$table->create();
		print "<P>".$table->gethtml("errorstring")."</P>\n";
		break;
	case	"sql":
		print $html_header;
		if (strlen($sql = @$_POST["sql"]."") > 0) {
			$sql = ereg_replace("[\t\r\n]+", " ", unquote_post($sql));
			$result = $tcmapdb->directquery($sql);
			if ($result === FALSE)
				print "<P>query : ".($tcmapdb->gethtmlerror())."</P>\n";
			else if (count($result) <= 0)
				print "<P>updated : ".($tcmapdb->getupdates())."</P>\n";
			else {
				$first = 1;
				print "<TABLE border>\n";
				foreach ($result as $line) {
					if (($first)) {
						print "<TR>";
						foreach ($line as $key => $val)
							print "<TH>".htmlspecialchars($key);
						print "\n";
						$first = 0;
					}
					print "<TR>";
					foreach ($line as $key => $val)
						print "<TD>".htmlspecialchars($val);
					print "\n";
				}
				print "</TABLE>\n";
			}
		}
		print <<<EOO
<HR>
<H2>direct query</H2>
<FORM method=post>
<P><TEXTAREA cols=40 rows=10 name=sql>
</TEXTAREA>
<BR><INPUT type=submit></P>
</FORM>
EOO;
		break;
	default:
		print $html_header;
		break;
}



?>
<HR>
</BODY></HTML>
