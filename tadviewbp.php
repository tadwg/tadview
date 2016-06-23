<?php
$version = "tadviewbp 130626";

class	systeminfo {
	var	$welcomemessage = "";
	var	$fontpath = "font/";
	var	$pdffontname = "HeiseiMin-W3";
	var	$pdflicense = "";
	var	$op_body;
	var	$cl_body = "<HR></BODY></HTML>";
	var	$apache_child_terminate = 0;
	var	$zfpath = null;
	function	systeminfo() {
		global	$version;
		$this->op_body = "<HTML><HEAD><TITLE>{$version}</TITLE></HEAD><BODY>";
	}
	function	setuppdf($pid) {
		if (strlen($this->pdflicense) > 0)
			pdf_set_parameter($pid, "license", $this->pdflicense);
	}
	function	hook_start() {
		if (function_exists("hook_start")) {
			hook_start();
			return;
		}
		if ($this->apache_child_terminate) {
			@ini_set("child_terminate", 1);
			@apache_child_terminate();
		}
		ini_set("max_execution_time", "600");	# 10 minutes
		ini_set("memory_limit", "512M");
		ini_set("ignore_user_abort", "0");	# abort when session disconnect
	}
}
$systeminfo =& new systeminfo();
include("env.php");

$fn = @$_FILES["fl"]["tmp_name"];
if (is_uploaded_file($fn) !== TRUE) {
	print <<<eoo
{$systeminfo->op_body}
<H1>$version</H1>

<FORM enctype="multipart/form-data" method=POST>
<P>
<INPUT type=file name=fl><INPUT type=submit>
<BR>[NOT WORK]input:
	<LABEL><INPUT type=radio name=input value=8 checked>BPACK</LABEL>
<BR>output:
	<LABEL><INPUT type=radio name=output value=3>PDF</LABEL>
	<LABEL><INPUT type=radio name=output value=4 checked>PDF with font</LABEL>
----<LABEL><INPUT type=checkbox name=binary value=1>download</LABEL>
<BR>image:
	<LABEL><INPUT type=radio name=jpeg value=0 checked>PNG</LABEL>
	<LABEL><INPUT type=radio name=jpeg value=1>JPEG</LABEL>
<BR>figure TAD output size:
	<LABEL><INPUT type=radio name=figurepage value=0>argued size</LABEL>
	<LABEL><INPUT type=radio name=figurepage value=1>contain all objects</LABEL>
	<LABEL><INPUT type=radio name=figurepage value=2 checked>paper size</LABEL>
<BR>[NOT WORK]default colormap:
	<LABEL><INPUT type=radio name=colormap value=0 checked>none</LABEL>
	<LABEL><INPUT type=radio name=colormap value=1>1B monochrome</LABEL>
	<LABEL><INPUT type=radio name=colormap value=2>1B color</LABEL>
	<LABEL><INPUT type=radio name=colormap value=3>M-CUBE beta</LABEL>
	<LABEL><INPUT type=radio name=colormap value=4>M-CUBE</LABEL>
	<LABEL><INPUT type=radio name=colormap value=5>TiPO</LABEL>
	<LABEL><INPUT type=radio name=colormap value=6>B-right/V</LABEL>
	<LABEL><INPUT type=radio name=colormap value=7>B-right/V R3</LABEL>
----<LABEL><INPUT type=checkbox name=colormapignore value=1>ignore colormap on file</LABEL>
</P>
</FORM>

{$systeminfo->welcomemessage}
{$systeminfo->cl_body}
eoo;
	die();
}

class	request {
	var	$input = 0;
	var	$jpeg = 0;
	var	$output = 4;
	var	$binary = 0;
	var	$truecolor = 2;
	var	$figurepage = 2;
	function	request(&$arg) {
		if (@$arg["input"] !== null)
			$this->input = @$arg["input"] + 0;
		if (@$arg["jpeg"] !== null)
			$this->jpeg = @$arg["jpeg"] + 0;
		if (@$arg["output"] !== null)
			$this->output = @$arg["output"] + 0;
		if (@$arg["binary"] !== null)
			$this->binary = @$arg["binary"] + 0;
		if (@$arg["truecolor"] !== null)
			$this->truecolor = @$arg["truecolor"] + 0;
		if (@$arg["figurepage"] !== null)
			$this->figurepage = @$arg["figurepage"] + 0;
	}
}

class	downloadinfo {
	var	$filename;
	var	$filetype = "application/octet-stream";
	var	$disposition = "inline";
	function	downloadinfo() {
		$this->filename = "tadview".date("mdHi");
	}
	function	setfilename($str) {
		if (strlen($str) > 0)
#			$this->filename = str_replace(".", "_", $str);
			$this->filename = ereg_replace("[^-a-zA-Z0-9]+", "_", $str);
	}
	function	extendfilename($str) {
		$this->filename = str_replace(".", "_", $this->filename).$str;
	}
	function	setfiletype($str) {
		$this->filetype = $str;
	}
	function	setdisposition($str) {
		$this->disposition = $str;
	}
	function	putheader() {
		header("Content-Type: $this->filetype");
		header("Content-Disposition: $this->disposition; filename=$this->filename");
	}
	function	putbody($body) {	# not work with PNG/JPEG
		header("Content-Length: ".strlen($body));
		print $body;
	}
}

class	logger {
	var	$level;
	function	logger($level) {
		$this->level = $level;
	}
	function	info($str) {
		if ($this->level > 2)
			$this->put("information : ".htmlspecialchars($str));
	}
	function	warn($str) {
		if ($this->level > 1)
			$this->put("<FONT color=blue>warning</FONT> : ".htmlspecialchars($str));
	}
	function	error($str) {
		if ($this->level > 0)
			$this->put("<FONT color=red>error</FONT> : ".htmlspecialchars($str));
	}
	function	put($str) {
		print "<P><B>$str</B></P>\n";
	}
}

class	debug {
	var	$id = 0;
	function	getnextid() {
		return $this->id++;
	}
}

class	global_var {
	var	$systeminfo = null;
	var	$req = null;
	var	$file = null;
	var	$tcmapdb = null;
	var	$log = null;
	var	$debug = null;
	var	$overlayscopelist;
	var	$overlayattrlist;
	function	global_var() {
		$this->overlayscopelist = array();
		$this->overlayattrlist = array();
	}
}
$global =& new global_var();

$global->systeminfo =& $systeminfo;
$global->req =& new request($_REQUEST);
$global->file =& new downloadinfo();
$global->file->setfilename(basename(@$_FILES["fl"]["name"]));
$global->log =& new logger(($global->req->output < 0)? 3 : 0);
$global->debug =& new debug();

$global->systeminfo->hook_start();

#
# TAD independent class
#


class	fileinfo {
	var	$lh;		# local header
	var	$name;		# TC[20]
	var	$mtime = 0;
	var	$jumpto = null;	# for genv
}

class	linkinfo {
	var	$fileinfo = null;
	var	$attr = 0;
}


class	bitstream {
	var	$bitremain = 0;
	var	$bitbuf = 0;
	var	$linkinfo;
	var	$linkinfopos = 0;
	var	$parentlinkinfo;
	function	bitstream() {
		$this->linkinfo = array();
		$this->parentlinkinfo =& $this;
	}
	function	appendlinkinfo(&$linkinfo) {
		$this->linkinfo[] =& $linkinfo;
	}
	function	&getlinkinfo() {
		if ($this->parentlinkinfo !== $this)
			return $this->parentlinkinfo->getlinkinfo();
		if ($this->linkinfopos >= count($this->linkinfo)) {
			$null = null;
			return $null;
		}
		return $this->linkinfo[$this->linkinfopos++];
	}
	function	rewindlinkinfo() {
		$this->linkinfopos = 0;
	}
	function	fillbit() {
		while ($this->bitremain <= 23) {
			if (!$this->remain())
				die("end of file.");
			$this->bitbuf = ($this->bitbuf | ($this->ub() << (23 - $this->bitremain))) & 0x7fffffff;
			$this->bitremain += 8;
#printf("bitbuf(%08x) bitremain(%d)<BR>\n", $this->bitbuf, $this->bitremain);
		}
	}
	function	pollbit($len = 1) {
		if ($len > 31)
			die("bitstream::pollbit > 31");
		$this->fillbit();
		return $this->bitbuf >> (31 - $len);
	}
	function	getbit($len = 1) {
		$val = $this->pollbit($len);
		$this->bitbuf = ($this->bitbuf << $len) & 0x7fffffff;
		$this->bitremain -= $len;
#printf("bitbuf(%08x) bitremain(%d)<BR>\n", $this->bitbuf, $this->bitremain);
		return $val;
	}
	function	uhs($size) {
		$array = array();
		while ($size-- > 0)
			$array[] = $this->uh();
		return $array;
	}
}


class	bytestream	extends	bitstream {
	var	$top = 0;	# minimum of $pos
	var	$end = 0;	# maximum of $pos
	var	$pos = 0;
	function	bytestream() {
		parent::bitstream();
	}
	function	uh() {
		$low = $this->ub();
		return $low | ($this->ub() << 8);
	}
	function	uw() {
		$low = $this->uh();
		return $low | $this->uh() << 16;
	}
	function	b() {
		$val = $this->ub();
		if ($val & 0x80)
			$val -= 0x100;
		return $val;
	}
	function	h() {
		$val = $this->uh();
		if ($val & 0x8000)
			$val -= 0x10000;
		return $val;
	}
	function	w() {
		$val = $this->uw();
# have no effect:
#		if ($val & 0x80000000)
#			$val -= 0x100000000;
		return $val;
	}
	function	offset($pos = FALSE, $mode = SEEK_SET) {
		if ($pos !== FALSE) {
			switch ($mode) {
				case	SEEK_SET:
					$this->pos = $this->top + $pos;
					break;
				case	SEEK_CUR:
					$this->pos += $pos;
					break;
				case	SEEK_END:
					$this->pos = $this->end + $pos;
					break;
				default:
					die("seek($mode).");
			}
			if ($this->pos < $this->top)
				$this->pos = $this->top;
			if ($this->pos > $this->end)
				$this->pos = $this->end;
		}
		return $this->pos - $this->top;
	}
	function	remain() {
		return $this->end - $this->pos;
	}
	function	size() {
		return $this->end - $this->top;
	}
};

# KNOWN-BUG:file-pointer never close.
class	file_bytestream extends bytestream {
	var	$path = "";
	var	$fp = -1;
	function	file_bytestream($path) {
		parent::bytestream();
		$this->path = $path;
		$this->fp = fopen($this->path, "r") or die("file($this->path) open failed.");
		$array = fstat($this->fp);
		$this->end = $array["size"];
	}
	function	ub() {
		if ($this->remain() <= 0)
			return FALSE;
		$this->pos++;
		return ord(fgetc($this->fp));
	}
	function	offset($pos = FALSE, $mode = SEEK_SET) {
		$val = parent::offset($pos, $mode);
		fseek($this->fp, $this->pos, SEEK_SET);
		return $val;
	}
	function	&clone_stream() {
		$newstream =& new file_bytestream($this->path);
		$newstream->top = $this->top;
		$newstream->end = $this->end;
		$newstream->pos = $this->pos;
		$newstream->parentlinkinfo =& $this->parentlinkinfo;
		return $newstream;
	}
	function	&subst_stream($size) {
		$newstream =& new file_bytestream($this->path);
		$newstream->top = $newstream->pos = $this->pos;
		$newstream->end = $this->pos + $size;
		$newstream->parentlinkinfo =& $this->parentlinkinfo;
		$newstream->offset(0, SEEK_SET);
		$this->offset($size, SEEK_CUR);
		return $newstream;
	}
	function	getinfostr() {
		return sprintf("stream offset(%08x) in file top(%08x) end(%08x)", $this->pos, $this->top, $this->end);
	}
};

class	array_bytestream extends bytestream {
	var	$buffer;
	var	$parent_infostr = "(none)";
	function	array_bytestream() {
		parent::bytestream();
		$this->buffer = array();
	}
	function	append(&$uh_array) {
		$this->buffer = array_merge($this->buffer, $uh_array);
		$this->end += count($uh_array) * 2;
	}
	function	ub() {
		if ($this->remain() <= 0)
			return FALSE;
		if (($this->pos & 1) == 0)
			return $this->buffer[($this->pos++) / 2] & 0xff;
		return $this->buffer[(($this->pos++) - 1) / 2] >> 8;
	}
	function	uh() {
		if ($this->remain() <= 0)
			return FALSE;
		if (($this->pos & 1))
			return parent::uh();
		$val = $this->buffer[$this->pos / 2];
		$this->pos += 2;
		return $val;
	}
	function	&clone_stream() {
		$newstream =& new array_bytestream();
		$newstream->buffer =& $this->buffer;
		$newstream->top = $this->top;
		$newstream->end = $this->end;
		$newstream->pos = $this->pos;
		$newstream->parent_infostr = $this->parent_infostr;
		$newstream->parentlinkinfo =& $this->parentlinkinfo;
		return $newstream;
	}
	function	&subst_stream($size) {
		$newstream =& new array_bytestream();
		$newstream->buffer =& $this->buffer;
		$newstream->top = $newstream->pos = $this->pos;
		$newstream->end = $this->pos + $size;
		$newstream->offset(0, SEEK_SET);
		$this->offset($size, SEEK_CUR);
		$newstream->parent_infostr = $this->parent_infostr;
		$newstream->parentlinkinfo =& $this->parentlinkinfo;
		return $newstream;
	}
	function	getinfostr() {
		return sprintf("stream offset(%08x) in array top(%08x) end(%08x) from %s", $this->pos, $this->top, $this->end, $this->parent_infostr);
	}
}

class	memory_bytestream extends array_bytestream {
	function	append(&$stream, $size) {
		$array = array();
		while ($size > 0) {
			$array[] = $stream->uh();
			$size -= 2;
		}
		parent::append($array);
		$this->parent_infostr = $stream->getinfostr();
	}
}

class	dump_bytestream {
	function	ub(&$stream) {
		printf("<BLOCKQUOTE><TT>\n");
		while ($stream->remain() > 0) {
			if (($stream->offset() & 0xf) == 0)
				printf("+%04x :", $stream->offset());
			printf(" %02x", $stream->ub());
			if (($stream->offset() & 0xf) == 0)
				printf("<BR>\n");
		}
		printf("</TT></BLOCKQUOTE>\n");
	}
	function	uh(&$stream) {
		printf("<TT><BLOCKQUOTE>\n");
		while ($stream->remain() > 0) {
			if (($stream->offset() & 0xf) == 0)
				printf("+%04x :", $stream->offset());
			printf(" %04x", $stream->uh());
			if (($stream->offset() & 0xf) == 0)
				printf("<BR>\n");
		}
		printf("</TT></BLOCKQUOTE>\n");
	}
	function	tad(&$stream) {
		while ($stream->remain() > 0) {
			$offset = $stream->offset();
			$type = $stream->uh();
			if ($type < 0xff80) {
				printf("<P>offset(%08x) char : 0x%04x</P>", $offset, $type);
				continue;
			}
			$len = $stream->uh();
			if ($len == 0xffff)
				$len = $stream->uw();
			printf("<P>offset(%08x) segment(%04x) length(%d)</P>", $offset, $type, $len);
#			$this->uh($stream->subst_stream($len));
			$st =& new memory_bytestream();
			$st->append($stream, $len);
			$this->uh($st);
		}
	}
	function	test_tad($filename) {
		print <<<eoo
<HTML><HEAD><TITLE>taddump</TITLE></HEAD><BODY>
<H1>taddump</H1>
eoo;
#		$test_dump =& new dump_bytestream();
		$test = "dump_bytestream";
		$test_dump = new $test();
		$test_dump->tad(new file_bytestream($filename));
		print <<<eoo
<HR>
</BODY></HTML>
eoo;
	}
}

if ($global->req->output == -1) {
	dump_bytestream::test_tad($fn);
	die();
}

#
# TAD handling class
#

function	int($val)
{
	return round($val - 0.2);
}
function	getcodelist(&$stream) {
	$codelist = array();
	$lang = 0x21;
	while ($stream->remain() >= 2) {
		if (($code = $stream->uh()) == 0)
			continue;	# TNULL
		if ($code < 0xfe00) {
			$codelist[] = ($lang << 16) | $code;
			continue;
		}
		if ($code >= 0xff00)
			continue;	# TAD escape
		$lang = 0;
		while ($code == 0xfefe) {
			if ($stream->remain() < 2)
				break 2;
			$lang += 0x200;
			$code = $stream->uh();
		}
		if (($code & 0xff00) != 0xfe00)
			$lang -= 0x100;
		$lang |= $code & 0xff;
	}
	return $codelist;
}

class	scale {
	var	$view = array(0, 0, 1, 1, 1, 1);
	var	$draw = array(0, 0, 1, 1, 1, 1);
	function	setview($left = 0, $top = 0, $right = 1, $bottom = 1) {
		$this->view = array($left, $top, $right, $bottom, $right - $left, $bottom - $top);
	}
	function	setdraw($left = 0, $top = 0, $right = 1, $bottom = 1) {
		$this->draw = array($left, $top, $right, $bottom, $right - $left, $bottom - $top);
	}
	function	getview(&$left, &$top, &$right, &$bottom) {
		$left = $this->view[0];
		$top = $this->view[1];
		$right = $this->view[2];
		$bottom = $this->view[3];
	}
	function	getdraw(&$left, &$top, &$right, &$bottom) {
		$left = $this->draw[0];
		$top = $this->draw[1];
		$right = $this->draw[2];
		$bottom = $this->draw[3];
	}
	function	import_copy(&$scale) {
		$this->view = $scale->view;
		$this->draw = $scale->draw;
	}
	function	convert_point(&$x, &$y) {
		$x = $this->view[0] + ($x - $this->draw[0]) * $this->view[4] / $this->draw[4];
		$y = $this->view[1] + ($y - $this->draw[1]) * $this->view[5] / $this->draw[5];
	}
	function	rconvert_point(&$x, &$y) {
		$x = $this->draw[0] + ($x - $this->view[0]) * $this->draw[4] / $this->view[4];
		$y = $this->draw[1] + ($y - $this->view[1]) * $this->draw[5] / $this->view[5];
	}
	function	convert_size(&$h, &$v) {
		$h = $h * $this->view[4] / $this->draw[4];
		$v = $v * $this->view[5] / $this->draw[5];
	}
	function	rconvert_size(&$h, &$v) {
		$h = $h * $this->draw[4] / $this->view[4];
		$v = $v * $this->draw[5] / $this->view[5];
	}
	function	convert_length(&$length) {
		$length = $length * ($this->view[4] + $this->view[5]) / ($this->draw[4] + $this->draw[5]);
	}
	function	rconvert_length(&$length) {
		$length = $length * ($this->draw[4] + $this->draw[5]) / ($this->view[4] + $this->view[5]);
	}
}

class	attrlist {
	var	$list;
	function	attrlist() {
		$this->list[""] = null;
	}
	function	add($key, $val) {
		$this->list[$key] = $val;
	}
	function	get($key) {
		return @$this->list[$key];
	}
	function	clearcurrentattr() {
		return;
	}
	function	importcopy(&$attrlist) {
		foreach ($attrlist->list as $key => $val)
			$this->list[$key] = $val;
	}
	function	importshare(&$attrlist) {
		$this->list =& $attrlist->list;
	}
}

class	pageattrlist extends attrlist {
	var	$factoryname = "pageattr";
	var	$scope;
	function	pageattrlist() {
		parent::attrlist();
		$this->add("dpih", 120);
		$this->add("dpiv", 120);
		$this->add("dpch", (12000 / 254));
		$this->add("dpcv", (12000 / 254));
		$this->add("pageattr", 0);
		$this->add("pageh", 297 * 120 / 25.4);
		$this->add("pagev", 210 * 120 / 25.4);
		$this->add("pageage", 1);
		$this->add("marginl", 10 * 120 / 25.4);
		$this->add("margint", 10 * 120 / 25.4);
		$this->add("marginr", 10 * 120 / 25.4);
		$this->add("marginb", 10 * 120 / 25.4);
		$this->add("marginol", 10 * 120 / 25.4);
		$this->add("marginot", 10 * 120 / 25.4);
		$this->add("marginor", 10 * 120 / 25.4);
		$this->add("marginob", 10 * 120 / 25.4);
		$this->add("overlay", 0);
		$this->add("overlay-0", -1);
		$this->add("overlay-1", -1);
		$this->add("overlay-2", -1);
		$this->add("overlay-3", -1);
		$this->add("overlay-4", -1);
		$this->add("overlay-5", -1);
		$this->add("overlay-6", -1);
		$this->add("overlay-7", -1);
		$this->add("overlay-8", -1);
		$this->add("overlay-9", -1);
		$this->add("overlay-a", -1);
		$this->add("overlay-b", -1);
		$this->add("overlay-c", -1);
		$this->add("overlay-d", -1);
		$this->add("overlay-e", -1);
		$this->add("overlay-f", -1);
		$this->add("pagestep", 1);
		$this->add("pagecount", 1);
		$this->add("pagenumber", 1);
		$this->add("pagetotal", 0);
	}
	function	tadfactory(&$scope, $dig = 0) {
		$this->scope =& $scope;
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("ffa0", $target);
		$this->scope->factoryfinder->add("ffb5", $target);
	}
	function	&create($key, $type, &$stream) {
		global	$global;
		
		if ($stream->remain() < 2)
			return $this->scope->factoryfinder;
		$subtype = $stream->uh();
		switch (($type << 8)|($subtype >> 8)) {
			case	0xffa000:
			case	0xffb500:
				if ($stream->remain() < 12)
					break;
				$this->add("pageattr", $subtype & 0xff);
				$this->add("pagev", $stream->uh());
				$this->add("pageh", $stream->uh());
				$this->add("marginot", $stream->uh());
				$this->add("marginob", $stream->uh());
				$this->add("marginol", $stream->uh());
				$this->add("marginor", $stream->uh());
				return $this->scope->factoryfinder->read("000d0000", 0xf, $stream);
			case	0xffa001:
			case	0xffb501:
				if ($stream->remain() < 8)
					break;
				$this->add("margint", $stream->uh());
				$this->add("marginb", $stream->uh());
				$this->add("marginl", $stream->uh());
				$this->add("marginr", $stream->uh());
				return $this->scope->factoryfinder->read("000d0000", 0xf, $stream);
			case	0xffa003:
			case	0xffb503:
				if ($stream->remain() < 2)
					break;
				$overlayscope =& new basescopeparts();
				$newscope =& new textscopeparts($overlayscope);
				$newscope->stream_infostr = $stream->getinfostr();
				$newscope->factoryfinder->add("ffe2", $this);
				$newscope->pageattrlist->add("dpih", $this->get("dpih"));
				$newscope->pageattrlist->add("dpiv", $this->get("dpiv"));
				$newscope->pageattrlist->add("dpch", $this->get("dpch"));
				$newscope->pageattrlist->add("dpcv", $this->get("dpcv"));
				$newscope->textattrlist->add("lang", 0x21);
				
				$overlayscope->partslist->add($newscope);
				$newscope->pageattrlist->tadfactory($newscope);
				$newscope->factoryfinder->readall($stream);
				
				$global->overlayscopelist[] =& $overlayscope;
				$global->overlayattrlist[] = $subtype & 0xff;
				$this->add(sprintf("overlay-%x", $subtype & 0xf), count($global->overlayscopelist) - 1);
				return $this->scope->factoryfinder;
			case	0xffa004:
			case	0xffb504:
				if ($stream->remain() < 2)
					break;
				$this->add("overlay", $stream->uh());
				return $this->scope->factoryfinder;
			case	0xffa006:
			case	0xffb506:
				if ($stream->remain() < 2)
					break;
				$this->add("pageage", $this->get("pageage") + 1);
				$this->add("pagestep", $subtype & 0xff);
				$this->add("pagenumber", $stream->uh());
				return $this->scope->factoryfinder;
			case	0xffa008:
				return $this->scope->factoryfinder->read("ffa00800", 0xffa008, $stream);
			default:
				break;
		}
		if ($type == 0xffa0)
			return $this->scope->factoryfinder->read("000d0000", 0xc, $stream);
		return $this->scope->factoryfinder;
	}
	function	set_dpi_h($val) {
		if ($val == 0)
			return;
		if ($val > 0) {
			$this->add("dpih", int($val * 254 / 100));
			$this->add("dpch", $val);
			return;
		}
		$this->add("dpih", -$val);
		$this->add("dpch", int(-$val * 100 / 254));
	}
	function	set_dpi_v($val) {
		if ($val == 0)
			return;
		if ($val > 0) {
			$this->add("dpiv", int($val * 254 / 100));
			$this->add("dpcv", $val);
			return;
		}
		$this->add("dpiv", -$val);
		$this->add("dpcv", int(-$val * 100 / 254));
	}
	function	getpagestep() {
		return $this->get("pagestep");
	}
	function	setupoverlay(&$target, $count, $page, $total) {
		$target->pageattrlist->add("pageh", $this->get("pageh"));
		$target->pageattrlist->add("pagev", $this->get("pagev"));
		$target->pageattrlist->add("marginl", $this->get("marginol"));
		$target->pageattrlist->add("margint", $this->get("marginot"));
		$target->pageattrlist->add("marginr", $this->get("marginor"));
		$target->pageattrlist->add("marginb", $this->get("marginob"));
		$target->pageattrlist->add("pagecount", $count);
		$target->pageattrlist->add("pagenumber", $page);
		$target->pageattrlist->add("pagetotal", $total);
	}
	function	drawoverlay(&$genv, $count = 1, $page = 1, $total = 1) {
		global	$global;
		
		for ($i=0; $i<16; $i++) {
			if (($this->get("overlay") & (0x8000 >> $i)) == 0)
				continue;
			if (($index = $this->get(sprintf("overlay-%x", $i))) < 0)
				continue;
			$global->log->info(sprintf("drawoverlay(%x) ----", $i));
			switch ($global->overlayattrlist[$index] & 0x30) {
				case	0x10:
					if (($count & 1) == 0)
						continue 2;	# skip if even
					break;
				case	0x20:
					if (($count & 1))
						continue 2;	# skip if odd
					break;
			}
			$overlayscope =& $global->overlayscopelist[$index];
			$overlayscope->partslist->rewind();
			$target =& $overlayscope->partslist->get();
			$this->setupoverlay($target, $count, $page, $total);
			$target->partslist->rewind();
			while ($target->partslist->remain() > 0) {
				$parts =& $target->partslist->get();
				$this->setupoverlay($parts, $count, $page, $total);
			}
			$left = $top = $right = $bottom = 0;
			$target->draw($genv, $left, $top, $right, $bottom, 1);
		}
		$global->log->info(sprintf("drawoverlay end ----"));
	}
}

class	textattrlist extends attrlist {
	var	$factoryname = "textattr";
	var	$scope;
	function	textattrlist() {
		parent::attrlist();
		$this->add("fontname", "");
		$this->add("charclass", 0x60c6);
		$this->add("charattr", 0);
		$this->add("charsize", 16);
		$this->add("charratio_h", 0x0101);
		$this->add("charratio_v", 0x0101);
		$this->add("charcolor", 0);
		$this->add("escapeflag", 0);
		$this->add("linepitch", 0x0304);
		$this->add("linepitchattr", 1);
		$this->add("charpitch", 0x0108);
		$this->add("charpitchattr", 1);
		$this->add("underline", -1);
		$this->add("wordwrapt", array());
		$this->add("wordwrapb", array());
		$this->add("wordwraptype", 0);
		$this->add("rubi", 0);
		$this->add("subscript", 0);
		$this->add("linealign", 0);
	}
	function	tadfactory(&$scope, $dig = 0) {
		$this->scope =& $scope;
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("fe21", $target);
		$this->scope->factoryfinder->add("ffa1", $target);
		$this->scope->factoryfinder->add("ffa2", $target);
		$this->scope->factoryfinder->add("ffa4", $target);
		$this->scope->factoryfinder->add("ffa5", $target);
		$this->scope->factoryfinder->add("000d0000", $target);
	}
	function	todpc(&$val, &$scope) {
		return ($val & 0x3fff) * ($scope->pageattrlist->get("dpcv")) / 800;
	}
	function	todpi(&$val, &$scope) {
		return ($val & 0x3fff) * ($scope->pageattrlist->get("dpiv")) / 1440;
	}
	function	chsztodot($val) {
		switch ($val & 0xc000) {
			case	0:
				return $val;
			case	0x4000:
				return $this->todpc($val, $this->scope);
			case	0x8000:
				return $this->todpi($val, $this->scope);
		}
		return 0;
	}
	function	&create($key, $type, &$stream) {
		switch ($key) {
			case	"000d0000":
				if ($this->get("escapeflag") < $type)
					$this->add("escapeflag", $type);
				return $this->scope->factoryfinder;
			case	"fe21":
				$this->scope->textattrlist->add("lang", $type);
				return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 2)
			return $this->scope->factoryfinder;
		$subtype = $stream->uh();
		switch (($type << 8)|($subtype >> 8)) {
			case	0xffa100:
				if ($stream->remain() < 2)
					break;
				$this->add("linepitchattr", $subtype & 0xff);
				$this->add("linepitch", $stream->uh());
				return $this->scope->factoryfinder->read("000d0000", 0xa, $stream);
			case	0xffa101:
				$this->add("linealign", $subtype & 0xff);
				return $this->scope->factoryfinder->read("000d0000", 0xa, $stream);
			case	0xffa104:
				$this->add("chardir", $subtype & 0xff);
				return $this->scope->factoryfinder->read("000d0000", 0xe, $stream);
			case	0xffa200:
				if ($stream->remain() < 2)
					break;
				$this->add("charclass", $stream->uh());
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa201:
				if ($stream->remain() < 2)
					break;
				$this->add("charattr", $stream->uh());
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa202:
				if ($stream->remain() < 2)
					break;
				$this->add("charsize", $this->chsztodot($stream->uh()));
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa203:
				if ($stream->remain() < 4)
					break;
				$ratio = $stream->uh();
				if (($ratio & 0xff) == 0)
					$ratio = 0x0101;
				$this->add("charratio_v", $ratio);
				$ratio = $stream->uh();
				if (($ratio & 0xff) == 0)
					$ratio = 0x0101;
				$this->add("charratio_h", $ratio);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa204:
				if ($stream->remain() < 2)
					break;
				$this->add("charpitchattr", $subtype & 0xff);
				$this->add("charpitch", $stream->uh());
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa206:
				if ($stream->remain() < 4)
					break;
				$this->add("charcolor", $stream->uw() & 0x00ffffff);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa404:
				if ($stream->remain() < 4)
					break;
				$this->scope->textattrlist->add("subscript", 1);
				$this->scope->textattrlist->add("subscripttype", $subtype & 0xff);
				$this->scope->textattrlist->add("subscriptpos", $stream->uh());
				$this->scope->textattrlist->add("subscriptsize", $stream->uh());
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa405:
				$this->scope->textattrlist->add("subscript", 0);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa406:
				$this->scope->textattrlist->add("rubi", 1);
				$factoryfinder =& $this->scope->factoryfinder->read("000d0000", 7, $stream);
				return $factoryfinder->read("ffa40600", $subtype, $stream);
			case	0xffa407:
				$this->scope->textattrlist->add("rubi", 0);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa408:
				if ($stream->remain() <= 0)
					$codelist = array(
						0x212122, 0x212123, 0x212124, 0x212125, 0x21212b, 0x21212c, 
						0x212147, 0x212149, 0x21214b, 0x21214d, 0x21214f, 0x212151, 
						0x212153, 0x212155, 0x212157, 0x212159, 0x21215b
					);
				else
					$codelist = getcodelist($stream);
				$this->scope->textattrlist->add("wordwrapt", $codelist);
				$this->scope->textattrlist->add("wordwraptype", $type & 0xff);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa409:
				if ($stream->remain() <= 0)
					$codelist = array(
						0x212146, 0x212148, 0x21214a, 0x21214c, 0x21214e, 0x212150, 
						0x212152, 0x212154, 0x212156, 0x212158, 0x21215a
					);
				else
					$codelist = getcodelist($stream);
				$this->scope->textattrlist->add("wordwrapb", $codelist);
				$this->scope->textattrlist->add("wordwraptype", $type & 0xff);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa500:
				$this->add("underline", 1);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
			case	0xffa501:
				$this->add("underline", -1);
				return $this->scope->factoryfinder->read("000d0000", 7, $stream);
		}
		$stream->offset(0, SEEK_SET);
		return $this->scope->factoryfinder;
	}
	function	getcharwidth($subscript = 1) {
		$ratio = $this->get("charratio_h");
		$val = int($this->get("charsize") * (($ratio >> 8) & 0xff) / ($ratio & 0xff));
		if (($subscript)&&($this->get("subscript"))) {
			$ratio = $this->get("subscriptsize");
			$val = int($val * (($ratio >> 8) & 0xff) / ($ratio & 0xff));
		}
		return $val;
	}
	function	getcharheight($subscript = 1) {
		$ratio = $this->get("charratio_v");
		$val = int($this->get("charsize") * (($ratio >> 8) & 0xff) / ($ratio & 0xff));
		if (($subscript)&&($this->get("subscript"))) {
			$ratio = $this->get("subscriptsize");
			$val = int($val * (($ratio >> 8) & 0xff) / ($ratio & 0xff));
		}
		return $val;
	}
	function	getcharattr() {
		$val = $this->get("charattr");
		if ($this->get("chardir") == 2)
			$val |= 0x4000;
		return $val;
	}
	function	feedchar(&$layout_feed, $limit) {
		$val = ($limit[0] != 0)? $this->getcharwidth() : $this->getcharheight();
		if ((($ratio = $this->get("charpitch")) & 0x8000))
			$val = $ratio & 0x7fff;
		else if (($ratio & 0xff))
			$val = int($val * (($ratio >> 8) & 0xff) / ($ratio & 0xff));
		if ((($attr = $this->get("charpitchattr")) & 0x8000))
			$val = -$val;
		if (($attr & 1)) {
			if ($limit[0] > 0)
				$layout_feed[2] += $val;
			else if ($limit[0] < 0)
				$layout_feed[0] -= $val;
			else if ($limit[1] > 0)
				$layout_feed[3] += $val;
			else if ($limit[1] < 0)
				$layout_feed[1] -= $val;
		} else {
			if ($limit[0] > 0) {
				$layout_feed[0] += $val;
				$layout_feed[2] = 0;
			} else if ($limit[0] < 0) {
				$layout_feed[0] = 0;
				$layout_feed[2] -= $val;
			} else if ($limit[1] > 0) {
				$layout_feed[1] += $val;
				$layout_feed[3] = 0;
			} else if ($limit[1] < 0) {
				$layout_feed[1] = 0;
				$layout_feed[3] -= $val;
			}
		}
	}
	function	feedline(&$layout_feed, $limit, $charwidth, $charheight) {
		$val = ($limit[0] != 0)? $charwidth : $charheight;
		if ((($ratio = $this->get("linepitch")) & 0x8000))
			$val = $ratio & 0x7fff;
		else if (($ratio & 0xff))
			$val = int($val * (($ratio >> 8) & 0xff) / ($ratio & 0xff));
		if ((($attr = $this->get("linepitchattr")) & 0x8000))
			$val = -$val;
		if (($attr & 1)) {
			if ($limit[0] > 0)
				$layout_feed[2] += $val;
			else if ($limit[0] < 0)
				$layout_feed[0] -= $val;
			else if ($limit[1] > 0)
				$layout_feed[3] += $val;
			else if ($limit[1] < 0)
				$layout_feed[1] -= $val;
		} else {
			if ($limit[0] > 0) {
				$layout_feed[0] += $val;
				$layout_feed[2] = 0;
			} else if ($limit[0] < 0) {
				$layout_feed[0] = 0;
				$layout_feed[2] -= $val;
			} else if ($limit[1] > 0) {
				$layout_feed[1] += $val;
				$layout_feed[3] = 0;
			} else if ($limit[1] < 0) {
				$layout_feed[1] = 0;
				$layout_feed[3] -= $val;
			}
		}
	}
}

class	figattrlist extends attrlist {
	var	$factoryname = "figattr";
	var	$templist;
	var	$scope;
	function	figattrlist() {
		parent::attrlist();
		$this->cleartempattr();
		$this->add("colormap-0000", 0xffffff);
		$this->add("mask-0001", array(array(0)));
		$this->add("mask-0002", array(array(1, 0, 0, 0), array(0, 0, 0, 0), array(0, 0, 1, 0), array(0, 0, 0, 0)));
		$this->add("mask-0003", array(array(1, 0), array(0, 0), array(0, 1), array(0, 0)));
		$this->add("mask-0004", array(array(1, 0), array(0, 1)));
		$this->add("mask-0005", array(array(1, 1), array(1, 0), array(1, 1), array(0, 1)));
		$this->add("mask-0006", array(array(1, 1, 1, 1), array(1, 0, 1, 1), array(1, 1, 1, 1), array(1, 1, 1, 0)));
		$this->add("mask-0007", array(array(1)));
		$this->add("mask-0008", array(array(1, 0, 0, 0)));
		$this->add("mask-0009", array(array(1), array(0), array(0), array(0)));
		$this->add("mask-000a", array(array(1, 0, 0, 0), array(0, 0, 0, 1), array(0, 0, 1, 0), array(0, 1, 0, 0)));
		$this->add("mask-000b", array(array(1, 0, 0, 0), array(0, 1, 0, 0), array(0, 0, 1, 0), array(0, 0, 0, 1)));
		$this->add("mask-000c", array(array(1, 1, 1, 1), array(1, 0, 0, 0), array(1, 0, 0, 0), array(1, 0, 0, 0)));
		$this->add("mask-000d", array(array(1, 0, 0, 0), array(0, 1, 0, 1), array(0, 0, 1, 0), array(0, 1, 0, 1)));
		$this->add("pattern-0000", array(array(-1)));
		$this->add("linetype-0000", array(0xff));
		$this->add("linetype-0001", array(0xff, 0));
		$this->add("linetype-0002", array(0xcc));
		$this->add("linetype-0003", array(0xff, 0xcc));
		$this->add("linetype-0004", array(0xff, 0xfc, 0xcc));
		$this->add("linetype-0005", array(0xff, 0xff, 0xff, 0));
	}
	function	addtemp($key, $val) {
		$this->templist[$key] = $val;
	}
	function	get($key) {
		if (($val = @$this->templist[$key]) !== null)
			return $val;
		return parent::get($key);
	}
	function	cleartempattr() {
		$this->templist = array("" => null);
	}
	function	importcopy(&$attrlist) {
		foreach ($attrlist->list as $key => $val)
			$this->list[$key] = $val;
		foreach ($attrlist->templist as $key => $val)
			$this->list[$key] = $val;
	}
	function	importshare(&$attrlist) {
		parent::importshare($attrlist);
		$this->templist =& $attrlist->templist;
	}
	function	tadfactory(&$scope, $dig = 0) {
		$this->scope =& $scope;
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("ffb1", $target);
		$this->scope->factoryfinder->add("ffb4", $target);
	}
	function	&create($key, $type, &$stream) {
		if ($stream->remain() < 2)
			return $this->scope->factoryfinder;
		$subtype = $stream->uh();
		switch (($type << 8)|($subtype >> 8)) {
			case	0xffb100:	# colormap
				if ($stream->remain() < 2)
					break;
				$count = $stream->uh();
				if ($stream->remain() < 4 * $count)
					break;
				for ($i=0; $i<$count; $i++)
					$this->add(sprintf("colormap-%04x", $i), $stream->uw() & 0x00ffffff);
				break;
			case	0xffb101:	# mask
				if (($subtype & 0xff) != 0)
					break;
				if ($stream->remain() < 6)
					break;
				if (($id = $stream->uh()) < 0)
					break;
				$width = $stream->uh();
				$height = $stream->uh();
				if ($width <= 0)
					break;
				if ($height <= 0)
					break;
				$count = floor(($width + 15) / 16);
				if ($stream->remain() < $count * $height * 2)
					break;
				$array_v = array(array(0));
				for ($y=0; $y<$height; $y++) {
					$array_h = array(0);
					for ($x=0; $x<$width; $x++) {
						if (($x & 15) == 0)
							$bitmap = $stream->uh();
						$array_h[$x] = ($bitmap & 0x8000)? 1 : 0;
						$bitmap <<= 1;
					}
					$array_v[$y] = $array_h;
				}
				$this->add(sprintf("mask-%04x", $id), $array_v);
				break;
			case	0xffb102:	# pattern
				if (($subtype & 0xff) != 0)
					break;
				if ($stream->remain() < 8)
					break;
				if (($id = $stream->uh()) <= 0)
					break;
				$width = $stream->uh();
				$height = $stream->uh();
				$count = $stream->uh();
				if ($stream->remain() < 6 * $count + 4)
					break;
				for ($i=0; $i<=$count; $i++) {
					$color = $stream->uw();
					switch ($color & 0xf0000000) {
						case	0:
							$color = $this->get(sprintf("colormap-%04x", $color));
							break;
						default:
							if ($color < 0) {
								$color = -1;
								break;
							}
						case	0x10000000:
							$color &= 0xffffff;
							break;
					}
					$fgcol[$i] = $color;
				}
				$pattern = array_fill(0, $height, array_fill(0, $width, $color));
				for ($i=0; $i<$count; $i++) {
					$mask = $this->get(sprintf("mask-%04x", $stream->uh()));
					$color = $fgcol[$i];
					for ($y=0; $y<$height; $y++)
						for ($x=0; $x<$width; $x++) {
							if ($color < 0) {
								if ($this->maskbit($mask, $x, $y) != 0)
									continue;
							} else if ($this->maskbit($mask, $x, $y) == 0)
								continue;
							$pattern[$y][$x] = $color;
						}
				}
				$this->add(sprintf("pattern-%04x", $id), $pattern);
				break;
			case	0xffb103:	# linetype
				if (($subtype & 0xff) != 0)
					break;
				if ($stream->remain() < 4)
					break;
				if (($id = $stream->uh()) < 0)
					break;
				if ($id > 255)
					break;
				$count = $stream->uh();
				if ($stream->remain() < $count)
					break;
				$array = array(0);
				$pos = 0;
				for (;;) {
					$data = $stream->uh();
					$array[$pos++] = $data & 0xff;
					if ($pos >= $count)
						break;
					$array[$pos++] = $data >> 8;
					if ($pos >= $count)
						break;
				}
				$this->add(sprintf("linetype-%04x", $id), $array);
				break;
			case	0xffb400:
				switch ($subtype & 0xff) {
					case	0:	# arrow
						if ($stream->remain() < 2)
							break;
						$this->addtemp("arrow", $stream->uh());
						break;
				}
				break;
			default:
				break;
		}
		return $this->scope->factoryfinder;
	}
	function	maskbit($mask, $x, $y) {
		$array_h = $mask[$y % count($mask)];
		return $array_h[$x % count($array_h)];
	}
}

class	partslist {
	var	$list;
	var	$pos = 0;
	function	partslist() {
		$this->list = array();
	}
	function	add(&$parts) {
		$this->list[] =& $parts;
	}
	function	rewind() {
		$this->pos = 0;
	}
	function	&get() {
		if (count($this->list) <= $this->pos) {
			$val = null;
			return $val;
		}
		$val = @$this->list[$this->pos++];
		return $val;
	}
	function	&get_last() {
		return $this->list[count($this->list) - 1];
	}
	function	unget() {
		if ($this->pos > 0)
			$this->pos--;
	}
	function	remain() {
		return max(0, count($this->list) - $this->pos);
	}
	function	count() {
		return count($this->list);
	}
	function	setpos($pos) {
		$this->pos = $pos;
	}
	function	truncate($size) {
		array_splice($this->list, $size);
	}
}

class	tadfactory_parts {
	var	$factoryname = "";
	var	$scope;
	function	tadfactory_parts(&$scope) {
		$this->scope =& $scope;
	}
	function	&create($key, $type, &$stream) {
		return $this->scope->factoryfinder;
	}
}

class	parts {
	var	$parent = null;
	var	$stream_infostr = "(none)";
	var	$layoutcontrol = 0;
	function	parts(&$parent) {
		$this->parent =& $parent;
	}
	function	getcharwidth() {
		return -1;
	}
	function	getcharheight() {
		return -1;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$right = $left;
		$bottom = $top;
	}
	function	printinfo() {
		print "(parts before $this->stream_infostr)<BR>\n";
	}
	function	reflowthis(&$layoutroot, &$createbranch, &$target) {
		if (($createbranch)) {
			$target->createbranch($this, 1);
			$createbranch = 0;
		}
		$layoutroot->add($this);
	}
	function	setpartsinfo(&$layout) {
		$layout->setcurrentparts($this);
	}
	function	iswordwrap() {
		return -1;		# never join
			# -2:ignore this parts
			# &1:join to prev parts
			# &2:join to next parts
	}
}

class	tadfactory_charparts extends tadfactory_parts {
	var	$factoryname = "char";
	function	tadfactory_charparts(&$scope, $dig = 0) {
		parent::tadfactory_parts($scope);
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("2121", $target);
	}
	function	&create($key, $type, &$stream) {
		$newparts =& new charparts($this->scope, $type, $this->scope->textattrlist->get("lang"));
		$this->scope->partslist->add($newparts);
		return $this->scope->factoryfinder;
	}
}

class	charparts extends parts {
	var	$code;
	function	charparts(&$parent, $code, $lang = 0x21) {
		parent::parts($parent);
		$this->code = ($code & 0xffff) | ($lang << 16);
	}
	function	printinfo() {
		printf("charparts(%08x)<BR>\n", $this->code);
	}
	function	getcharwidth() {
		return $this->parent->textattrlist->getcharwidth();
	}
	function	getcharheight() {
		return $this->parent->textattrlist->getcharheight();
	}
	function	getwordwraptype() {
		return $this->parent->textattrlist->get("wordwraptype");
	}
	function	iswordwrap() {
		$val = $this->parent->textattrlist->get("rubi");
		if (($this->parent->textattrlist->get("wordwraptype") & 0xf) == 0)
			return $val;
		if (in_array($this->code, $this->parent->textattrlist->get("wordwrapt")))
			$val |= 1;
		if (in_array($this->code, $this->parent->textattrlist->get("wordwrapb")))
			$val |= 2;
		return $val;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$x = $left;
		$y = $top;
		if ($this->parent->textattrlist->get("subscript") == 0)
			$subscript = 0;
		else if ($this->parent->textattrlist->get("chardir") != 2)
			$subscript = 1;
		else
			$subscript = 2;
		$w = $this->parent->textattrlist->getcharwidth(($subscript == 2)? 0 : 1);
		$h = $this->parent->textattrlist->getcharheight(($subscript == 1)? 0 : 1);
# print "charparts left({$left}) top({$top}) right({$right}) bottom({$bottom}) draw($draw) w({$w}) h({$h})<BR>\n";
		$attr = $this->parent->textattrlist->getcharattr();
		$genv->setfont($this->parent->textattrlist->get("fontname"), $this->parent->textattrlist->get("charclass"), $attr);
		$w_real = $w;
#		$h_real = $w;
		$h_real = $h;
		if (($attr & 0x8000))
			$w_real = $genv->textwidth($w, $h, $this->code);
		$top = $y - $h_real + 1;
		$bottom = $y + 1;		# baseline
		$left = $x - ($w_real / 2);	# virtical baseline
		$right = $left + $w_real;
# print "---- left({$left}) top({$top}) right({$right}) bottom({$bottom}) draw($draw) w({$w}) h({$h})<BR>\n";
		if ($draw == 0)
			return;
		$x = $left;
		$y = $top;
		switch ($subscript) {
			case	1:
				$size = $this->parent->textattrlist->getcharheight();
				if (($this->parent->textattrlist->get("subscripttype") & 1) == 0)
					$y += $h - $size;
				$h = $size;
				break;
			case	2:
				$size = $this->parent->textattrlist->getcharwidth();
				if (($this->parent->textattrlist->get("subscripttype") & 1))
					$x += $w - $size;
				$w = $size;
				break;
		}
		$this->parent->scale->convert_point($x, $y);
		$this->parent->scale->convert_size($w, $h);
		$this->parent->scale->convert_size($w_real, $h_real);
		
		$genv->setcolor($this->parent->textattrlist->get("charcolor"));
		$genv->text(int($x), int($y), int($w), int($h), $this->code);
		
		if ($this->parent->textattrlist->get("underline") >= 0) {
			$genv->setlattr(1, array(0xff));
			$genv->polyline(2, array($x, $y + $h - 1, $x + $w - 1, $y + $h - 1));
		}
		return;
	}
}

class	tadfactory_controlcharparts extends tadfactory_parts {
	var	$factoryname = "controlchar";
	function	tadfactory_controlcharparts(&$scope, $dig = 0) {
		parent::tadfactory_parts($scope);
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("0009", $target);
		$this->scope->factoryfinder->add("000a", $target);
		$this->scope->factoryfinder->add("000b", $target);
		$this->scope->factoryfinder->add("000c", $target);
		$this->scope->factoryfinder->add("000d", $target);
	}
	function	&create($key, $type, &$stream) {
		switch ($type) {
			case	9:
				return $this->scope->factoryfinder->read("000d0000", 9, $stream);
			case	0xa:
				return $this->scope->factoryfinder->read("000d0000", 0xc, $stream);
			case	0xb:
				return $this->scope->factoryfinder->read("000d0000", 0xd, $stream);
			case	0xc:
				return $this->scope->factoryfinder->read("000d0000", 0xf, $stream);
			case	0xd:
				return $this->scope->factoryfinder->read("000d0000", 0xa, $stream);
		}
		return $this->scope->factoryfinder;
	}
}

class	tadfactory_textobjectparts extends tadfactory_parts {
	var	$factoryname = "textobject";
	function	tadfactory_textobjectparts(&$scope, $dig = 0) {
		parent::tadfactory_parts($scope);
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("ffa00800", $target);
		$this->scope->factoryfinder->add("ffa40600", $target);
		$this->scope->factoryfinder->add("ffad", $target);
	}
	function	&create($key, $type, &$stream) {
		switch ($key) {
			case	"ffa00800":
				$newparts =& new layoutcontrolparts($this->scope, $type);
				$this->scope->partslist->add($newparts);
				return $this->scope->factoryfinder->read("000d0000", 0xa, $stream);
			case	"ffa40600":
				$newparts =& new rubiparts($this->scope, $type, getcodelist($stream));
				$this->scope->partslist->add($newparts);
				return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 2)
			return $this->scope->factoryfinder;
		$subtype = $stream->uh();
		switch (($type << 8)|($subtype >> 8)) {
			case	0xffad00:
				if ($stream->remain() < 2)
					return $this->scope->factoryfinder;
				$newparts =& new variableparts($this->scope, $stream->uh());
				$this->scope->partslist->add($newparts);
				return $this->scope->factoryfinder;
		}
		return $this->scope->factoryfinder;
	}
}

class	rubiparts extends parts {
	var	$type;
	var	$codelist;
	function	rubiparts(&$parent, $type, $codelist) {
		parent::parts($parent);
		$this->type = $type;
		$this->codelist = $codelist;
	}
	function	printinfo() {
		print "rubiparts(";
		foreach ($this->codelist as $val)
			printf(" %08x", $val);
		print " )<BR>\n";
	}
	function	iswordwrap() {
		return 2;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$x = $right = $left;
		$y = $bottom = $top;
		if ($draw == 0)
			return;
		$w = $this->parent->textattrlist->getcharwidth() / 2;
		$h = $this->parent->textattrlist->getcharheight() / 2;
		$attr = $this->parent->textattrlist->getcharattr();
		$genv->setfont($this->parent->textattrlist->get("fontname"), $this->parent->textattrlist->get("charclass"), $attr);
		$genv->setcolor($this->parent->textattrlist->get("charcolor"));
		$this->parent->scale->convert_point($x, $y);
		$this->parent->scale->convert_size($w, $h);
		switch ($chardir = $this->parent->textattrlist->get("chardir")) {
			case	1:
				$x -= $w;
			default:
			case	0:
				if (($this->type & 1) == 0) 
					$y -= $h * 3;
				break;
			case	2:
				if (($this->type & 1) == 0) 
					$x += $w;
				else
					$x -= $w * 2;
				break;
		}
		$feed = $w;
		foreach ($this->codelist as $code) {
			$genv->text(int($x), int($y), int($w), int($h), $code);
			if (($attr & 0x8000))
				$feed = $genv->textwidth($w, $h, $code);
			switch ($chardir) {
				default:
				case	0:
					$x += $feed;
					break;
				case	1:
					$x -= $feed;
					break;
				case	2:
					$y += $h;
					break;
			}
		}
		return;
	}
}

class	variableparts extends parts {
	var	$type;
	function	variableparts(&$parent, $type) {
		parent::parts($parent);
		$this->type = $type;
	}
	function	printinfo() {
		printf("variableparts(".$this->type.")<BR>\n");
	}
	function	createchar(&$layout, $code, $lang = 0x21) {
		$newparts =& new charparts($this->parent, $code, $lang);
		$layout->add($newparts);
	}
	function	createcharascii(&$layout, $string) {
		$euc = mb_convert_kana($string, "AS", "EUC-JP");
		for ($i=0; $i<strlen($euc); $i+=2) {
			$code = (ord(substr($euc, $i, 1)) & 0x7f) << 8;
			$code |= ord(substr($euc, $i + 1, 1)) & 0x7f;
			$this->createchar($layout, $code);
		}
	}
	function	toroman($num) {
		# 1-1000:IVXLCDM
		$string = "";
		$array = array("", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX");
		while ($num >= 1000) {
			$string .= "m";
			$num -= 1000;
		}
		$string .= str_replace(array("I", "V", "X"), array("c", "d", "m"), $array[floor($num / 100) % 10]);
		$string .= str_replace(array("I", "V", "X"), array("x", "l", "c"), $array[floor($num / 10) % 10]);
		$string .= str_replace(array("I", "V", "X"), array("i", "v", "x"), $array[$num % 10]);
		return $string;
	}
	function	reflowthis(&$layoutroot, &$createbranch, &$target) {
		global	$global;
		
		if (($createbranch)) {
			$target->createbranch($this, 1);
			$createbranch = 0;
		}
		switch ($this->type) {
			case	0:		# sample.tad
				$this->createcharascii($layoutroot, $global->file->filename);
				break;
			case	100:		# 07
				$this->createcharascii($layoutroot, date("y"));
				break;
			case	101:		# H19
				$this->createcharascii($layoutroot, "H".(date("Y") - 2007 + 19));
				break;
			case	110:		# 5
				$this->createcharascii($layoutroot, date("n"));
				break;
			case	111:		# 05
				$this->createcharascii($layoutroot, date("m"));
				break;
			case	112:		# may
				$this->createcharascii($layoutroot, strtolower(date("M")));
				break;
			case	113:		# MAY
				$this->createcharascii($layoutroot, strtoupper(date("M")));
				break;
			case	120:		# 6
				$this->createcharascii($layoutroot, date("j"));
				break;
			case	121:		# 06
				$this->createcharascii($layoutroot, date("d"));
				break;
			case	200:		# 1
				$val = $this->parent->pageattrlist->get("pagenumber");
				$this->createcharascii($layoutroot, $val);
				break;
			case	201:		# i
				$val = $this->parent->pageattrlist->get("pagenumber");
				$this->createcharascii($layoutroot, $this->toroman($val));
				break;
			case	202:		# I
				$val = $this->parent->pageattrlist->get("pagenumber");
				$this->createcharascii($layoutroot, strtoupper($this->toroman($val)));
				break;
			case	250:		# 5
				$val = $this->parent->pageattrlist->get("pagetotal");
				$this->createcharascii($layoutroot, $val);
				break;
			case	251:		# v
				$val = $this->parent->pageattrlist->get("pagetotal");
				$this->createcharascii($layoutroot, $this->toroman($val));
				break;
			case	252:		# V
				$val = $this->parent->pageattrlist->get("pagetotal");
				$this->createcharascii($layoutroot, strtoupper($this->toroman($val)));
				break;
		}
	}
}

class	layoutcontrolparts extends parts {
	var	$value;
	function	layoutcontrolparts(&$parent, $type) {
		parent::parts($parent);
		$this->layoutcontrol = $type;
	}
	function	printinfo() {
		printf("layoutcontrolparts(".$this->layoutcontrol.")<BR>\n");
	}
	function	iswordwrap() {
		return -2;
	}
}

class	tadfactory_imageparts extends tadfactory_parts {
	var	$factoryname = "image";
	function	tadfactory_imageparts(&$scope, $dig = 0) {
		parent::tadfactory_parts($scope);
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("ffe5", $target);
	}
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$newparts =& new imageparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		$newparts->view[0] = $stream->h();
		$newparts->view[1] = $stream->h();
		$newparts->view[2] = $stream->h();
		$newparts->view[3] = $stream->h();
		$left0 = $stream->h();
		$top0 = $stream->h();
		$right0 = $stream->h();
		$bottom0 = $stream->h();
		$newparts->scale->setdraw($left0, $top0, $right0, $bottom0);
		$stream->uh();	# DPI
		$stream->uh();	# DPI
		$stream->h();	# slope
		$colorattr = $stream->uh();
		$colorinfo[0] = $stream->uh();
		$colorinfo[1] = $stream->uh();
		$colorinfo[2] = $stream->uh();
		$colorinfo[3] = $stream->uh();
		if ($colorattr & 8) {	# color-map
			$colormap = array(0);
			for ($i=0; $i<256; $i++)
				$colormap[$i] = $this->scope->figattrlist->get(sprintf("colormap-%04x", $i));
			if (($count = $colorinfo[0] / 4) > 0) {
				$colormapstream =& $stream->clone_stream();
				$colormapstream->offset(($colorinfo[2] << 16) | $colorinfo[3], SEEK_SET);
				for ($i=0; $i<$count; $i++)
					$colormap[$i] = $colormapstream->uw() & 0xffffff;
			}
		} else {		# direct-color
			$colormap = null;
			switch ($colorattr & 7) {
				case	0:	# B/W
					$shift_r = $shift_g = $shift_b = $colorinfo[0] >> 8;
					$mask_r = $mask_g = $mask_b = 0x0fffffff >> (28 - ($colorinfo[0] & 0xff));
					$mul_r = $mul_g = $mul_b = floor(255 / $mask_r);
					break;
				case	1:	# RGB
					$shift_r = $colorinfo[0] >> 8;
					$mask_r = 0x0fffffff >> (28 - ($colorinfo[0] & 0xff));
					$mul_r = floor(255 / $mask_r);
					$shift_g = $colorinfo[1] >> 8;
					$mask_g = 0x0fffffff >> (28 - ($colorinfo[1] & 0xff));
					$mul_g = floor(255 / $mask_g);
					$shift_b = $colorinfo[2] >> 8;
					$mask_b = 0x0fffffff >> (28 - ($colorinfo[2] & 0xff));
					$mul_b = floor(255 / $mask_b);
					break;
				default:
					return $this->scope->factoryfinder;
			}
			
			$global->log->info(sprintf("R shift(%d) mask(%08x) mul(%d)", $shift_r, $mask_r, $mul_r));
			$global->log->info(sprintf("G shift(%d) mask(%08x) mul(%d)", $shift_g, $mask_g, $mul_g));
			$global->log->info(sprintf("B shift(%d) mask(%08x) mul(%d)", $shift_b, $mask_b, $mul_b));
		}
		$stream->uw();	# extlen
		$stream->uw();	# extoff
		$maskstream = null;
		if (($maskoffset = $stream->uw()) > 0) {
			$maskstream =& $stream->clone_stream();
			$maskstream->offset($maskoffset, SEEK_SET);
		}
		$compac = $stream->uh();
		$planes = $stream->uh();
		$pixbits = $stream->uh();
		$pixeldatawidth = min($pixbits >> 8, 32);
		$pixelcount = min($pixbits & 0xff, 28);
		$pixelmask = 0x0fffffff >> (28 - $pixelcount);
		$rowbytes = $stream->uh();
		$newparts->bounds[0] = $stream->h();
		$newparts->bounds[1] = $stream->h();
		$newparts->bounds[2] = $stream->h();
		$newparts->bounds[3] = $stream->h();
		$width = $newparts->bounds[2] - $newparts->bounds[0];
		$height = $newparts->bounds[3] - $newparts->bounds[1];
		
		$global->log->info(sprintf("planes(%d) pixbits(%04x) pixeldatawidth(%d) pixelcount(%d) pixelmask(%08x)", $planes, $pixbits, $pixeldatawidth, $pixelcount, $pixelmask));
		$global->log->info(sprintf(" rowbytes(%d) width(%d) height(%d)", $rowbytes, $width, $height));
		
		for ($i=0; $i<$planes; $i++) {
			if (($offset = $stream->uw()) == 0) {
				$planestream[$i] = null;
				continue;
			}
			$planestream[$i] =& $stream->clone_stream();
			$planestream[$i]->offset($offset, SEEK_SET);
			$global->log->info(sprintf("offset(%d)", $offset));
		}
		$array = array(array(0));
		for ($y=0; $y<$height; $y++) {
			$array_h = array(0);
			for ($i=0; $i<$planes; $i++) {
				$planedataarray[$i] = array_fill(0, $rowbytes, 0);
				if ($planestream[$i] === null)
					continue;
				for ($j=0; $j<$rowbytes; $j++)
					$planedataarray[$i][$j] = $planestream[$i]->ub();
			}
			for ($x=0; $x<$width; $x++) {
				$pixel = 0;
				for ($i=$planes-1; $i>=0; $i--) {
					$pixel <<= $pixelcount;
					if ($pixeldatawidth > 8) {
						$pixel |= array_shift($planedataarray[$i])  & $pixelmask;
						$pixel |= (array_shift($planedataarray[$i]) << 8) & $pixelmask;
					}
					if ($pixeldatawidth > 16) {
						$pixel |= (array_shift($planedataarray[$i]) << 16) & $pixelmask;
						$pixel |= (array_shift($planedataarray[$i]) << 24) & $pixelmask;
					}
					if ($pixeldatawidth > 8)
						continue;
					$pixel |= (($planedataarray[$i][0] <<= $pixeldatawidth) >> 8) & $pixelmask;
					if ((($x + 1) * $pixeldatawidth) % 8 == 0)
						array_shift($planedataarray[$i]);
				}
				if (($x % 16))
					;
				else if ($maskstream !== null) {
					$maskdata  = $maskstream->uh();
					$maskdata = ($maskdata >> 8)|(($maskdata << 8) & 0xff00);
				} else
					$maskdata = 0xffff;
				if ((($maskdata <<= 1) & 0x10000) == 0) {
					$array_h[$x] = -1;
					continue;
				}
				if ($colormap !== null) {
					$array_h[$x] = @$colormap[$pixel];
					continue;
				}
				$rgb = ((($pixel >> $shift_r) & $mask_r) * $mul_r) << 16;
				$rgb |= ((($pixel >> $shift_g) & $mask_g) * $mul_g) << 8;
				$rgb |= (($pixel >> $shift_b) & $mask_b) * $mul_b;
				$array_h[$x] = $rgb;
			}
			$array[$y] = $array_h;
		}
		$newparts->bitmaparray = $array;
		$this->scope->partslist->add($newparts);
		return $this->scope->factoryfinder;
	}
}

class	tadfactory_imageparts_intext extends tadfactory_imageparts {
	var	$factoryname = "image in text";
	function	&create($key, $type, &$stream) {
		$ret =& parent::create($key, $type, $stream);
		
		$newparts =& $this->scope->partslist->get_last();
#		$newparts->stream_infostr = $stream->getinfostr();
#		$left = $top = $right = $bottom = 0;
#		$newparts->scale->getview($left, $top, $right, $bottom);
		$right = $newparts->view[2] - $newparts->view[0];	# width
		$top = $newparts->view[1] - ($newparts->view[3] - 1);
		$bottom = 1;		# baseline
		if (($right <= 0)||($top >= 1)) {
			$left0 = $top0 = $right0 = $bottom0 = 0;
			$newparts->scale->getdraw($left0, $top0, $right0, $bottom0);
			$top = -$this->scope->textattrlist->getcharheight() + 1;
			$right = $this->scope->textattrlist->getcharwidth() * ($right0 - $left0) / ($bottom0 - $top0);
		}
		$right += ($left = -($right / 2));	# virtical baseline
		$newparts->view = array($left, $top, $right, $bottom);
		
		return $ret;
	}
}

class	imageparts extends parts {
	var	$scale;
	var	$view = array(0, 0, 0, 0);
	var	$bounds = array(0, 0, 0, 0);
	var	$bitmaparray;
	function	imageparts(&$parent) {
		parent::parts($parent);
		$this->scale =& new scale();
	}
	function	printinfo() {
		printf("imageparts view(%d, %d, %d, %d) before %s<BR>\n", $this->view[0], $this->view[1], $this->view[2], $this->view[3], $this->stream_infostr);
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$x = $left;
		$y = $top;
		$l = $left = $this->view[0] + $x;
		$t = $top = $this->view[1] + $y;
		$r = $right = $this->view[2] + $x;
		$b = $bottom = $this->view[3] + $y;
		if ($draw == 0)
			return;
		
		$this->parent->scale->convert_point($l, $t);
		$this->parent->scale->convert_point($r, $b);
		$view = array(int($l), int($t), int($r), int($b));
		$this->scale->getdraw($left0, $top0, $right0, $bottom0);
		$draw = array(int($left0 - $this->bounds[0]), int($top0 - $this->bounds[1]), int($right0 - $this->bounds[0]), int($bottom0 - $this->bounds[1]));
		$genv->bitmap($this->bitmaparray, $view, $draw);
	}
}

class	tadfactory_vobjparts extends tadfactory_parts {
	var	$factoryname = "vobj";
	function	tadfactory_vobjparts(&$scope, $dig = 0) {
		parent::tadfactory_parts($scope);
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("ffe6", $target);
	}
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$newparts =& new vobjparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		$newparts->view = array($left, $top, $right, $bottom);
		
		$newparts->height = $stream->uh();	# height (if opened)
		$newparts->fontsize = $stream->uh();	# fontsize
		$newparts->frcolor = $stream->uw();	# frame color
		$newparts->fgcolor = $stream->uw();	# text color
		$newparts->bgcolor = $stream->uw();	# background color
		$stream->uw();	# background color (if opened)
		
		$newparts->linkinfo =& $stream->getlinkinfo();
		
		$this->scope->partslist->add($newparts);
		return $this->scope->factoryfinder;
	}
}

class	tadfactory_vobjparts_intext extends tadfactory_vobjparts {
	var	$factoryname = "vobj in text";
	function	&create($key, $type, &$stream) {
		$ret =& parent::create($key, $type, $stream);
		
		$newparts =& $this->scope->partslist->get_last();
#		$newparts->stream_infostr = $stream->getinfostr();
		$right = $newparts->view[2] - $newparts->view[0];	# width
		$top = $newparts->view[1] - ($newparts->view[3] - 1);
		$bottom = 1;		# baseline
		
		$right += ($left = -($right / 2));	# virtical baseline
		$newparts->view = array($left, $top, $right, $bottom);
		
		return $ret;
	}
}

class	vobjparts extends parts {
	var	$view = array(0, 0, 0, 0);
	var	$height = 0;
	var	$fontsize = 0;
	var	$frcolor = 0;
	var	$fgcolor = 0;
	var	$bgcolor = 0xffffff;
	var	$linkinfo = null;
#	function	vobjparts(&$parent) {
#		parent::parts($parent);
#	}
	function	printinfo() {
		printf("vobjparts view(%d, %d, %d, %d) before %s<BR>\n", $this->view[0], $this->view[1], $this->view[2], $this->view[3], $this->stream_infostr);
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$x = $left;
		$y = $top;
		$l = $left = $this->view[0] + $x;
		$t = $top = $this->view[1] + $y;
		$r = $right = $this->view[2] + $x;
		$b = $bottom = $this->view[3] + $y;
		if ($draw == 0)
			return;
		
		$this->parent->scale->convert_point($l, $t);
		$this->parent->scale->convert_point($r, $b);
		$linewidth = 1;
		if ($this->linkinfo !== null)
			$linewidth = ($this->linkinfo->attr & 0x80) ? 0 : 1;
		
		$this->parent->scale->convert_length($linewidth);
		$pointlist = array(
			$l, $t, 
			$r - $linewidth, $t, 
			$r - $linewidth, $b - $linewidth, 
			$l, $b - $linewidth, 
			$l, $t
		);
		
		$genv->setjumpto($l, $t, $r, $b, $this->linkinfo->fileinfo->jumpto);
		
		$genv->setcolor($this->bgcolor);
		$genv->polygon(4, $pointlist);
		
		if ($linewidth > 0) {
			$genv->setcolor($this->frcolor);
			$genv->setlattr($linewidth, array(0xff));
			$genv->polyline(5, $pointlist);
		}
		
		$w = $h = $this->parent->textattrlist->chsztodot($this->fontsize);
		if ($h == 0)
			$w = $h = 16;
		$this->parent->scale->convert_size($w, $h);
		$feed = $w;
		
		$genv->setfont();
		$genv->setcolor($this->fgcolor & 0xffffff);
		$x = $l + $linewidth * 2;
		$y = $t + $linewidth * 2;
		
		$stream =& new array_bytestream();
		$stream->append($this->linkinfo->fileinfo->name);
		foreach (getcodelist($stream) as $code) {
			if ($code == 0)
{
#printf("l(%d) t(%d) r(%d) b(%d) w(%d) h(%d) x(%d) y(%d) feed(%d)<BR>\n", $l, $t, $r, $b, $w, $h, $x, $y, $feed);
#die("***");
				break;
}
#			if (($attr & 0x8000))
#				$feed = $genv->textwidth($w, $h, $code);
			if ($x + $feed > $r)
				return;
			$genv->text(int($x), int($y), int($w), int($h), $code);
			switch (0) {
				default:
				case	0:
					$x += $feed;
					break;
				case	1:
					$x -= $feed;
					break;
				case	2:
					$y += $h;
					break;
			}
		}
	}
}

class	tadfactory_figparts extends tadfactory_parts {
	var	$factoryname = "fig";
	var	$cx;
	var	$cy;
	var	$rh;
	var	$rv;
	function	tadfactory_figparts(&$scope) {
		parent::tadfactory_parts($scope);
		$this->scope->factoryfinder->add("ffb0", $this);
	}
	function	readradian(&$stream) {
		$x = ($stream->h() - $this->cx) * $this->rv;
		$y = ($stream->h() - $this->cy) * $this->rh;
		if ($y < $x) {
			if ($y < -$x) {	# top
				if ($y >= -2)
					return 0;
				return -atan($x / $y) + pi() * 1.5;
			}
			if ($x <= 2)
				return 0;
			if ($y < 0)	# right-top
				return atan($y / $x) + pi() * 2;
					# right-bottom
			return atan($y / $x);
		}
		if ($y < -$x) {		# left
			if ($x >= -2)
				return 0;
			return atan($y / $x) + pi();
		}
					# bottom
		if ($y <= 2)
			return 0;
		return -atan($x / $y) + pi() / 2;
	}
	function	createarrow($sx, $sy, $ex, $ey, $fgpatid, $linewidth) {
		$newparts =& new lineparts($this->scope);
		if ($linewidth <= 0)
			return;
		if ($fgpatid <= 0)
			return;
		$vx = $ex - $sx;
		$vy = $ey - $sy;
		$length = sqrt($vx * $vx + $vy * $vy) / (4.0 + $linewidth * 3);
		if ($length <= 0.5)
			return;
		$sin = sin(24 * pi() / 180) / $length;
		$cos = cos(24 * pi() / 180) / $length;
		$newparts =& new lineparts($this->scope);
		$newparts->setfgpat($fgpatid, $linewidth);
		$ex = $vx * $cos - $vy * $sin + $sx;
		$ey = $vx * $sin + $vy * $cos + $sy;
		$newparts->addpoint($ex, $ey);
		$newparts->addpoint($sx, $sy);
		$ex = $vx * $cos + $vy * $sin + $sx;
		$ey = -$vx * $sin + $vy * $cos + $sy;
		$newparts->addpoint($ex, $ey);
		$this->scope->partslist->add($newparts);
	}
	function	setup_oval($left, $top, $right, $bottom) {
		$this->cx = ($left + $right) / 2.0;
		$this->cy = ($top + $bottom) / 2.0;
		$this->rh = ($right - $left) / 2.0;
		$this->rv = ($bottom - $top) / 2.0;
	}
	function	addpoint_onoval(&$parts, $radian) {
		$x = $this->cx + cos($radian) * $this->rh;
		$y = $this->cy + sin($radian) * $this->rv;
		$parts->addpoint($x, $y);
	}
	function	addvector_onoval(&$parts, $sradian, $eradian) {
		$x = $this->cx + cos($sradian) * $this->rh;
		$y = $this->cy + sin($sradian) * $this->rv;
		$sin = sin(($eradian - $sradian) / 2);
		$cos = cos(($eradian - $sradian) / 2);
		$mul = (1 - $cos) / $sin * 4 / 3;
		$x += cos($sradian + pi() / 2) * $this->rh * $mul;
		$y += sin($sradian + pi() / 2) * $this->rv * $mul;
		$parts->addpoint($x, $y);
	}
	function	create_rect($subtype, &$stream) {
		$newparts =& new lineparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 16)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$bgpatid = $stream->uh();
		$angle = $stream->h();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		if ($linewidth > $right - $left)
			$linewidth = $right - $left;
		if ($linewidth > $bottom - $top)
			$linewidth = $bottom - $top;
		if (($linewidth > 0)&&($fgpatid > 0))
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		if ($bgpatid > 0)
			$newparts->setbgpat($bgpatid);
		if ($linewidth < 1)
			$linewidth = 1;
		$right -= $linewidth;
		$bottom -= $linewidth;
		$newparts->addpoint($left, $top);
		$newparts->addpoint($right, $top);
		$newparts->addpoint($right, $bottom);
		$newparts->addpoint($left, $bottom);
		$newparts->addpoint($left, $top);
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_roundedrect($subtype, &$stream) {
		$newparts =& new bezierparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 20)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$bgpatid = $stream->uh();
		$angle = $stream->h();
		$roundh = $stream->uh();
		$roundv = $stream->uh();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		if ($linewidth > $right - $left)
			$linewidth = $right - $left;
		if ($linewidth > $bottom - $top)
			$linewidth = $bottom - $top;
		if (($linewidth > 0)&&($fgpatid > 0))
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		if ($bgpatid > 0)
			$newparts->setbgpat($bgpatid);
		if ($linewidth < 1)
			$linewidth = 1;
		$right -= $linewidth;
		$bottom -= $linewidth;
		if ($roundh > $right - $left)
			$roundh = $right - $left;
		if ($roundv > $bottom - $top)
			$roundv = $bottom - $top;

		$rh = $roundh / 2;
		$rv = $roundv / 2;
		$vx = $roundh * (sqrt(2.0) - 1) * 2 / 3 - $rh;
		$vy = $roundv * (sqrt(2.0) - 1) * 2 / 3 - $rv;
		$newparts->addpoint($right - $rh, $top);	# right-top round
		$newparts->addpoint($right + $vx, $top);
		$newparts->addpoint($right, $top - $vy);
		$newparts->addpoint($right, $top + $rv);	# right line
		$newparts->addpoint($right, $top + $rv);
		$newparts->addpoint($right, $bottom - $rv);
		$newparts->addpoint($right, $bottom - $rv);	# right-bottom round
		$newparts->addpoint($right, $bottom + $vy);
		$newparts->addpoint($right + $vx, $bottom);
		$newparts->addpoint($right - $rh, $bottom);	# bottom line
		$newparts->addpoint($right - $rh, $bottom);
		$newparts->addpoint($left + $rh, $bottom);
		$newparts->addpoint($left + $rh, $bottom);	# left-bottom round
		$newparts->addpoint($left - $vx, $bottom);
		$newparts->addpoint($left, $bottom + $vy);
		$newparts->addpoint($left, $bottom - $rv);	# left line
		$newparts->addpoint($left, $bottom - $rv);
		$newparts->addpoint($left, $top + $rv);
		$newparts->addpoint($left, $top + $rv);		# left-top round
		$newparts->addpoint($left, $top - $vy);
		$newparts->addpoint($left - $vx, $top);
		$newparts->addpoint($left + $rh, $top);		# top line
		$newparts->addpoint($left + $rh, $top);
		$newparts->addpoint($right - $rh, $top);
		$newparts->addpoint($right - $rh, $top);

		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_oval($subtype, &$stream) {
		$newparts =& new bezierparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 16)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$bgpatid = $stream->uh();
		$angle = $stream->h();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		if ($linewidth > $right - $left)
			$linewidth = $right - $left;
		if ($linewidth > $bottom - $top)
			$linewidth = $bottom - $top;
		if (($linewidth > 0)&&($fgpatid > 0))
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		if ($bgpatid > 0)
			$newparts->setbgpat($bgpatid);
		if ($linewidth < 1)
			$linewidth = 1;
		$right -= $linewidth;
		$bottom -= $linewidth;
		$this->setup_oval($left, $top, $right, $bottom);
		$this->addpoint_onoval($newparts, $s = 0);
		for ($i=1; $i<=4; $i++) {
			$e = pi() * $i / 2;
			$this->addvector_onoval($newparts, $s, $e);
			$this->addvector_onoval($newparts, $e, $s);
			$this->addpoint_onoval($newparts, $s = $e);
		}
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_sector($subtype, &$stream) {
		$newparts =& new bezierparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 24)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$bgpatid = $stream->uh();
		$angle = $stream->h();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		if ($linewidth > $right - $left)
			$linewidth = $right - $left;
		if ($linewidth > $bottom - $top)
			$linewidth = $bottom - $top;
		if (($linewidth > 0)&&($fgpatid > 0))
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		if ($bgpatid > 0)
			$newparts->setbgpat($bgpatid);
		if ($linewidth < 1)
			$linewidth = 1;
		$right -= (0)? 1 : $linewidth;
		$bottom -= (0)? 1 : $linewidth;
		$this->setup_oval($left, $top, $right, $bottom);
		$sradian = $this->readradian($stream);
		$eradian = $this->readradian($stream);
		if ($eradian <= $sradian)
			$eradian += pi() * 2;
		
		$div = ceil(($eradian - $sradian) * 2 / pi());	# max 90degree
		$newparts->addpoint($this->cx, $this->cy);
		$newparts->addpoint($this->cx, $this->cy);
		$this->addpoint_onoval($newparts, $s = $sradian);
		$this->addpoint_onoval($newparts, $s);
		for ($i=1; $i<=$div; $i++) {
			$e = ($sradian * ($div - $i) + $eradian * $i) / $div;
			$this->addvector_onoval($newparts, $s, $e);
			$this->addvector_onoval($newparts, $e, $s);
			$this->addpoint_onoval($newparts, $s = $e);
		}
		$this->addpoint_onoval($newparts, $s);
		$newparts->addpoint($this->cx, $this->cy);
		$newparts->addpoint($this->cx, $this->cy);
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_chord($subtype, &$stream) {
		$newparts =& new bezierparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 24)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$bgpatid = $stream->uh();
		$angle = $stream->h();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		if ($linewidth > $right - $left)
			$linewidth = $right - $left;
		if ($linewidth > $bottom - $top)
			$linewidth = $bottom - $top;
		if (($linewidth > 0)&&($fgpatid > 0))
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		if ($bgpatid > 0)
			$newparts->setbgpat($bgpatid);
		if ($linewidth < 1)
			$linewidth = 1;
		$right -= (0)? 1 : $linewidth;
		$bottom -= (0)? 1 : $linewidth;
		$this->setup_oval($left, $top, $right, $bottom);
		$sradian = $this->readradian($stream);
		$eradian = $this->readradian($stream);
		if ($eradian <= $sradian)
			$eradian += pi() * 2;
		
		$div = ceil(($eradian - $sradian) * 2 / pi());	# max 90degree
		$this->addpoint_onoval($newparts, $s = $sradian);
		for ($i=1; $i<=$div; $i++) {
			$e = ($sradian * ($div - $i) + $eradian * $i) / $div;
			$this->addvector_onoval($newparts, $s, $e);
			$this->addvector_onoval($newparts, $e, $s);
			$this->addpoint_onoval($newparts, $s = $e);
		}
		$this->addpoint_onoval($newparts, $s);
		$this->addpoint_onoval($newparts, $s = $sradian);
		$this->addpoint_onoval($newparts, $s);
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_polygon($subtype, &$stream) {
		if ($stream->remain() < 10)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$bgpatid = $stream->uh();
		$round = $stream->uh();
		$count = $stream->uh();
		if ($stream->remain() < $count * 4)
			return;
		if ($round == 0) {
			$newparts =& new lineparts($this->scope);
			$newparts->stream_infostr = $stream->getinfostr();
			if (($linewidth > 0)&&($fgpatid > 0))
				$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
			if ($bgpatid > 0)
				$newparts->setbgpat($bgpatid);
			$sx = $stream->h();
			$sy = $stream->h();
			$newparts->addpoint($sx, $sy);
			while ($count-- > 1) {
				$x = $stream->h();
				$y = $stream->h();
				$newparts->addpoint($x, $y);
			}
			$newparts->addpoint($sx, $sy);
			$this->scope->partslist->add($newparts);
			$this->scope->figattrlist->cleartempattr();
			return;
		}
		$newparts =& new bezierparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if (($linewidth > 0)&&($fgpatid > 0))
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		if ($bgpatid > 0)
			$newparts->setbgpat($bgpatid);
		
		for ($i=0; $i<$count; $i++) {
			$x[$i] = $stream->h();
			$y[$i] = $stream->h();
			$r[$i] = 0;
			$sin[$i] = 0.5;
			$cos[$i] = 0.5;
			$h[$i] = 0;
		}
		for ($i=0; $i<$count; $i++) {
			$next = ($i + 1) % $count;
			$l[$i] = sqrt(pow($x[$next] - $x[$i], 2) + pow($y[$next] - $y[$i], 2));
		}
		for ($i=0; $i<$count; $i++) {
			$back = ($i + $count - 1) % $count;
			$next = ($i + 1) % $count;
			if (($r1 = min($round / 2, $l[$back], $l[$i])) < 1)
				continue;
			$x0 = ($x[$back] - $x[$i]) / $l[$back];
			$y0 = ($y[$back] - $y[$i]) / $l[$back];
			$x2 = ($x[$next] - $x[$i]) / $l[$i];
			$y2 = ($y[$next] - $y[$i]) / $l[$i];
			$x1 = $x0 + $x2;
			$y1 = $y0 + $y2;
			if (($l1 = sqrt($x1 * $x1 + $y1 * $y1)) < 0.1)
				continue;
			$x1 /= $l1;
			$y1 /= $l1;
			$cos1 = 0;
			if (($sin1 = abs($x0 * $y1 - $x1 * $y0)) <= 0.01)
				continue;
			if (($cos1 = sqrt(1 - $sin1 * $sin1)) <= 0.01)
				continue;
			$r[$i] = $r1;
			$sin[$i] = $sin1;
			$cos[$i] = $cos1;
			$h[$i] = $r1 * $cos1 / $sin1;
		}
		for (;;) {
			$over_h = 0;
			$over_index = 0;
			for ($i=0; $i<$count; $i++) {
				$next = ($i + 1) % $count;
				$h1 = $h[$i] + $h[$next] - $l[$i];
				if ($over_h < $h1) {
					$over_h = $h1;
					$over_index = $i;
				}
			}
			if ($over_h <= 0.1)
				break;
			$i = $over_index;
			$next = ($i + 1) % $count;
			if ($h[$i] < $l[$i] / 2) {
				$h[$next] = $l[$i] - $h[$i];
				$r[$next] = $h[$next] * $sin[$next] / $cos[$next];
			} else if ($h[$next] < $l[$i] / 2) {
				$h[$i] = $l[$i] - $h[$next];
				$r[$i] = $h[$i] * $sin[$i] / $cos[$i];
			} else {
				$h[$i] = $h[$next] = $l[$i] / 2;
				$r[$i] = $h[$i] * $sin[$i] / $cos[$i];
				$r[$next] = $h[$next] * $sin[$next] / $cos[$next];
			}
		}
		for ($i=0; $i<=$count; $i++) {
			$back = ($i + $count - 1) % $count;
			$curr = $i % $count;
			$next = ($i + 1) % $count;
			if ($h[$curr] < 1) {
				if ($i > 0)
					$newparts->addpoint($x[$curr], $y[$curr]);
				$newparts->addpoint($x[$curr], $y[$curr]);
				if ($i < $count)
					$newparts->addpoint($x[$curr], $y[$curr]);
				continue;
			}
			$x0 = ($x[$curr] - $x[$back]) * $h[$curr] / $l[$back];
			$y0 = ($y[$curr] - $y[$back]) * $h[$curr] / $l[$back];
			$x2 = ($x[$curr] - $x[$next]) * $h[$curr] / $l[$curr];
			$y2 = ($y[$curr] - $y[$next]) * $h[$curr] / $l[$curr];
			$mul = $r[$curr] * (1 - $sin[$curr]) / $cos[$curr] * 4 / 3;
			$vx0 = ($x[$curr] - $x[$back]) * $mul / $l[$back];
			$vy0 = ($y[$curr] - $y[$back]) * $mul / $l[$back];
			$vx2 = ($x[$curr] - $x[$next]) * $mul / $l[$curr];
			$vy2 = ($y[$curr] - $y[$next]) * $mul / $l[$curr];
			if ($i > 0) {
				$newparts->addpoint($x[$curr] - $x0, $y[$curr] - $y0);
				$newparts->addpoint($x[$curr] - $x0, $y[$curr] - $y0);
				$newparts->addpoint($x[$curr] - $x0 + $vx0, $y[$curr] - $y0 + $vy0);
				$newparts->addpoint($x[$curr] - $x2 + $vx2, $y[$curr] - $y2 + $vy2);
			}
			$newparts->addpoint($x[$curr] - $x2, $y[$curr] - $y2);
			if ($i < $count)
				$newparts->addpoint($x[$curr] - $x2, $y[$curr] - $y2);
		}
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_line($subtype, &$stream) {
		$newparts =& new lineparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 12)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		if ($linewidth <= 0)
			return;
		if ($fgpatid <= 0)
			return;
		$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		$sx = $stream->h();
		$sy = $stream->h();
		$ex = $stream->h();
		$ey = $stream->h();
		$newparts->addpoint($sx, $sy);
		$newparts->addpoint($ex, $ey);
		$arrow = $this->scope->figattrlist->get("arrow");
		if (($arrow & 1))
			$this->createarrow($sx, $sy, $ex, $ey, $fgpatid, $linewidth);
		if (($arrow & 2))
			$this->createarrow($ex, $ey, $sx, $sy, $fgpatid, $linewidth);
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_arc($subtype, &$stream) {
		$newparts =& new bezierparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 22)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$angle = $stream->h();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		if ($linewidth > $right - $left)
			$linewidth = $right - $left;
		if ($linewidth > $bottom - $top)
			$linewidth = $bottom - $top;
		if ($linewidth <= 0)
			return;
		if ($fgpatid <= 0)
			return;
		$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		if ($linewidth < 1)
			$linewidth = 1;
		$right -= (0)? 1 : $linewidth;
		$bottom -= (0)? 1 : $linewidth;
		$this->setup_oval($left, $top, $right, $bottom);
		$sradian = $this->readradian($stream);
		$eradian = $this->readradian($stream);
		if ($eradian <= $sradian)
			$eradian += pi() * 2;
		
		$div = ceil(($eradian - $sradian) * 2 / pi());	# max 90degree
		$this->addpoint_onoval($newparts, $s = $sradian);
		for ($i=1; $i<=$div; $i++) {
			$e = ($sradian * ($div - $i) + $eradian * $i) / $div;
			$this->addvector_onoval($newparts, $s, $e);
			$this->addvector_onoval($newparts, $e, $s);
			$this->addpoint_onoval($newparts, $s = $e);
		}
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_polyline($subtype, &$stream) {
		if ($stream->remain() < 8)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		if ($linewidth <= 0)
			return;
		if ($fgpatid <= 0)
			return;
		$round = $stream->uh();
		$count = $stream->uh();
		if ($stream->remain() < $count * 4)
			return;
		if ($round == 0) {
			$newparts =& new lineparts($this->scope);
			$newparts->stream_infostr = $stream->getinfostr();
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
			while ($count-- > 0) {
				$x = $stream->h();
				$y = $stream->h();
				$newparts->addpoint($x, $y);
			}
			$this->scope->partslist->add($newparts);
			$this->scope->figattrlist->cleartempattr();
			return;
		}
		$newparts =& new bezierparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		
		for ($i=0; $i<$count; $i++) {
			$x[$i] = $stream->h();
			$y[$i] = $stream->h();
			$r[$i] = 0;
			$sin[$i] = 0.5;
			$cos[$i] = 0.5;
			$h[$i] = 0;
		}
		for ($i=0; $i<$count-1; $i++)
			$l[$i] = sqrt(pow($x[$i + 1] - $x[$i], 2) + pow($y[$i + 1] - $y[$i], 2));
		for ($i=1; $i<$count-1; $i++) {
			if (($r1 = min($round / 2, $l[$i - 1], $l[$i])) < 1)
				continue;
			$x0 = ($x[$i - 1] - $x[$i]) / $l[$i - 1];
			$y0 = ($y[$i - 1] - $y[$i]) / $l[$i - 1];
			$x2 = ($x[$i + 1] - $x[$i]) / $l[$i];
			$y2 = ($y[$i + 1] - $y[$i]) / $l[$i];
			$x1 = $x0 + $x2;
			$y1 = $y0 + $y2;
			if (($l1 = sqrt($x1 * $x1 + $y1 * $y1)) < 0.1)
				continue;
			$x1 /= $l1;
			$y1 /= $l1;
			$cos1 = 0;
			if (($sin1 = abs($x0 * $y1 - $x1 * $y0)) <= 0.01)
				continue;
			if (($cos1 = sqrt(1 - $sin1 * $sin1)) <= 0.01)
				continue;
			$r[$i] = $r1;
			$sin[$i] = $sin1;
			$cos[$i] = $cos1;
			$h[$i] = $r1 * $cos1 / $sin1;
		}
		for (;;) {
			$over_h = 0;
			$over_index = 0;
			for ($i=0; $i<$count-1; $i++) {
				$h1 = $h[$i] + $h[$i + 1] - $l[$i];
				if ($over_h < $h1) {
					$over_h = $h1;
					$over_index = $i;
				}
			}
			if ($over_h <= 0.1)
				break;
			$i = $over_index;
			if ($h[$i] < $l[$i] / 2) {
				$h[$i + 1] = $l[$i] - $h[$i];
				$r[$i + 1] = $h[$i + 1] * $sin[$i + 1] / $cos[$i + 1];
			} else if ($h[$i + 1] < $l[$i] / 2) {
				$h[$i] = $l[$i] - $h[$i + 1];
				$r[$i] = $h[$i] * $sin[$i] / $cos[$i];
			} else {
				$h[$i] = $h[$i + 1] = $l[$i] / 2;
				$r[$i] = $h[$i] * $sin[$i] / $cos[$i];
				$r[$i + 1] = $h[$i + 1] * $sin[$i + 1] / $cos[$i + 1];
			}
		}
		$newparts->addpoint($x[0], $y[0]);
		$newparts->addpoint($x[0], $y[0]);
		for ($i=1; $i<$count-1; $i++) {
			if ($h[$i] < 1) {
				$newparts->addpoint($x[$i], $y[$i]);
				$newparts->addpoint($x[$i], $y[$i]);
				$newparts->addpoint($x[$i], $y[$i]);
				continue;
			}
			$x0 = ($x[$i] - $x[$i - 1]) * $h[$i] / $l[$i - 1];
			$y0 = ($y[$i] - $y[$i - 1]) * $h[$i] / $l[$i - 1];
			$x2 = ($x[$i] - $x[$i + 1]) * $h[$i] / $l[$i];
			$y2 = ($y[$i] - $y[$i + 1]) * $h[$i] / $l[$i];
			$mul = $r[$i] * (1 - $sin[$i]) / $cos[$i] * 4 / 3;
			$vx0 = ($x[$i] - $x[$i - 1]) * $mul / $l[$i - 1];
			$vy0 = ($y[$i] - $y[$i - 1]) * $mul / $l[$i - 1];
			$vx2 = ($x[$i] - $x[$i + 1]) * $mul / $l[$i];
			$vy2 = ($y[$i] - $y[$i + 1]) * $mul / $l[$i];
			$newparts->addpoint($x[$i] - $x0, $y[$i] - $y0);
			$newparts->addpoint($x[$i] - $x0, $y[$i] - $y0);
			$newparts->addpoint($x[$i] - $x0 + $vx0, $y[$i] - $y0 + $vy0);
			$newparts->addpoint($x[$i] - $x2 + $vx2, $y[$i] - $y2 + $vy2);
			$newparts->addpoint($x[$i] - $x2, $y[$i] - $y2);
			$newparts->addpoint($x[$i] - $x2, $y[$i] - $y2);
		}
		$newparts->addpoint($x[$count - 1], $y[$count - 1]);
		$newparts->addpoint($x[$count - 1], $y[$count - 1]);
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	create_curve($subtype, &$stream) {
		$newparts =& new lineparts($this->scope);
		$newparts->stream_infostr = $stream->getinfostr();
		if ($stream->remain() < 10)
			return;
		$linetype = $stream->uh();
		$linewidth = $linetype & 0xff;
		$fgpatid = $stream->uh();
		$bgpatid = $stream->uh();
		$stream->uh();	######### type
		if (($linewidth > 0)&&($fgpatid > 0))
			$newparts->setfgpat($fgpatid, $linewidth, $linetype >> 8);
		$count = $stream->uh();
		if ($stream->remain() < $count * 4)
			return;
		$sx = $stream->h();
		$sy = $stream->h();
		$newparts->addpoint($sx, $sy);
		while ($count-- > 1) {
			$x = $stream->h();
			$y = $stream->h();
			$newparts->addpoint($x, $y);
		}
		if ($x != $sx)
			;
		else if ($y != $sy)
			;
		else if ($bgpatid > 0)
			$newparts->setbgpat($bgpatid);
		
		$this->scope->partslist->add($newparts);
		$this->scope->figattrlist->cleartempattr();
	}
	function	&create($key, $type, &$stream) {
		if ($stream->remain() < 2)
			return $this->scope->factoryfinder;
		$subtype = $stream->uh();
		switch ($subtype & 0xff00) {
			case	0:
				$this->create_rect($subtype, $stream);
				break;
			case	0x100:
				$this->create_roundedrect($subtype, $stream);
				break;
			case	0x200:
				$this->create_oval($subtype, $stream);
				break;
			case	0x300:
				$this->create_sector($subtype, $stream);
				break;
			case	0x400:
				$this->create_chord($subtype, $stream);
				break;
			case	0x500:
				$this->create_polygon($subtype, $stream);
				break;
			case	0x600:
				$this->create_line($subtype, $stream);
				break;
			case	0x700:
				$this->create_arc($subtype, $stream);
				break;
			case	0x800:
				$this->create_polyline($subtype, $stream);
				break;
			case	0x900:
				$this->create_curve($subtype, $stream);
				break;
		}
		return $this->scope->factoryfinder;
	}
}

class	lineparts extends parts {
	var	$fgpat = null;
	var	$bgpat = null;
	var	$pointlist;	# if close, $pointlist[0:1] == pointlist[last-2:1]
	var	$linewidth = 0;
	var	$linepattern = array(0);
	function	lineparts(&$parent) {
		parent::parts($parent);
		$this->pointlist = array();
	}
	function	printinfo() {
		print "lineparts before $this->stream_infostr<BR>\n";
		$i = 0;
		for (;;) {
			printf("(%d, %d)", $this->pointlist[$i], $this->pointlist[$i + 1]);
			if (($i += 2) >= count($this->pointlist))
				break;
			print " - ";
		}
		print "<BR>\n";
	}
	function	addpoint($x, $y) {
		$this->pointlist[] = $x;
		$this->pointlist[] = $y;
	}
	function	setfgpat($fgpatid, $linewidth = 1, $linetype = 0) {
		global	$global;
		
		$global->log->info(sprintf("fgpatid(%08x)", $fgpatid));
		
		$this->fgpat = $this->parent->figattrlist->get(sprintf("pattern-%04x", $fgpatid));
		$this->linewidth = $linewidth;
		$this->linepattern = $this->parent->figattrlist->get(sprintf("linetype-%04x", $linetype));
	}
	function	setbgpat($bgpatid) {
		global	$global;
		
		$global->log->info(sprintf("bgpatid(%08x)", $bgpatid));
		
		$this->bgpat = $this->parent->figattrlist->get(sprintf("pattern-%04x", $bgpatid));
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$x = $left;
		$y = $top;
		if (count($this->pointlist) < 2) {
			$right = $x;
			$bottom = $y;
			return;
		}
		$left = $right = $this->pointlist[0];
		$top = $bottom = $this->pointlist[1];
		for ($i=2; $i<count($this->pointlist); $i+=2) {
			if ($left > $this->pointlist[$i])
				$left = $this->pointlist[$i];
			if ($right < $this->pointlist[$i])
				$right = $this->pointlist[$i];
			if ($top > $this->pointlist[$i + 1])
				$top = $this->pointlist[$i + 1];
			if ($bottom < $this->pointlist[$i + 1])
				$bottom = $this->pointlist[$i + 1];
		}
		$left += $x;
		$top += $y;
		$right += $this->linewidth + $x;
		$bottom += $this->linewidth + $y;
		if ($draw == 0)
			return;
		$pointlist = $this->pointlist;
		$count = count($this->pointlist) / 2;
		for ($i=0; $i<count($this->pointlist); $i+=2) {
			$pointlist[$i] += $x;
			$pointlist[$i + 1] += $y;
			$this->parent->scale->convert_point($pointlist[$i], $pointlist[$i + 1]);
		}
		if ($this->bgpat !== null) {
			$genv->setpattern($this->bgpat);
			$genv->polygon($count - 1, $pointlist);
		}
		if (($this->fgpat !== null)&&($this->linewidth > 0)) {
			$linewidth = $this->linewidth;
			$this->parent->scale->convert_length($linewidth);
			$genv->setpattern($this->fgpat);
			$genv->setlattr($this->linewidth, $this->linepattern);
			$genv->polyline($count, $pointlist);
		}
		return;
	}
}

class	bezierparts extends parts {
	var	$fgpat = null;
	var	$bgpat = null;
	var	$pointlist;	# if close, $pointlist[0:1] == pointlist[last-2:1]
	var	$linewidth = 0;
	var	$linepattern = array(0);
	function	bezierparts(&$parent) {
		parent::parts($parent);
		$this->pointlist = array();
	}
	function	printinfo() {
		print "bezierparts before $this->stream_infostr<BR>\n";
		$i = 0;
		$x = $this->pointlist[$i++];
		$y = $this->pointlist[$i++];
		printf("(%d, %d)<BR>\n", $x, $y);
		while ($i < count($this->pointlist)) {
			$x1 = $this->pointlist[$i++];
			$y1 = $this->pointlist[$i++];
			$x2 = $this->pointlist[$i++];
			$y2 = $this->pointlist[$i++];
			$x3 = $this->pointlist[$i++];
			$y3 = $this->pointlist[$i++];
			printf(" - (%d, %d) (%d, %d) (%d, %d)<BR>\n", $x1, $y1, $x2, $y2, $x3, $y3);
		}
	}
	function	addpoint($x, $y) {
		$this->pointlist[] = $x;
		$this->pointlist[] = $y;
	}
	function	setfgpat($fgpatid, $linewidth = 1, $linetype = 0) {
		global	$global;
		
		$global->log->info(sprintf("fgpatid(%08x)", $fgpatid));
		
		$this->fgpat = $this->parent->figattrlist->get(sprintf("pattern-%04x", $fgpatid));
		$this->linewidth = $linewidth;
		$this->linepattern = $this->parent->figattrlist->get(sprintf("linetype-%04x", $linetype));
	}
	function	setbgpat($bgpatid) {
		global	$global;
		
		$global->log->info(sprintf("bgpatid(%08x)", $bgpatid));
		
		$this->bgpat = $this->parent->figattrlist->get(sprintf("pattern-%04x", $bgpatid));
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$x = $left;
		$y = $top;
		if (count($this->pointlist) < 8) {
			$right = $x;
			$bottom = $y;
			return;
		}
		$left = $right = $this->pointlist[0];
		$top = $bottom = $this->pointlist[1];
		for ($i=2; $i<count($this->pointlist); $i+=2) {
			if ($left > $this->pointlist[$i])
				$left = $this->pointlist[$i];
			if ($right < $this->pointlist[$i])
				$right = $this->pointlist[$i];
			if ($top > $this->pointlist[$i + 1])
				$top = $this->pointlist[$i + 1];
			if ($bottom < $this->pointlist[$i + 1])
				$bottom = $this->pointlist[$i + 1];
		}
		$left += $x;
		$top += $y;
		$right += $this->linewidth + $x;
		$bottom += $this->linewidth + $y;
		if ($draw == 0)
			return;
		$pointlist = $this->pointlist;
		$count = int((count($this->pointlist) - 2) / 6);
		for ($i=0; $i<count($this->pointlist); $i+=2) {
			$pointlist[$i] += $x;
			$pointlist[$i + 1] += $y;
			$this->parent->scale->convert_point($pointlist[$i], $pointlist[$i + 1]);
		}
		if ($this->bgpat !== null) {
			$genv->setpattern($this->bgpat);
			$genv->filledbezier($count, $pointlist);
		}
		if (($this->fgpat !== null)&&($this->linewidth > 0)) {
			$linewidth = $this->linewidth;
			$this->parent->scale->convert_length($linewidth);
			$genv->setpattern($this->fgpat);
			$genv->setlattr($this->linewidth, $this->linepattern);
			$genv->polybezier($count, $pointlist);
		}
		return;
	}
}

#
# layout
#	created by textscopeparts::draw()
#

class	layout {
	var	$layoutname = "layout";
	var	$layoutroot = null;
	var	$parent = null;
	var	$partslist;
	var	$offsetlist;
	var	$forwarding = null;
	var	$layout_org;
	var	$layout;
	var	$layout_feed;
	var	$limit = array(0, 0);
	var	$currentparts = null;
	function	layout(&$layoutroot, &$parent, $layout, $limit) {
		$this->layoutroot =& $layoutroot;
		$this->parent =& $parent;
		$this->partslist =& new partslist();
		$this->offsetlist = array();
		$this->layout_org = $this->layout = $this->layout_feed = $layout;
		$this->limit = $limit;
	}
	function	fixlayout() {
		$this->fixchild();
	}
	function	fixchild() {
		while ($this->forwarding !== null) {
			$null = null;
			$forwarding =& $this->forwarding;
			$this->forwarding =& $null;
			$forwarding->fixlayout();
			$this->add($forwarding);
		}
		return;
	}
	function	createbranch(&$parts, $force = 0) {
		die("layout::createbranch called.");
		
		if (($force))
			;
		else if ($parts->parent->textattrlist->get("escapeflag") >= 100)
			;
		else if ($this->forwarding === null)
			;
		else {
			$this->forwarding->createbranch($parts, $force);
			return;
		}
		if (($this->forwarding)) {
			$this->fixchild();
			if ($this->layout == $this->layout_org) {
				# no insertable object
				$this->parent->createbranch($parts, 1);
				return;
			}
		}
		$this->forwarding =& new layout($this->layoutroot, $this, $this->layout, $this->limit);
		$this->forwarding->createbranch($parts, $force);
		return;
	}
	function	feed_pre($layout, &$obj) {
		# make space
		return $layout;
	}
	function	feed_post($layout, &$obj) {
		# make space
		return $layout;
	}
	function	reflowthis(&$layoutroot, &$createbranch, &$target) {	# common to parts-class
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$obj =& $this->partslist->get();
			$obj->reflowthis($this->layoutroot, $createbranch, $target);
		}
		while ($this->forwarding !== null) {
			$null = null;
			$forwarding =& $this->forwarding;
			$this->forwarding =& $null;
			$forwarding->reflowthis($this->layoutroot, $createbranch, $target);
		}
	}
	function	add(&$obj, $force = 0) {
		global	$global;
		
		$offset = array(0, 0);
		$left = $top = 0;
		$obj->draw($this->layoutroot->genv, $left, $top, $right, $bottom, 0);
		if (($left)||($top)||($right)||($bottom))
			;
		else {
				# no feed
			$global->log->info("{$this->layoutname}::add size(null)");
			$this->partslist->add($obj);
			if ($this->limit[0] > 0)
				$offset[0] = $this->layout_feed[2];
			else if ($this->limit[0] < 0)
				$offset[0] = $this->layout_feed[0];
			else if ($this->limit[1] > 0)
				$offset[1] = $this->layout_feed[3];
			else if ($this->limit[1] < 0)
				$offset[1] = $this->layout_feed[1];
			$this->offsetlist[] = $offset;
			$obj->setpartsinfo($this);
			return;
		}
		$layout = $this->feed_pre($this->layout_feed, $obj);
		
		$global->log->info("{$this->layoutname}::add");
		$global->log->info("{$this->layoutname}::add size({$left}, {$top}, {$right}, {$bottom})");
		$global->log->info("{$this->layoutname}::add limit({$this->limit[0]}, {$this->limit[1]}) layout({$layout[0]}, {$layout[1]}, {$layout[2]}, {$layout[3]})");
		
		if ($this->limit[0] > 0) {
			$offset[0] = $layout[0] = max($layout[0], $layout[2] - $left);
			$layout[2] = $layout[0] + $right;
			if ($layout[1] > $top)
				$layout[1] = $top;
			if ($layout[3] < $bottom)
				$layout[3] = $bottom;
			if ($layout[2] <= $this->limit[0])
				$force = 1;
		} else if ($this->limit[0] < 0) {
			$offset[0] = $layout[2] = min($layout[2], $layout[0] - $right);
			$layout[0] = $layout[2] + $left;
			if ($layout[1] > $top)
				$layout[1] = $top;
			if ($layout[3] < $bottom)
				$layout[3] = $bottom;
			if ($layout[0] >= $this->limit[0])
				$force = 1;
		} else if ($this->limit[1] > 0) {
			$offset[1] = $layout[1] = max($layout[1], $layout[3] - $top);
			$layout[3] = $layout[1] + $bottom;
			if ($layout[0] > $left)
				$layout[0] = $left;
			if ($layout[2] < $right)
				$layout[2] = $right;
			if ($layout[3] <= $this->limit[1])
				$force = 1;
		} else if ($this->limit[1] < 0) {
			$offset[1] = $layout[3] = min($layout[3], $layout[1] - $bottom);
			$layout[1] = $layout[3] + $top;
			if ($layout[0] > $left)
				$layout[0] = $left;
			if ($layout[2] < $right)
				$layout[2] = $right;
			if ($layout[1] >= $this->limit[1])
				$force = 1;
		}
		
		$global->log->info("{$this->layoutname}::add offset({$offset[0]}, {$offset[1]}) layout({$layout[0]}, {$layout[1]}, {$layout[2]}, {$layout[3]})");
		if (($force)) {
			$this->partslist->add($obj);
			$this->offsetlist[] = $offset;
			$obj->setpartsinfo($this);
			$this->layout = $layout;
			$this->layout_feed = $this->feed_post($layout, $obj);
			return;
		}
		$global->log->info("$this->layoutname::add overflow");
		$this->cantadd($obj);
	}
	function	cantadd(&$obj) {
		$work = 1;
		$obj->reflowthis($this->layoutroot, $work, $this->parent);
	}
	function	addtobottom(&$obj, $force = 0) {
		if ($this->forwarding === null)
			$this->add($obj, $force);
		else
			$this->forwarding->addtobottom($obj, $force);
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		global	$global;
		
		# genv is alive
		$x = $left;
		$y = $top;
		$left = $this->layout[0] + $x;
		$top = $this->layout[1] + $y;
		$right = $this->layout[2] + $x;
		$bottom = $this->layout[3] + $y;
		if ($this->limit[0] > 0)
			$left = $this->layout_org[0] + $x;
		else if ($this->limit[0] < 0)
			$right = $this->layout_org[2] + $x;
		else if ($this->limit[1] > 0)
			$top = $this->layout_org[1] + $y;
		else if ($this->limit[1] < 0)
			$bottom = $this->layout_org[3] + $y;
		if ($draw == 0)
			return;
		
		$global->log->info("$this->layoutname::draw start");
		$this->partslist->rewind();
		$pos = 0;
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$offset = $this->offsetlist[$pos];
			$global->log->info("$this->layoutname : pos($pos) offset($offset[0], $offset[1])");
			$l = $offset[0] + $x;
			$t = $offset[1] + $y;
			$target->draw($genv, $l, $t, $r, $b, $draw);
			$pos++;
		}
		$global->log->info("$this->layoutname::draw end");
	}
	function	getcharwidth() {
		$val = 0;
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$val = max($val, $target->getcharwidth());
		}
		return $val;
	}
	function	getcharheight() {
		$val = 0;
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$val = max($val, $target->getcharheight());
		}
		return $val;
	}
	function	setpartsinfo() {
		return;
	}
	function	setcurrentparts(&$parts) {
		$this->currentparts =& $parts;
		$this->parent->setcurrentparts($parts);
	}
	function	printinfo() {
		print "$this->layoutname : start<BR>\n";
		$this->partslist->rewind();
		$pos = 0;
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$offset = $this->offsetlist[$pos];
			print "$this->layoutname : pos($pos) offset($offset[0], $offset[1])<BR>\n";
			$target->printinfo();
			$pos++;
		}
		print "$this->layoutname : end<BR>\n";
	}
}

class	layoutroot extends layout {
	var	$layoutname = "layoutroot";
	var	$genv = null;
	var	$offset;
	var	$count = 0;
	function	layoutroot(&$genv) {
		$this->layoutroot =& $this;
		$this->genv =& $genv;
		$this->offset = array(0, 0);
		$this->layout_org = $this->layout = $this->layout_feed = array(0, 0, 0, 0);
	}
	function	addtobottom(&$obj, $force = 0) {
		die("layoutroot::addtobottom called.");
	}
	function	add(&$obj) {
		global	$global;
		
		if ($this->count == 0) {
			$global->log->info("{$this->layoutname}::add count({$this->count}) == 0");
			return;
		}
		if ($this->count != 1) {
			$global->log->warn("{$this->layoutname}::add count({$this->count}) != 1");
			return;
		}
		$this->forwarding->addtobottom($obj);
	}
	function	setcurrentparts(&$parts) {
		$this->currentparts =& $parts;
		# no parent : $this->parent->setcurrentparts($parts);
	}
	function	printinfo() {
		print "$this->layoutname : start<BR>\n";
		$this->forwarding->printinfo();
		print "$this->layoutname : end<BR>\n";
	}
}

class	layoutroot_text extends layoutroot {
	var	$layoutname = "layoutroot_text";
	function	createbranch(&$parts, $force = 0) {
		global	$global;
		
		if (($force))
			;
		else if ($parts->parent->textattrlist->get("escapeflag") >= 1000)
			;
		else if ($this->forwarding === null)
			;
		else {
			if ($this->count != 1) {
				$global->log->warn("{$this->layoutname}::createbranch count({$this->count}) != 1");
				return;
			}
			$this->forwarding->createbranch($parts, $force);
			return;
		}
		$global->log->info("createbranch count($this->count)");
		if (++$this->count > 1) {
			$global->log->warn("{$this->layoutname}::createbranch count({$this->count}) > 1");
			return;
		}
		$this->fixchild();
		$this->forwarding =& new layout_pageset($this, $this, array(0, 0, 0, 0), array(0, 0));
		$this->forwarding->createbranch($parts, $force);
		return;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		if ($draw == 0)
			return;
		if ($this->forwarding === null)
			return;
		$this->forwarding->fixlayout();
		$left = $top = 0;
		$this->forwarding->draw($genv, $left, $top, $right, $bottom, $draw);
	}
}

class	layout_pageset extends layout {
	var	$layoutname = "layout_pageset";
	var	$pagesizelist;
	function	layout_pageset(&$layoutroot, &$parent, $layout, $limit) {
		parent::layout($layoutroot, $parent, $layout, $limit);
		$this->pagesizelist = array();
	}
	function	createbranch(&$parts, $force = 0) {
		global	$global;
		
		if (($force))
			;
		else if ($parts->parent->textattrlist->get("escapeflag") >= 0xf)
			;
		else if ($this->forwarding === null)
			;
		else {
			$this->forwarding->createbranch($parts, $force);
			return;
		}
		if (($this->forwarding))
			$this->fixchild();
		
		$pagesize = array(0, 0, 0, 0, 0, 0);
		$pagesize[0] = $parts->parent->pageattrlist->get("pageh");
		$pagesize[1] = $parts->parent->pageattrlist->get("pagev");
		$pagesize[2] = $parts->parent->pageattrlist->get("marginl");
		$pagesize[3] = $parts->parent->pageattrlist->get("margint");
		$pagesize[4] = $parts->parent->pageattrlist->get("marginr");
		$pagesize[5] = $parts->parent->pageattrlist->get("marginb");
		$this->pagesizelist[] = $pagesize;
		$layout = array(0, 0, 0, 0);
		$layout[2] = $pagesize[0] - $pagesize[2] - $pagesize[4];
		$layout[3] = $pagesize[1] - $pagesize[3] - $pagesize[5];
		$offset = array(0, 0);
		$offset[0] = $pagesize[2];
		$offset[1] = $pagesize[3];
		$newlayout = array(0, 0, 0, 0);
		$newlimit = array(0, 0);
		switch ($parts->parent->textattrlist->get("chardir")) {
			default:
			case	0:
				$newlayout[2] = $layout[2];
				$newlimit[1] = $layout[3];
				break;
			case	1:
				$newlayout[0] = -$layout[2];
				$newlimit[1] = $layout[3];
				$offset[0] += $layout[2];
				break;
			case	2:
				$newlayout[3] = $layout[3];
				$newlimit[0] = -$layout[2];
				$offset[0] += $layout[2];
				break;
		}
		$this->offsetlist[] = $offset;
		$this->forwarding =& new layout_lineset($this->parent, $this, $newlayout, $newlimit);
		$this->forwarding->createbranch($parts, $force);
		return;
	}
	function	add(&$obj, $force = 0) {
		global	$global;
		
		$global->log->info("{$this->layoutname}::add size(null)");
		$this->partslist->add($obj);
		$obj->setpartsinfo($this);
		return;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		global	$global;
		
#		if ($draw == 0)
#			return;
		$predraw = 1;
		if (($genv_pre =& $genv->newpage(0, 0)) === null) {
			$genv_pre =& $genv;
			$predraw = 0;
		}
		if (($predraw)) {
			$this->partslist->rewind();
			$pos = 0;
			$pageage = -1;
			while ($this->partslist->remain() > 0) {
				$target =& $this->partslist->get();
				$pagesize = $this->pagesizelist[$pos];
				if ($pageage != $target->currentparts->parent->pageattrlist->get("pageage")) {
					$pageage = $target->currentparts->parent->pageattrlist->get("pageage");
					$genv_pre->setpagenumber($target->currentparts->parent->pageattrlist->get("pagenumber"), $target->currentparts->parent->pageattrlist->get("pagestep"));
				}
				if ($genv_pre->newpage($pagesize[0], $pagesize[1], $pagesize[2], $pagesize[3], $pagesize[4], $pagesize[5]) === null)
					break;
				$offset = $this->offsetlist[$pos];
				$global->log->info("{$this->layoutname} : pos({$pos}) offset({$offset[0]}, {$offset[1]})");
				$l = $offset[0] + $left;
				$t = $offset[1] + $top;
#				$target->draw($genv_pre, $l, $t, $r, $b, $predraw);
				$target->draw($genv_pre, $l, $t, $r, $b, $draw);
				$pos++;
			}
			$predraw = 0;
		}
		
		$global->log->info("{$this->layoutname}::draw start");
		$this->partslist->rewind();
		$pos = 0;
		$pageage = -1;
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$pagesize = $this->pagesizelist[$pos];
			if ($pageage != $target->currentparts->parent->pageattrlist->get("pageage")) {
				$pageage = $target->currentparts->parent->pageattrlist->get("pageage");
				$genv->setpagenumber($target->currentparts->parent->pageattrlist->get("pagenumber"), $target->currentparts->parent->pageattrlist->get("pagestep"));
			}
			$genv->setoverlay($target->currentparts->parent->pageattrlist);
			if ($genv->newpage($pagesize[0], $pagesize[1], $pagesize[2], $pagesize[3], $pagesize[4], $pagesize[5]) === null)
				break;
			$offset = $this->offsetlist[$pos];
			$global->log->info("$this->layoutname : pos($pos) offset($offset[0], $offset[1])");
			$l = $offset[0] + $left;
			$t = $offset[1] + $top;
			$target->draw($genv, $l, $t, $r, $b, $draw);
			$pos++;
		}
		$global->log->info("$this->layoutname::draw end");
	}
}

class	layoutroot_infig extends layoutroot {
	var	$layoutname = "layoutroot_infig";
	function	layoutroot_infig(&$genv, $left, $top, $right, $bottom) {
		parent::layoutroot($genv);
		$this->offset = array($left, $top);
		$this->layout = array(0, 0, $right - $left, $bottom - $top);
	}
	function	createbranch(&$parts, $force = 0) {
		global	$global;
		
		if (($force))
			;
		else if ($parts->parent->textattrlist->get("escapeflag") >= 1000)
			;
		else if ($this->forwarding === null)
			;
		else {
			if ($this->count != 1) {
				$global->log->warn("{$this->layoutname}::createbranch count({$this->count}) != 1");
				return;
			}
			$this->forwarding->createbranch($parts, $force);
			return;
		}
		
		$global->log->info("{$this->layoutname}::createbranch count({$this->count})");
		if (++$this->count > 1) {
			$global->log->warn("{$this->layoutname}::createbranch count({$this->count}) > 1");
			return;
		}
		$this->fixchild();
		$newlayout = array(0, 0, 0, 0);
		$newlimit = array(0, 0);
		switch ($parts->parent->textattrlist->get("chardir")) {
			default:
			case	0:
				$newlayout[2] = $this->layout[2];
				$newlimit[1] = $this->layout[3];
				break;
			case	1:
				$newlayout[0] = -$this->layout[2];
				$newlimit[1] = $this->layout[3];
				$this->offset[0] += $this->layout[2];
				break;
			case	2:
				$newlayout[3] = $this->layout[3];
				$newlimit[0] = -$this->layout[2];
				$this->offset[0] += $this->layout[2];
				break;
		}
		$this->forwarding =& new layout_lineset($this, $this, $newlayout, $newlimit);
		$this->forwarding->createbranch($parts, $force);
		return;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		if ($this->forwarding === null)
			return;
		$this->forwarding->fixlayout();
		$left += $this->offset[0];
		$top += $this->offset[1];
		$this->forwarding->draw($genv, $left, $top, $right, $bottom, $draw);
	}
}

class	layout_lineset extends layout {
	var	$layoutname = "layout_lineset";
	var	$first = 1;
	var	$padposlist;
	function	layout_lineset(&$layoutroot, &$parent, $layout, $limit) {
		parent::layout($layoutroot, $parent, $layout, $limit);
		$this->padposlist = array();
	}
	function	createbranch(&$parts, $force = 0) {
		global	$global;
		
		if (($force))
			;
		else if ($parts->parent->textattrlist->get("escapeflag") >= 0xa)
			;
		else if ($this->forwarding === null)
			;
		else {
			$this->forwarding->createbranch($parts, $force);
			return;
		}
		
		$global->log->info("{$this->layoutname}::createbranch force({$force})");
		if (($this->forwarding)) {
			$this->fixchild();
			if ($this->layout == $this->layout_org) {
				$global->log->info("{$this->layoutname}::createbranch no object exist");
				$this->parent->createbranch($parts, 1);
				return;
			}
		}
		$newlimit = array(0, 0);
		if ($this->limit[0] != 0) {
			if ($this->layout[3] > 0)
				$newlimit[1] = $this->layout[3];
			else if ($this->layout[1] < 0)
				$newlimit[1] = $this->layout[1];
		} else if ($this->limit[1] != 0) {
			if ($this->layout[2] > 0)
				$newlimit[0] = $this->layout[2];
			else if ($this->layout[0] < 0)
				$newlimit[0] = $this->layout[0];
		}
		$this->forwarding =& new layout_lineblock($this->layoutroot, $this, array(0, 0, 0, 0), $newlimit);
		$this->forwarding->createbranch($parts, $force);
		return;
	}
	function	fixlayout() {
		parent::fixlayout();
		if (($count = count($this->padposlist)) > 0) {
			$shiftx = $shifty = 0;
			if ($this->limit[0] > 0) {
				if (($shiftx = max(0, $this->limit[0] - $this->layout[2])) <= 0)
					return;
				$this->layout[2] = $this->limit[0];
			} else if ($this->limit[0] < 0) {
				if (($shiftx = min(0, $this->limit[0] - $this->layout[0])) >= 0)
					return;
				$this->layout[0] = $this->limit[0];
			} else if ($this->limit[1] > 0) {
				if (($shifty = max(0, $this->limit[1] - $this->layout[3])) < 0)
					return;
				$this->layout[3] = $this->limit[1];
			} else if ($this->limit[1] < 0) {
				if (($shifty = min(0, $this->limit[1] - $this->layout[1])) >= 0)
					return;
				$this->layout[1] = $this->limit[1];
			}
			$pos = 0;
			for ($i=0; $i<count($this->offsetlist); $i++) {
				while (($pos < count($this->padposlist))&&($i >= $this->padposlist[$pos]))
					$pos++;
				$this->offsetlist[$i][0] += $shiftx * $pos / $count;
				$this->offsetlist[$i][1] += $shifty * $pos / $count;
			}
		}
	}
	function	feed_pre($layout, &$obj) {
		if ($this->currentparts === null)
			;
		else if ($this->layout != $this->layout_org)
			$this->currentparts->parent->textattrlist->feedline($layout, $this->limit, $obj->getcharwidth(), $obj->getcharheight());
		return $layout;
	}
	function	add(&$obj, $force = 0) {
		if (($this->first)) {
			$this->first = 0;
			$force = 1;
		}
 		parent::add($obj, $force);
	}
	function	addtobottom(&$obj, $force = 0) {
		switch ($obj->layoutcontrol) {
			case	0xffa008:
				$this->padposlist[] = count($this->offsetlist);
				return;
		}
		parent::addtobottom($obj, $force);
	}
}

class	layout_lineblock extends layout {
	var	$layoutname = "layout_lineblock";
	var	$first = 1;
	var	$lastwordwrap = 0;
	var	$reflowpos = -1;
	var	$lastlayout;
	function	layout_lineblock(&$layoutroot, &$parent, $layout, $limit) {
		parent::layout($layoutroot, $parent, $layout, $limit);
		$this->lastlayout = $this->layout;
	}
	function	createbranch(&$parts, $force = 0) {
		global	$global;
		
		$global->log->info("{$this->layoutname}::createbranch force({$force})");
	}
	function	fixlayout() {
		parent::fixlayout();
		if ($this->layout != $this->layout_org) {
			$align = $this->currentparts->parent->textattrlist->get("linealign");
			$shiftx = $shifty = 0;
			if ($this->limit[0] > 0) {
				if (($remain = max(0, $this->limit[0] - $this->layout[2])) <= 0)
					return;
				switch ($align) {
					case	1:
						$shiftx = $remain / 2;
						break;
					case	2:
						$shiftx = $remain;
						break;
				}
				$this->layout[2] = $this->limit[0];
			} else if ($this->limit[0] < 0) {
				if (($remain = min(0, $this->limit[0] - $this->layout[0])) >= 0)
					return;
				switch ($align) {
					case	1:
						$shiftx = $remain / 2;
						break;
					case	2:
						$shiftx = $remain;
						break;
				}
				$this->layout[0] = $this->limit[0];
			} else if ($this->limit[1] > 0) {
				if (($remain = max(0, $this->limit[1] - $this->layout[3])) <= 0)
					return;
				switch ($align) {
					case	1:
						$shifty = $remain / 2;
						break;
					case	2:
						$shifty = $remain;
						break;
				}
				$this->layout[3] = $this->limit[1];
			} else if ($this->limit[1] < 0) {
				if (($remain = min(0, $this->limit[1] - $this->layout[1])) >= 0)
					return;
				switch ($align) {
					case	1:
						$shifty = $remain / 2;
						break;
					case	2:
						$shifty = $remain;
						break;
				}
				$this->layout[1] = $this->limit[1];
			}
			for ($i=0; $i<count($this->offsetlist); $i++) {
				$this->offsetlist[$i][0] += $shiftx;
				$this->offsetlist[$i][1] += $shifty;
			}
			return;
		}
		# empty line
		if ($this->limit[0] != 0) {
			$this->layout[1] = 1 - $this->getcharheight();
			$this->layout[3] = 1;
		} else if ($this->limit[1] != 0) {
			$width = $this->getcharwidth();
			$this->layout[0] = -$width / 2;
			$this->layout[2] = $width - ($width / 2);
		}
	}
	function	feed_post($layout, &$obj) {
		if ($this->currentparts !== null)
			$this->currentparts->parent->textattrlist->feedchar($layout, $this->limit);
		return $layout;
	}
	function	add(&$obj, $force = 0) {
		if (($this->first)) {
			$this->first = 0;
			$force = 1;
		}
		if (($wordwrap = $obj->iswordwrap()) != -2) {
			if ($this->lastwordwrap < 0)
				$this->reflowpos = $this->partslist->count();
			else if ($wordwrap < 0)
				$this->reflowpos = $this->partslist->count();
			else if (($this->lastwordwrap & 2))
				;
			else if (($wordwrap & 1))
				;
			else
				$this->reflowpos = $this->partslist->count();
			$this->lastwordwrap = $wordwrap;
			$this->lastlayout = $this->layout;
		}
		parent::add($obj, $force);
	}
	function	cantadd(&$obj) {
		$work = 1;
		$list = array();
		if ($this->reflowpos > 0) {
			$this->partslist->setpos($this->reflowpos);
			while (($this->partslist->remain())) {
				$list[] =& $this->partslist->get();
			}
			$this->partslist->rewind();
			$this->partslist->truncate($this->reflowpos);
		}
		$list[] =& $obj;
		for ($i=0; $i<count($list); $i++)
			$list[$i]->reflowthis($this->layoutroot, $work, $this->parent);
		$this->layout = $this->lastlayout;
	}
	function	getcharwidth() {
		if (($val = parent::getcharwidth()) > 0)
			return $val;
		if ($this->currentparts === null)
			return 0;
		return $this->currentparts->parent->textattrlist->getcharwidth();
	}
	function	getcharheight() {
		if (($val = parent::getcharheight()) > 0)
			return $val;
		if ($this->currentparts === null)
			return 0;
		return $this->currentparts->parent->textattrlist->getcharheight();
	}
}

class	branchcreatorparts extends parts {
	function	printinfo() {
		printf("branchcreatorparts escapeflag(%08x)<BR>\n", $this->parent->textattrlist->get("escapeflag"));
	}
	function	reflowthis(&$layoutroot, &$createbranch, &$target) {
		if (($createbranch)) {
			$target->createbranch($this, 1);
			$createbranch = 0;
		}
		$layoutroot->add($this);
		$layoutroot->createbranch($this, 0);
	}
	function	iswordwrap() {
		return -2;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$right = $left;
		$bottom = $top;
	}
	function	setpartsinfo() {
		return;
	}
}

class	attrholderparts extends parts {
	function	printinfo() {
		printf("attrholderparts<BR>\n");
	}
	function	iswordwrap() {
		return -2;
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$right = $left;
		$bottom = $top;
	}
}

#
# scopeparts
#

class	scopeparts extends parts {
	var	$partslist;
	var	$factoryfinder;
	var	$pageattrlist;
	var	$textattrlist;
	var	$figattrlist;
	var	$scale;
	function	scopeparts(&$parent) {
		parent::parts($parent);
		$this->partslist =& new partslist();
		$this->factoryfinder =& new tadfactoryfinder();
		$this->pageattrlist =& new pageattrlist();
		$this->textattrlist =& new textattrlist();
		$this->figattrlist =& new figattrlist();
		$this->scale =& new scale();
		$this->nestedscopeparts($parent);
	}
	function	nestedscopeparts(&$parent) {
		$this->factoryfinder->importcopy($this->parent->factoryfinder);
		$this->pageattrlist->importcopy($this->parent->pageattrlist);
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$this->partslist->rewind();
		$x = $left;
		$y = $top;
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$left0 = $x;
			$top0 = $y;
			$right0 = 0;
			$bottom0 = 0;
			$target->draw($genv, $left0, $top0, $right0, $bottom0, $draw);
			if ($left > $left0)
				$left = $left0;
			if ($top > $top0)
				$top = $top0;
			if ($right < $right0)
				$right = $right0;
			if ($bottom < $bottom0)
				$bottom = $bottom0;
		}
		return;
	}
}

class	basescopeparts extends scopeparts {
	function	basescopeparts() {
		$null = null;
		parent::scopeparts($null);
		new tadfactory_basetextscopeparts($this);
		new tadfactory_basefigscopeparts($this);
	}
	function	nestedscopeparts(&$parent) {
		return;		# override
	}
	function	printinfo() {
		print "basescopeparts<BR>\n";
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->printinfo();
		}
		print "end of basescopeparts<BR>\n";
	}
	function	draw(&$genv) {
		$this->partslist->rewind();
		if ($this->partslist->remain() <= 0)
			die("no scope.");
		$target =& $this->partslist->get();
		$left = $top = $right = $bottom = 0;
		$target->draw($genv, $left, $top, $right, $bottom, 1);
	}
}

class	tadfactory_textscopeparts extends tadfactory_parts {
	var	$factoryname = "textscope";
	function	tadfactory_textscopeparts(&$scope, $dig = 0) {
		parent::tadfactory_parts($scope);
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("ffe1", $target);
	}
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$global->log->info(sprintf("textscope(%08x)", $type));
		if ($type == 0xffe2) {
			$this->scope->figattrlist->cleartempattr();
			return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 24)
			return $this->scope->factoryfinder;
		$newscope =& new textscopeparts($this->scope);
		$newscope->stream_infostr = $stream->getinfostr();
		$newscope->factoryfinder->add("ffe2", $this);
		$stream->h();	# view
		$stream->h();
		$stream->h();
		$stream->h();
		$stream->h();	# draw
		$stream->h();
		$stream->h();
		$stream->h();
		$newscope->pageattrlist->set_dpi_h($stream->h());
		$newscope->pageattrlist->set_dpi_v($stream->h());
		$newscope->textattrlist->add("lang", $stream->uh());
		$stream->h();	# bgpat
		$this->scope->partslist->add($newscope);
		if ($type == -1)
			$newscope->pageattrlist->tadfactory($newscope);
		return $newscope->factoryfinder;
	}
}

class	tadfactory_basetextscopeparts extends tadfactory_textscopeparts {
	function	&create($key, $type, &$stream) {
		if ($type == 0xffe1)
			$type = -1;
		return parent::create($key, $type, $stream);
	}
}

class	tadfactory_textscopeparts_intext extends tadfactory_textscopeparts {
	var	$factoryname = "textscope in text";
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$global->log->info(sprintf("textscope(%08x)", $type));
		if ($type == 0xffe2) {
			$this->scope->figattrlist->cleartempattr();
			return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 24)
			return $this->scope->factoryfinder;
		$newscope =& new textscopeparts($this->scope);
		$newscope->stream_infostr = $stream->getinfostr();
		$newscope->factoryfinder->add("ffe2", $this);
		$stream->h();	# view
		$stream->h();
		$stream->h();
		$stream->h();
		$stream->h();	# draw
		$stream->h();
		$stream->h();
		$stream->h();
		$newscope->pageattrlist->set_dpi_h($stream->h());
		$newscope->pageattrlist->set_dpi_v($stream->h());
		$newscope->textattrlist->add("lang", $stream->uh());
		$stream->h();	# bgpat
		$newscope->partslist =& $this->scope->partslist;
			# share partslist
			# not regist newscope
		return $newscope->factoryfinder;
	}
}

class	textscopeparts extends scopeparts {
	function	textscopeparts(&$parent, $dig = 0) {
		$target =& $parent;
		if ($dig == 0) {
			$target =& $this;
			parent::scopeparts($parent);
		}
		new tadfactory_textscopeparts_intext($target, $dig);
		$this->textattrlist->tadfactory($target, $dig);
		new textstringscopeparts($target, 1);
		if (($dig))
			return;
		$this->textattrlist->importcopy($this->parent->textattrlist);
		$this->textattrlist->add("escapeflag", 0x7fffffff);
	}
	function	printinfo() {
		print "textscopeparts before $this->stream_infostr<BR>\n";
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->printinfo();
		}
		print "layout of textscopeparts<BR>\n";
		$genv =& new genv();
		$layoutroot =& new layoutroot_text($genv);
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
#			$target->layout($layoutroot);
			$target->reflowthis($layoutroot, $zero, $null);
		}
		$layoutroot->printinfo();
		print "end of textscopeparts<BR>\n";
	}
	function	&create($key, $type, &$stream) {
		$newscope =& new textstringscopeparts($this);
		$this->partslist->add($newscope);
		$this->textattrlist->add("escapeflag", 0);
		return $newscope->factoryfinder->read($key, $type, $stream);
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		global	$global;
		
		$global->log->info("textscopeparts::draw");
		$layoutroot =& new layoutroot_text($genv);
		
		$this->partslist->rewind();
		$zero = 0;
		$null = null;
		while ($this->partslist->remain() > 0) {
			$global->log->info("layout");
			
			$target =& $this->partslist->get();
#			$target->layout($layoutroot);
			$target->reflowthis($layoutroot, $zero, $null);
		}
		$global->log->info("draw");
		$layoutroot->draw($genv, $left, $top, $right, $bottom, $draw);
		return;
	}
}

class	tadfactory_textscopeparts_infig extends tadfactory_textscopeparts {
	var	$factoryname = "textscope in fig";
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$global->log->info(sprintf("textscope(%08x)", $type));
		if ($type == 0xffe2) {
			$this->scope->figattrlist->cleartempattr();
			return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 24)
			return $this->scope->factoryfinder;
		$newscope =& new textscopeparts_infig($this->scope);
		$newscope->stream_infostr = $stream->getinfostr();
		$newscope->factoryfinder->add("ffe2", $this);
		$newscope->view[0] = $stream->h();
		$newscope->view[1] = $stream->h();
		$newscope->view[2] = $stream->h();
		$newscope->view[3] = $stream->h();
		$left = $stream->h();
		$top = $stream->h();
		$right = $stream->h();
		$bottom = $stream->h();
		$newscope->scale->setdraw(0, 0, $right - $left, $bottom - $top);
		$newscope->pageattrlist->set_dpi_h($stream->h());
		$newscope->pageattrlist->set_dpi_v($stream->h());
		$newscope->textattrlist->add("lang", $stream->uh());
		$bgpatid = $stream->uh();
		if ($bgpatid > 0) {
			$newscope->bgpat = $this->scope->figattrlist->get(sprintf("pattern-%04x", $bgpatid));
		}
#$newscope->factoryfinder->read("ffa40800", 0x0811, $stream);
#$newscope->factoryfinder->read("ffa40800", 0x0911, $stream);
		$this->scope->partslist->add($newscope);
		return $newscope->factoryfinder;
	}
}

class	tadfactory_textscopeparts_intext_infig extends tadfactory_textscopeparts_infig {
	var	$factoryname = "textscope in text in fig";
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$global->log->info(sprintf("textscope(%08x)", $type));
		if ($type == 0xffe2) {
			$this->scope->figattrlist->cleartempattr();
			return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 24)
			return $this->scope->factoryfinder;
		$newscope =& new textscopeparts_infig($this->scope);
		$newscope->stream_infostr = $stream->getinfostr();
		$newscope->factoryfinder->add("ffe2", $this);
		$stream->h();	# view
		$stream->h();
		$stream->h();
		$stream->h();
		$stream->h();	# draw
		$stream->h();
		$stream->h();
		$stream->h();
		$newscope->pageattrlist->set_dpi_h($stream->h());
		$newscope->pageattrlist->set_dpi_v($stream->h());
		$newscope->textattrlist->add("lang", $stream->uh());
		$stream->h();	# bgpat
		$newscope->partslist =& $this->scope->partslist;
			# share partslist
			# not regist newscope
		return $newscope->factoryfinder;
	}
}

class	textscopeparts_infig extends textscopeparts {
	var	$bgpat;
	var	$view = array(0, 0, 0, 0);
	function	printinfo() {
		printf("textscopeparts_infig view(%d, %d, %d, %d) before %s<BR>\n", $this->view[0], $this->view[1], $this->view[2], $this->view[3], $this->stream_infostr);
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->printinfo();
		}
		
		$this->scale->getdraw($left, $top, $right, $bottom);
		$genv =& new genv();
		$layoutroot =& new layoutroot_infig($genv, 0, 0, $right - $left, $bottom - $top);
		print "layout of textscopeparts_infig draw($left, $top, $right, $bottom)<BR>\n";
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
#			$target->layout($layoutroot);
			$target->reflowthis($layoutroot, $zero, $null);
		}
		$layoutroot->printinfo();
		print "end of textscopeparts_infig<BR>\n";
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		global	$global;
		
		$x = $left;
		$y = $top;
		$l = $left = $this->view[0] + $x;
		$t = $top = $this->view[1] + $y;
		$r = $right = $this->view[2] + $x;
		$b = $bottom = $this->view[3] + $y;
		if ($draw == 0)
			return;
		
		$this->parent->scale->convert_point($l, $t);
		$this->parent->scale->convert_point($r, $b);
		$this->scale->setview($l, $t, $r, $b);
		if ($this->bgpat !== null) {
			$genv->setpattern($this->bgpat);
			$genv->polygon(4, array($l, $t, $r - 1, $t, $r - 1, $b - 1, $l, $b - 1));
		}
		$global->log->info("size($l, $t, $r, $b)");
		
		$this->scale->getdraw($left, $top, $right, $bottom);
		$layoutroot =& new layoutroot_infig($genv, 0, 0, $right - $left, $bottom - $top);
		$this->partslist->rewind();
		$zero = 0;
		$null = null;
		while ($this->partslist->remain() > 0) {
			$global->log->info("layout");
			$target =& $this->partslist->get();
#			$target->layout($layoutroot);
			$target->reflowthis($layoutroot, $zero, $null);
		}
		$global->log->info("draw");
		
		$layoutroot->draw($genv, $l = 0, $t = 0, $r, $b);
	}
}

class	textstringscopeparts extends scopeparts {
	function	textstringscopeparts(&$parent, $dig = 0) {
		$target =& $parent;
		if ($dig == 0) {
			$target =& $this;
			parent::scopeparts($parent);
		}
		new tadfactory_charparts($target, $dig);
		new tadfactory_controlcharparts($target, $dig);
		new tadfactory_textobjectparts($target, $dig);
		new tadfactory_figscopeparts_intext($target, $dig);
		new tadfactory_imageparts_intext($target, $dig);
		new tadfactory_vobjparts_intext($target, $dig);
		if (($dig))
			return;
		$this->textattrlist->importcopy($this->parent->textattrlist);
		$this->scale =& $this->parent->scale;	# never modify
		$this->partslist->add(new branchcreatorparts($this));
		$this->partslist->add(new attrholderparts($this));
	}
	function	printinfo() {
		printf("textstringscopeparts<BR>\n");
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->printinfo();
		}
		print "end of textstringscopeparts<BR>\n";
	}
#	function	layout(&$layout) {
	function	reflowthis(&$layoutroot, &$createbranch, &$target) {
		global	$global;
		
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$global->log->info("add");
			
			$parts =& $this->partslist->get();
			$parts->reflowthis($layoutroot, $createbranch, $target);
		}
	}
}

class	tadfactory_figscopeparts extends tadfactory_parts {
	var	$factoryname = "figscope";
	function	tadfactory_figscopeparts(&$scope, $dig = 0) {
		parent::tadfactory_parts($scope);
		$target =& $this;
		if (($dig))
			$target =& $scope;
		$this->scope->factoryfinder->add("ffe3", $target);
	}
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$global->log->info(sprintf("figscope(%08x)", $type));
		$global->log->info(sprintf("key(%s) type(%04x)", $key, $type));
		if ($type == 0xffe4) {
			$this->scope->figattrlist->cleartempattr();
			return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 24)
			return $this->scope->factoryfinder;
		if ($type == -1) {
			$newscope =& new basefigscopeparts($this->scope);
			$newscope->pagesize_mode = $global->req->figurepage;
			$newscope->pageattrlist->tadfactory($newscope);
		} else
			$newscope =& new figscopeparts($this->scope);
		$newscope->stream_infostr = $stream->getinfostr();
		$newscope->factoryfinder->add("ffe4", $this);
		$newscope->view[0] = $stream->h();
		$newscope->view[1] = $stream->h();
		$newscope->view[2] = $stream->h();
		$newscope->view[3] = $stream->h();
		$left0 = $stream->h();
		$top0 = $stream->h();
		$right0 = $stream->h();
		$bottom0 = $stream->h();
		$newscope->scale->setdraw($left0, $top0, $right0, $bottom0);
		$newscope->pageattrlist->set_dpi_h($stream->h());
		$newscope->pageattrlist->set_dpi_v($stream->h());
		$this->scope->partslist->add($newscope);
		return $newscope->factoryfinder;
	}
}

class	tadfactory_basefigscopeparts extends tadfactory_figscopeparts {
	function	&create($key, $type, &$stream) {
		if ($type == 0xffe3)
			$type = -1;
		return parent::create($key, $type, $stream);
	}
}

class	tadfactory_figscopeparts_intext extends tadfactory_figscopeparts {
	var	$factoryname = "figscope in text";
	function	&create($key, $type, &$stream) {
		$ret =& parent::create($key, $type, $stream);
		if ($type != 0xffe3)
			return $ret;
		
		$newparts =& $this->scope->partslist->get_last();
		$left = $top = $right = $bottom = 0;
		$newparts->scale->getview($left, $top, $right, $bottom);
		$right = $newparts->view[2] - $newparts->view[0];	# width
		$top = $newparts->view[1] - ($newparts->view[3] - 1);
		$bottom = 1;		# baseline
		if (($right <= 0)||($top >= 1)) {
			$left0 = $top0 = $right0 = $bottom0 = 0;
			$newparts->scale->getdraw($left0, $top0, $right0, $bottom0);
			$top = int(-$this->scope->textattrlist->getcharheight() + 1);
			$right = int($this->scope->textattrlist->getcharwidth() * ($right0 - $left0) / ($bottom0 - $top0));
		}
		$right += ($left = -int($right / 2));	# virtical baseline
		$newparts->view = array($left, $top, $right, $bottom);
		
		return $ret;
	}
}

class	figscopeparts extends scopeparts {
	var	$view = array(0, 0, 0, 0);
	function	figscopeparts(&$parent) {
		parent::scopeparts($parent);
		new tadfactory_textscopeparts_infig($this);
		new tadfactory_figscopeparts($this);
		new tadfactory_groupfigscopeparts($this);
		new tadfactory_figparts($this);
		new tadfactory_imageparts($this);
		new tadfactory_vobjparts($this);
		$this->figattrlist->importcopy($this->parent->figattrlist);
		$this->figattrlist->tadfactory($this);
	}
	function	printinfo() {
		printf("figscopeparts view(%d, %d, %d, %d) before %s<BR>\n", $this->view[0], $this->view[1], $this->view[2], $this->view[3], $this->stream_infostr);
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->printinfo();
		}
		print "end of figscopeparts<BR>\n";
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$x = $left;
		$y = $top;
		$left = $l = $this->view[0] + $x;
		$top = $t = $this->view[1] + $y;
		$right = $r = $this->view[2] + $x;
		$bottom = $b = $this->view[3] + $y;
		if ($draw == 0)
			return;
		
		$this->parent->scale->convert_point($l, $t);
		$this->parent->scale->convert_point($r, $b);
		$this->scale->setview($l, $t, $r, $b);
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->draw($genv, $l = 0, $t = 0, $r, $b, 1);
		}
	}
}

class	basefigscopeparts extends figscopeparts {
	var	$pagesize_mode = -1;
	function	basefigscopeparts(&$parent) {
		parent::figscopeparts($parent);
	}
	function	printinfo() {
		printf("basefigscopeparts view(%d, %d, %d, %d) before %s<BR>\n", $this->view[0], $this->view[1], $this->view[2], $this->view[3], $this->stream_infostr);
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->printinfo();
		}
		print "end of basefigscopeparts<BR>\n";
	}
	function	draw(&$genv, &$left, &$top, &$right, &$bottom, $draw = 1) {
		$predraw = 1;
		if (($genv_pre =& $genv->newpage(0, 0)) === null) {
			$genv_pre =& $genv;
			$predraw = 0;
		}
		switch ($this->pagesize_mode) {
			case	0:
				$this->scale->getdraw($l, $t, $r, $b);
				$this->view = array(0, 0, $r - $l, $b - $t);
				$width = $r - $l;
				$height = $b - $t;
				$l = $t = $r = $b = 0;
				break;
			case	1:
				$this->partslist->rewind();
				$left = $top = $right = $bottom = 0;
				while ($this->partslist->remain() > 0) {
					$target =& $this->partslist->get();
					$l = $t = $r = $b = 0;
					$target->draw($genv_pre, $l, $t, $r, $b, $predraw);
					if ($left > $l)
						$left = $l;
					if ($top > $t)
						$top = $t;
					if ($right < $r)
						$right = $r;
					if ($bottom < $b)
						$bottom = $b;
				}
				$predraw = 0;
				$this->view = array(0, 0, $right - $left, $bottom - $top);
				$this->scale->setdraw($left, $top, $right, $bottom);
				$width = $right - $left;
				$height = $bottom - $top;
				$l = $t = $r = $b = 0;
				break;
			default:
			case	2:
				$l = $this->pageattrlist->get("marginl");
				$t = $this->pageattrlist->get("margint");
				$r = $this->pageattrlist->get("marginr");
				$b = $this->pageattrlist->get("marginb");
				$width = $this->pageattrlist->get("pageh");
				$height = $this->pageattrlist->get("pagev");
				$this->view = array($l, $t, $width - $r, $height - $b);
				$this->scale->setdraw(0, 0, $width - $l - $r, $height - $t - $b);
				$genv->setoverlay($this->pageattrlist);
				$genv->setpagenumber($this->pageattrlist->get("pagenumber"), $this->pageattrlist->get("pagestep"));
				$genv_pre->setpagenumber($this->pageattrlist->get("pagenumber"), $this->pageattrlist->get("pagestep"));
				break;
		}
		$this->scale->setview($this->view[0], $this->view[1], $this->view[2], $this->view[3]);
		
		if (($predraw)) {
			$genv_pre->newpage($width, $height, $l, $t, $r, $b);
			$this->partslist->rewind();
			while ($this->partslist->remain() > 0) {
				$target =& $this->partslist->get();
				$left = $top = 0;
				$target->draw($genv_pre, $left, $top, $right, $bottom, 1);
			}
			$predraw = 0;
		}
		$genv->newpage($width, $height, $l, $t, $r, $b);
		
		$this->partslist->rewind();
		while ($this->partslist->remain() > 0) {
			$target =& $this->partslist->get();
			$target->draw($genv, $l = 0, $t = 0, $r, $b, 1);
		}
		$left = $this->view[0];
		$top = $this->view[1];
		$right = $this->view[2];
		$bottom = $this->view[3];
	}
}

class	tadfactory_groupfigscopeparts extends tadfactory_parts {
	var	$factoryname = "groupscope";
	function	tadfactory_groupfigscopeparts(&$scope) {
		parent::tadfactory_parts($scope);
		$this->scope->factoryfinder->add("ffb2", $this);
	}
	function	&create($key, $type, &$stream) {
		global	$global;
		
		$global->log->info(sprintf("groupfigscope(%08x)", $type));
		if ($type == 0) {
			$this->scope->figattrlist->cleartempattr();
			return $this->scope->factoryfinder;
		}
		if ($stream->remain() < 2)
			return $this->scope->factoryfinder;
		$subtype = $stream->uh();
		switch ($subtype & 0xff00) {
			case	0:		# group start
				$newscope =& new groupfigscopeparts($this->scope);
				$newscope->factoryfinder->add("ffb20100", $this);
				$newscope->partslist =& $this->scope->partslist;
					# share partslist
				return $newscope->factoryfinder;
			case	0x0100:		# group end
				return $this->scope->factoryfinder->read("ffb20100", 0, $stream);
		}
		return $this->scope->factoryfinder;
	}
}

class	groupfigscopeparts extends figscopeparts {
	function	groupfigscopeparts(&$parent) {
		parent::figscopeparts($parent);
		$this->figattrlist->importshare($this->parent->figattrlist);
		$this->scale =& $parent->scale;
	}
}

#
# factoryfinder
#

class	factoryfinder {
	var	$id;
	var	$keytable;
	function	factoryfinder() {
		global	$global;
		
		$this->id = $global->debug->getnextid();
		$this->keytable = array();
	}
	function	add($key, &$factory) {
		$this->keytable[$key] =& $factory;
	}
	function	isexist($key) {
		return (@$this->keytable[$key] === null)? 0 : 1;
	}
	function	importcopy(&$factoryfinder) {
		global	$global;
		
		$keylist = array_keys($factoryfinder->keytable);
		$global->log->info("importcopy called(".implode(", ", $keylist).")");
		foreach ($keylist as $key) {
			$this->add($key, $factoryfinder->keytable[$key]);
		}
	}
	function	&read($key, $type, &$stream) {
		global	$global;
		
		$global->log->info("scope({$this->id}) -- ");
		if (@$this->keytable[$key] === null) {
			$global->log->info(sprintf("not found : key(%s) type(%04x)", $key, $type));
			return $this;
		}
		return $this->keytable[$key]->create($key, $type, $stream);
	}
}

class	tadfactoryfinder extends factoryfinder {
	function	readall(&$stream) {
		$currentfactoryfinder =& $this;
		while ($stream->remain() > 0) {
			$type = $stream->uh();
			$len = 0;
			$substream = null;
			if ($type < 0x0021)
				$key = sprintf("%04x", $type);
			else if ($type < 0x2121)
				$key = "0021";
			else if ($type < 0xfe00)
				$key = "2121";
			else if ($type < 0xff00) {
				$key = "fe21";
				$len = 0;
				while ($type == 0xfefe) {
					$len += 2;
					$type = $stream->uh();
				}
				if ($type < 0x100)
					$len--;
				$type = ($type & 0xff) | ($len << 8);
				$len = 0;
			} else if ($type < 0xff80)
				$key = "ff00";
			else {
				$key = sprintf("%04x", $type);
				$len = $stream->uh();
				if ($len == 0xffff)
					$len = $stream->uw();
				$substream = &$stream->subst_stream($len);
			}
			$currentfactoryfinder =& $currentfactoryfinder->read($key, $type, $substream);
		}
	}
}

#
# tcmap database
#

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

class	tcmap_database extends database {
	function	attr2val($val) {
		$val &= 7;
		if ($val >= 4)
			return $val - 3;
		return -$val;
	}
	function	findfont($tc, $type = 0, $attr = 0, $class = 0x60c6) {
		$attr += 0;
		$class += 0;
		$sql = "select
tcmap.hex, font.fonttype, font.fontname, font.fontcmap, 
	abs(font.attr_prop - ?) * 100 + abs(font.attr_dir - ?) * 10000 + abs(font.attr_line - ?) * 400 +
	abs(font.attr_italic - ?) * 100 + abs(font.attr_weight - ?) * 500 + abs(font.attr_width - ?) * 3 +
	abs(font.class_15 - ?) * 500 + abs(font.class_14 - ?) * 250 + abs(font.class_13 - ?) * 200 + abs(font.class_12 - ?) * 150 + 
	abs(font.class_11 - ?) * 100 + abs(font.class_10 - ?) * 50 + abs(font.class_9 - ?) * 30 + abs(font.class_8 - ?) * 15 + 
	abs(font.class_7 - ?) * 10 + abs(font.class_6 - ?) * 8 + abs(font.class_5 - ?) * 4 + abs(font.class_4 - ?) * 3 + 
	abs(font.class_3 - ?) * 2 + abs(font.class_2 - ?) * 2 + abs(font.class_1 - ?) * 1 + abs(font.class_0 - ?) * 1
as distance from tcmap, font where tcmap.tc = ? and tcmap.encode = font.encode";
		$array = array(
			($attr & 0x8000)? 1 : 0, ($attr & 0x4000)? 1 : 0, ($attr >> 9) & 7,
			-$this->attr2val($attr >> 6), $this->attr2val($attr >> 3), $this->attr2val($attr), 
			($class & 0x8000)? 1 : 0, ($class & 0x4000)? 1 : 0, ($class & 0x2000)? 1 : 0, ($class & 0x1000)? 1 : 0, 
			($class & 0x800)? 1 : 0, ($class & 0x400)? 1 : 0, ($class & 0x200)? 1 : 0, ($class & 0x100)? 1 : 0, 
			($class & 0x80)? 1 : 0, ($class & 0x40)? 1 : 0, ($class & 0x20)? 1 : 0, ($class & 0x10)? 1 : 0, 
			($class & 8)? 1 : 0, ($class & 4)? 1 : 0, ($class & 2)? 1 : 0, ($class & 1)? 1 : 0, 
			$tc + 0
		);
		if ($type > 0) {
			$sql .= " and font.fonttype > ?";
			$array[] = -2;
		} else if ($type < 0) {
			$sql .= " and font.fonttype = ?";
#			$array[] = -2;
			$array[] = $type;
		}
		$sql .= " order by distance limit 1;";
		$array = $this->query($sql, $array);
		if (count($array) != 1)
			return null;
		$result = array();
		$result["string"] = pack("H*", $array[0]["tcmap.hex"]);
		$result["fonttype"] = $array[0]["font.fonttype"] + 0;
		$result["fontname"] = $array[0]["font.fontname"];
		$result["fontcmap"] = $array[0]["font.fontcmap"];
		return $result;
	}
}

class	tcmap_nodatabase {
	function	findfont($tc, $type = 0, $attr = 0, $class = 0x60c6) {
		global	$global;
		
		$s = ($tc >> 16) & 0xffff;
		$h = ($tc >> 8) & 0xff;
		$l = $tc & 0xff;
		if ($s != 0x21)
			return null;
		if (($h < 0x21)||($h > 0x7e))
			return null;
		if (($l < 0x21)||($l > 0x7e))
			return null;
		$result = array();
		$string = chr($h | 0x80).chr($l | 0x80);
		$result["string"] = mb_convert_encoding($string, "UTF-8", "eucJP-win");
		if ($type > 0) {
				# TTF
			$result["fonttype"] = -1;
			$result["fontname"] = "GT200001.TTF";
			$result["fontcmap"] = "unicode";
		} else {
				# PDF
			$result["fonttype"] = -2;
			$result["fontname"] = $global->systeminfo->pdffontname;
			if (($attr & 0x4000))
				$result["fontcmap"] = "UniJIS-UCS2-V";
			else
				$result["fontcmap"] = "UniJIS-UCS2-H";
		}
		return $result;
	}
}

#
# graphic environment
#

class	genv {
	var	$linewidth = 0;
	var	$pagenumber = 1;
	var	$pagestep = 1;
	var	$overlay = null;
	function	genv() {
	}
	function	setdpi($dpi_h, $dpi_v) {
	}
	function	flushpage() {
	}
	function	close() {
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $null;
		return $this;
	}
	function	bitmap() {
	}
	function	setcolor($rgb) {
	}
	function	setpattern($array) {
		$r = 0;
		$g = 0;
		$b = 0;
		$count = 0;
		for ($y=0; $y<count($array); $y++) {
			$array_h = $array[$y];
			for ($x=0; $x<count($array_h); $x++) {
				if (($color = $array_h[$x]) < 0)
					continue;
				$r += ($color >> 16) & 0xff;
				$g += ($color >> 8) & 0xff;
				$b += $color & 0xff;
				$count++;
			}
		}
		if ($count > 0) {
			$r = round($r / $count);
			$g = round($g / $count);
			$b = round($b / $count);
			$this->setcolor(($r << 16)|($g << 8)|($b));
		} else
			$this->setcolor(0xffffff);
	}
	function	setlattr($width, $pattern) {
		$this->linewidth = $width;
	}
	function	polyline($count, $array) {
		for ($i=0; $i<$count*2-2; $i+=2) {
			$sx = $array[$i];
			$sy = $array[$i + 1];
			$ex = $array[$i + 2];
			$ey = $array[$i + 3];
			if (($sx == $ex)||($sy == $ey)) {
				if ($sx < $ex)
					$ex += $this->linewidth - 1;
				else
					$sx += $this->linewidth - 1;
				if ($sy < $ey)
					$ey += $this->linewidth - 1;
				else
					$sy += $this->linewidth - 1;
				$this->polygon(4, array($sx, $sy, $ex, $sy, $ex, $ey, $sx, $ey));
				continue;
			}
			if ($sx > $ex) {
				$tmp = $sx;
				$sx = $ex;
				$ex = $tmp;
				$tmp = $sy;
				$sy = $ey;
				$ey = $tmp;
			}
			$w = $this->linewidth - 1;
			if ($sy < $ey) {	# sx < ex && sy < ey
				$this->polygon(6, array($sx, $sy, $sx + $w, $sy, $ex + $w, $ey, $ex + $w, $ey + $w, $ex, $ey + $w, $sx, $sy + $w));
				continue;
			}
						# sx < ex && sy > ey
			$this->polygon(6, array($sx, $sy, $ex, $ey, $ex + $w, $ey, $ex + $w, $ey + $w, $sx + $w, $sy + $w, $sx, $sy + $w));
		}
	}
	function	polygon() {
	}
	function	polybezier($count, $array) {
		$i = 0;
		$x3 = $array[$i++];
		$y3 = $array[$i++];
		$newarray = array($x3, $y3);
		while ($i < $count * 6) {
			$x0 = $x3;
			$y0 = $y3;
			$x1 = $array[$i++];
			$y1 = $array[$i++];
			$x2 = $array[$i++];
			$y2 = $array[$i++];
			$x3 = $array[$i++];
			$y3 = $array[$i++];
			if (($x0 == $x1)&&($y0 == $y1)&&($x2 == $x3)&&($y2 == $y3)) {
				$newarray[] = $x3;
				$newarray[] = $y3;
				continue;
			}
			$px = $x0 * 0.125 + $x1 * 0.375 + $x2 * 0.375 + $x3 * 0.125;
			$py = $y0 * 0.125 + $y1 * 0.375 + $y2 * 0.375 + $y3 * 0.125;
			$div = 0;
			do {
				$div += 4;
				$l = ($div / 2 + 0.5) / $div;
				$r = 1 - $l;
				$qx = $x0 * pow($l, 3) + 3 * $x1 * $l * $l * $r + 3 * $x2 * $l * $r * $r + $x3 * pow($r, 3);
				$qy = $y0 * pow($l, 3) + 3 * $y1 * $l * $l * $r + 3 * $y2 * $l * $r * $r + $y3 * pow($r, 3);
				$l = ($div / 2 + 1) / $div;
				$r = 1 - $l;
				$rx = $x0 * pow($l, 3) + 3 * $x1 * $l * $l * $r + 3 * $x2 * $l * $r * $r + $x3 * pow($r, 3);
				$ry = $y0 * pow($l, 3) + 3 * $y1 * $l * $l * $r + 3 * $y2 * $l * $r * $r + $y3 * pow($r, 3);
				$l = abs(($px - $qx) * ($ry - $qy) + ($py - $qy) * ($rx - $qx));
				$l /= sqrt(pow($px - $rx, 2) + pow($py - $ry, 2));
			} while ($l > 1);
			for ($j=1; $j<=$div; $j++) {
				$l = ($div - $j + 0.0) / $div;
				$r = (0.0 + $j) / $div;
				$x = $x0 * pow($l, 3) + 3 * $x1 * $l * $l * $r + 3 * $x2 * $l * $r * $r + $x3 * pow($r, 3);
				$y = $y0 * pow($l, 3) + 3 * $y1 * $l * $l * $r + 3 * $y2 * $l * $r * $r + $y3 * pow($r, 3);
				$newarray[] = round($x);
				$newarray[] = round($y);
			}
		}
		$this->polyline(count($newarray) / 2, $newarray);
	}
	function	filledbezier($count, $array) {
		$i = 0;
		$x3 = $array[$i++];
		$y3 = $array[$i++];
		$newarray = array($x3, $y3);
		while ($i < $count * 6) {
			$x0 = $x3;
			$y0 = $y3;
			$x1 = $array[$i++];
			$y1 = $array[$i++];
			$x2 = $array[$i++];
			$y2 = $array[$i++];
			$x3 = $array[$i++];
			$y3 = $array[$i++];
			if (($x0 == $x1)&&($y0 == $y1)&&($x2 == $x3)&&($y2 == $y3)) {
				$newarray[] = $x3;
				$newarray[] = $y3;
				continue;
			}
			$px = $x0 * 0.125 + $x1 * 0.375 + $x2 * 0.375 + $x3 * 0.125;
			$py = $y0 * 0.125 + $y1 * 0.375 + $y2 * 0.375 + $y3 * 0.125;
			$div = 0;
			do {
				$div += 4;
				$l = ($div / 2 + 0.5) / $div;
				$r = 1 - $l;
				$qx = $x0 * pow($l, 3) + 3 * $x1 * $l * $l * $r + 3 * $x2 * $l * $r * $r + $x3 * pow($r, 3);
				$qy = $y0 * pow($l, 3) + 3 * $y1 * $l * $l * $r + 3 * $y2 * $l * $r * $r + $y3 * pow($r, 3);
				$l = ($div / 2 + 1) / $div;
				$r = 1 - $l;
				$rx = $x0 * pow($l, 3) + 3 * $x1 * $l * $l * $r + 3 * $x2 * $l * $r * $r + $x3 * pow($r, 3);
				$ry = $y0 * pow($l, 3) + 3 * $y1 * $l * $l * $r + 3 * $y2 * $l * $r * $r + $y3 * pow($r, 3);
				$l = abs(($px - $qx) * ($ry - $qy) + ($py - $qy) * ($rx - $qx));
				$l /= sqrt(pow($px - $rx, 2) + pow($py - $ry, 2));
			} while ($l > 1);
			for ($j=1; $j<=$div; $j++) {
				$l = ($div - $j + 0.0) / $div;
				$r = (0.0 + $j) / $div;
				$x = $x0 * pow($l, 3) + 3 * $x1 * $l * $l * $r + 3 * $x2 * $l * $r * $r + $x3 * pow($r, 3);
				$y = $y0 * pow($l, 3) + 3 * $y1 * $l * $l * $r + 3 * $y2 * $l * $r * $r + $y3 * pow($r, 3);
				$newarray[] = round($x);
				$newarray[] = round($y);
			}
		}
		$this->polygon(count($newarray) / 2, $newarray);
	}
	function	setfont($name, $class, $attr) {
	}
	function	text($x, $y, $width, $height, $code) {
	}
	function	textwidth($width, $height, $code) {
		return $width;
	}
	function	setpagenumber($pagenumber = 1, $pagestep = 1) {
		$this->pagenumber = $pagenumber;
		$this->pagestep = $pagestep;
	}
	function	setoverlay(&$pageattrlist) {
		$this->overlay =& $pageattrlist;
	}
	function	&getjumpto() {
		$null = null;
		return $null;
	}
	function	setjumpto($l, $t, $r, $b, &$jumpto) {
	}
}

class	genv_log extends genv {
	function	genv_log() {
		global	$global;
		
		$global->file->extendfilename(".html");
		$global->file->setfiletype("text/html");
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		printf("newpage(%d, %d)<BR>\n", $width, $height);
		$null = null;
		if (($width == 0)&&($height == 0))
			return $null;
		return $this;
	}
	function	setdpi($dpi_h, $dpi_v) {
		printf("setcolor(%d, %d)<BR>\n", $dpi_h, $dpi_v);
	}
	function	flushpage() {
		printf("flushpage<BR>\n");
	}
	function	close() {
		printf("close<BR>\n");
	}
	function	bitmap($array, $view, $draw) {
		printf("bitmap view(%d, %d, %d, %d) draw(%d, %d, %d, %d)<BR>\n", $view[0], $view[1], $view[2], $view[3], $draw[0], $draw[1], $draw[2], $draw[3]);
	}
	function	setcolor($rgb) {
		printf("setcolor(%08x)<BR>\n", $rgb);
	}
	function	setpattern($array) {
		printf("setpattern<BR>\n");
	}
	function	setlattr($width, $pattern) {
		printf("setlattr width($width)<BR>\n");
	}
	function	polyline($count, $array) {
		printf("polyline<BR>\n<UL>\n");
		for ($i=0; $i<$count*2; $i+=2)
			printf("\t<LI>(%d, %d)\n", $array[$i], $array[$i + 1]);
		printf("</UL>\n");
	}
	function	polygon($count, $array) {
		printf("polygon<BR>\n<UL>\n");
		for ($i=0; $i<$count*2; $i+=2)
			printf("\t<LI>(%d, %d)\n", $array[$i], $array[$i + 1]);
		printf("</UL>\n");
	}
	function	polybezier($count, $array) {
		printf("polybezier<BR>\n<UL>\n");
		for ($i=0; $i<$count*2; $i+=2)
			printf("\t<LI>(%d, %d)\n", $array[$i], $array[$i + 1]);
		printf("</UL>\n");
	}
	function	filledbezier($count, $array) {
		printf("filledbezier<BR>\n<UL>\n");
		for ($i=0; $i<$count*2; $i+=2)
			printf("\t<LI>(%d, %d)\n", $array[$i], $array[$i + 1]);
		printf("</UL>\n");
	}
	function	setfont($name, $class, $attr) {
		printf("font class(%04x) attr(%04x)<BR>\n", $class, $attr);
	}
	function	text($x, $y, $width, $height, $code) {
		printf("text(%d, %d) size(%d, %d) code(%08x)<BR>\n", $x, $y, $width, $height, $code);
	}
	function	textwidth($width, $height, $code) {
		return $width;
	}
	function	setpagenumber($pagenumber, $pagestep) {
		printf("pagenumber(%d) pagestep(%d)<BR>\n", $pagenumber, $pagestep);
	}
	function	setoverlay(&$pageattrlist) {
		printf("setoverlay<BR>\n");
		$pageattrlist->drawoverlay($this);
	}
}

class	genv_gd2 extends genv {
	var	$gid = FALSE;
	var	$gid_pattern = FALSE;
	var	$pixel;
	var	$rgb;
	var	$trans;
	var	$fontattr = 0;
	var	$fontclass = 0x60c6;
	var	$first = 1;
	function	genv_gd2() {
		parent::genv();
		$this->creategenv(16, 16);
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if ($this->first == 0)
			return $null;		# single page only
		if (($width == 0)&&($height == 0))
			return $null;
		$this->first = 0;
		imagedestroy($this->gid);
		$this->creategenv($width, $height);
		if ($this->overlay !== null)
			$this->overlay->drawoverlay(new genv_gd2overlay($this), 1, $this->pagenumber, $this->pagenumber);
		return $this;
	}
	function	creategenv($width, $height) {
		global	$global;
		
		if ($global->req->truecolor > 0) {
			$this->gid = imagecreatetruecolor($width, $height) or die("imagecreate failed.");
			imagealphablending($this->gid, FALSE);
			$this->trans = imagecolorallocatealpha($this->gid, 0, 0, 1, 0x7f);
			imagealphablending($this->gid, TRUE);
		} else {
			$this->gid = imagecreate($width, $height) or die("imagecreate failed.");
			$this->trans = imagecolorallocate($this->gid, 0, 0, 1);
		}
		imagecolortransparent($this->gid, $this->trans);
		$this->setcolor(0xffffff);
		$this->polygon(4, array(0, 0, $width, 0, $width, $height, 0, $height));
	}
	function	close() {
		if ($this->gid !== FALSE) {
			imagedestroy($this->gid);
			$this->gid = FALSE;
		}
		if ($this->gid_pattern !== FALSE) {
			imagedestroy($this->gid_pattern);
			$this->gid_pattern = FALSE;
		}
	}
	function	array2bitmap($array) {
		global	$global;
		
		$width = count($array[0]);
		$height = count($array);
		$black = 0;
		$trans = -1;
		if ($global->req->truecolor > 0) {
			$gid = imagecreatetruecolor($width, $height) or die("imagecreate(array) failed.");
			imagealphablending($gid, FALSE);
			$trans = imagecolorallocatealpha($gid, 0, 0, 1, 0x7f);
		} else {
			$gid = imagecreate($width, $height) or die("imagecreate(array) failed.");
			$black = imagecolorallocate($gid, 0, 0, 0);
			$trans = imagecolorallocate($gid, 0, 0, 1);
		}
		imagecolortransparent($gid, $trans);
		for ($y=0; $y<$height; $y++)
			for ($x=0; $x<$width; $x++) {
				if (($rgb = $array[$y][$x]) >= 0) {
					$pixel = imagecolorresolvealpha($gid, ($rgb >> 16) & 0xff, ($rgb >> 8) & 0xff, $rgb & 0xff, 0);
					if ($pixel == $trans)
						$pixel = $black;
				} else if ($trans >= 0)
					$pixel = $trans;
				else
					continue;
				imagesetpixel($gid, $x, $y, $pixel);
			}
		return $gid;
	}
	function	bitmap($array, $view, $draw) {
		$gid = $this->array2bitmap($array);
		imagecopyresampled($this->gid, $gid, $view[0], $view[1], $draw[0], $draw[1], $view[2] - $view[0], $view[3] - $view[1], $draw[2] - $draw[0], $draw[3] - $draw[1]);
		imagedestroy($gid);
	}
	function	setcolor($rgb) {
		global	$global;
		
		$global->log->info(sprintf("rgb2pixel(%08x)", $rgb));
		$this->rgb = $rgb;
		$this->pixel = imagecolorresolve($this->gid, ($this->rgb >> 16) & 0xff, ($this->rgb >> 8) & 0xff, $this->rgb & 0xff);
	}
	function	setpattern($array) {
		if ($this->gid_pattern !== FALSE)
			imagedestroy($this->gid_pattern);
		$this->gid_pattern = $this->array2bitmap($array);
		imagealphablending($this->gid_pattern, TRUE);
		imagesettile($this->gid, $this->gid_pattern);
		$this->pixel = IMG_COLOR_TILED;
	}
	function	polyline($count, $array) {
		if ($this->linewidth > 1) {
			parent::polyline($count, $array);
			return;
		}
		for ($i=0; $i<$count*2-2; $i+=2)
			imageline($this->gid, $array[$i], $array[$i + 1], $array[$i + 2], $array[$i + 3], $this->pixel);
	}
	function	polygon($count, $array) {
		imagefilledpolygon($this->gid, $array, $count, $this->pixel);
	}
	function	setfont($name, $class, $attr) {
		$this->fontattr = $attr;
		$this->fontclass = $class;
	}
	function	text($x, $y, $width, $height, $code) {
		global	$global;
		
		if (($array = $global->tcmapdb->findfont($code, 1, $this->fontattr, $this->fontclass)) === null)
			return;
		
		if (($global->req->truecolor != 1)&&($width == $height)) {
			@imagettftext($this->gid, $height * 72 / 96, 0, $x, $y + $height - 1, $this->pixel, $global->systeminfo->fontpath.$array["fontname"], $array["string"]);
			return;
		}
		$size = max($width, $height);
		if ($global->req->truecolor > 1)
			$gid = imagecreatetruecolor($size * 3, $size * 3) or die("imagecreate(text) failed.");
		else
			$gid = imagecreate($size * 3, $size * 3) or die("imagecreate(text) failed.");
		imagecopyresized($gid, $this->gid, 0, 0, $x - $width, $y - $height, $size * 3, $size * 3, $width * 3, $height * 3);
		$pixel = imagecolorresolve($gid, ($this->rgb >> 16) & 0xff, ($this->rgb >> 8) & 0xff, $this->rgb & 0xff);
		@imagettftext($gid, $size * 72 / 96, 0, $size, $size * 2 - 1, $pixel, $global->systeminfo->fontpath.$array["fontname"], $array["string"]);
		imagecopyresampled($this->gid, $gid, $x - $width, $y - $height, 0, 0, $width * 3, $height * 3, $size * 3, $size * 3);
		imagedestroy($gid);
	}
	function	textwidth($width, $height, $code) {
		global	$global;
		
		if ($height == 0)
			return;
		if (($this->fontattr & 0x8000) == 0)
			return $width;
		if (($array = $global->tcmapdb->findfont($code, 1, $this->fontattr, $this->fontclass)) === null)
			return $width;
		$array0 = @imagettfbbox($height * 72 / 96, 0, $global->systeminfo->fontpath.$array["fontname"], "-".$array["string"]."-");
		$array1 = @imagettfbbox($height * 72 / 96, 0, $global->systeminfo->fontpath.$array["fontname"], "--");
		return int(($array0[2] - $array0[0] - ($array1[2] - $array1[0])) * $width / $height);
	}
}

class	genv_gd2overlay extends genv_gd2 {
	function	genv_gd2overlay(&$parent) {
		$this->gid = $parent->gid;
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $null;
		return $this;
	}
	function	close() {
	}
}

class	genv_png extends genv_gd2 {
	function	genv_png() {
		global	$global;
		
		parent::genv_gd2();
		$global->file->extendfilename(".png");
		$global->file->setfiletype("image/png");
	}
	function	close() {
		imagepng($this->gid);
		parent::close();
	}
}

class	genv_jpeg extends genv_gd2 {
	function	genv_jpeg() {
		global	$global;
		
		parent::genv_gd2();
		$global->file->extendfilename(".jpeg");
		$global->file->setfiletype("image/jpeg");
	}
	function	close() {
		imagejpeg($this->gid);
		parent::close();
	}
}

class	genv_pdfimage extends genv {
	var	$parent;
	var	$pattern;
	var	$imageid;
	var	$inpage = 0;
	var	$pageinfo;
	var	$drawsize;
	var	$max_x = 0;
	var	$max_y = 0;
	var	$maxpagenumber = 0;
	function	genv_pdfimage(&$parent) {
		$this->parent =& $parent;
		$this->pattern = array();
		$this->imageid = array();
		$this->drawsize = array();
	}
	function	flushpage() {
		if (($this->inpage)) {
			$this->drawsize[] = array($this->max_x, $this->max_y);
			$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
			$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
			$this->pageinfo[6] = max(1, ceil(($this->max_x - $this->pageinfo[2]) / $pitch_h));
			$this->pageinfo[7] = max(1, ceil(($this->max_y - $this->pageinfo[3]) / $pitch_v));
			$this->pagenumber += $this->pagestep * $this->pageinfo[6] * $this->pageinfo[7];
			$this->maxpagenumber = max($this->maxpagenumber, $this->pagenumber - $this->pagestep);
#printf("pagenumber(%d) maxpagenumber(%d) pageinfo(%d, %d)<BR>\n", $this->pagenumber, $this->maxpagenumber, $this->pageinfo[6], $this->pageinfo[7]);
#			$count = max(1, ceil(($this->max_x - $this->pageinfo[2]) / ($this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4])));
#			$count *= max(1, ceil(($this->max_y - $this->pageinfo[3]) / ($this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5])));
#			$this->maxpagenumber = max($this->maxpagenumber, $this->pagenumber + $this->pagestep * ($count - 1));
#			$this->pagenumber += $this->pagestep * $count;
		}
		$this->max_x = $this->max_y = 0;
		$this->inpage = 0;
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $null;
		$this->flushpage();
		$this->inpage = 1;
		$this->pageinfo = array($width, $height, $margin_l, $margin_t, $margin_r, $margin_b, 1, 1);
		return $this;
	}
	function	close() {
		$this->flushpage();
	}
	function	get_drawsize() {
		return array_shift($this->drawsize);
	}
	function	array2image($array, $jpeg = 0) {
		$gid = genv_gd2::array2bitmap($array);
		$file = tempnam("", "tv");
		if (($jpeg)) {
			imagejpeg($gid, $file);
			$type = "jpeg";
		} else {
			imagepng($gid, $file);
			$type = "png";
		}
		imagedestroy($gid);
		$img = pdf_open_image_file($this->parent->pid, $type, $file, "", 0);
		unlink($file);
		return $img;
	}
	function	bitmap($array, $view, $draw) {
		global	$global;
		
		$this->imageid[] = $this->array2image($array, $global->req->jpeg);
		$this->max_x = max($this->max_x, $view[2]);
		$this->max_y = max($this->max_y, $view[3]);
	}
	function	get_imageid() {
		return array_shift($this->imageid);
	}
	function	setpattern($array) {
		if (array_search($array, $this->pattern, TRUE) !== FALSE)
			return;
		$color = $array[0][0];
		for ($v=0; $v<count($array); $v++) {
			for ($h=0; $h<count($array[$v]); $h++)
				if ($color != $array[$v][$h]) {
					$color = -1;
					break;
				}
			if ($color < 0)
				break;
		}
		if ($color >= 0)
			return;		# single color
		$widearray = $array;
		if (($img = $this->array2image($widearray)) < 0)
			return;
		$width = count($array[0]) * $this->parent->ratio_h;
		$height = count($array) * $this->parent->ratio_v;
		$pat = pdf_begin_pattern($this->parent->pid, $width, $height, $width, $height, 1);
		pdf_save($this->parent->pid);
		pdf_scale($this->parent->pid, $this->parent->ratio_h, $this->parent->ratio_v);
		pdf_place_image($this->parent->pid, $img, 0, 0, 1);
		pdf_restore($this->parent->pid);
		pdf_end_pattern($this->parent->pid);
		$this->pattern[$pat] = $array;
# Dmy-pattern Ny++
		pdf_begin_pattern($this->parent->pid, $width, $height, $width, $height, 1);
		pdf_end_pattern($this->parent->pid);
	}
	function	searchpattern($array) {
		return @array_search($array, $this->pattern, TRUE);
	}
	function	polyline($count, $array) {
		for ($i=0; $i<$count*2; $i+=2) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	polygon($count, $array) {
		for ($i=0; $i<$count*2; $i+=2) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	polybezier($count, $array) {
		for ($i=0; $i<=$count*6; $i+=6) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	filledbezier($count, $array) {
		for ($i=0; $i<=$count*6; $i+=6) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	setfont($name = "", $class = 0x60c6, $attr = 0) {
		$this->parent->setfont($name, $class, $attr);
	}
	function	text($x, $y, $width, $height, $code) {
		$this->max_x = max($this->max_x, $x + $this->parent->textwidth($width, $height, $code));
		$this->max_y = max($this->max_y, $y);
	}
	function	textwidth($width, $height, $code) {
		return $this->parent->textwidth($width, $height, $code);
	}
}


class	pagecount {
	var	$pagecount;
	function	pagecount($count = 0) {
		$this->pagecount = $count;
	}
	function	&getnext() {
		$obj =& new pagecount($this->pagecount + 1);
		return $obj;
	}
}


class	genv_pdf extends genv {
	var	$pid = FALSE;
	var	$pdfimage = null;
	var	$pdfoverlay = null;
	var	$width = 0;
	var	$height = 0;
	var	$font;
	var	$ratio_h = 0.212;
	var	$ratio_v = 0.212;
	var	$fontattr = 0;
	var	$fontclass = 0x60c6;
	var	$inpage = FALSE;
	var	$pageinfo = null;
	var	$discardimagelist;
	var	$pagecount = 1;
	var	$pagecountobj = null;
	var	$jumptolist;
	function	genv_pdf() {
		global	$global;
		
		parent::genv();
		$this->pagecountobj =& new pagecount();
		
		$this->pid = pdf_new() or die("pdf_new failed.");
		$global->systeminfo->setuppdf($this->pid);
		pdf_set_parameter($this->pid, "usercoordinates", "true");
		pdf_open_file($this->pid, "");
		$this->pdfimage =& new genv_pdfimage($this);
		$this->pdfoverlay =& new genv_pdfoverlay($this);
		
		$global->file->extendfilename(".pdf");
		$global->file->setfiletype("application/pdf");
		$this->discardimagelist = array();
	}
	function	setdpi($dpi_h, $dpi_v) {
		$this->ratio_h = 72.0 / $dpi_h;
		$this->ratio_v = 72.0 / $dpi_v;
	}
	function	flushpage() {
		if ($this->inpage === FALSE)
			return;
		pdf_end_template($this->pid);
		$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
		$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
		for ($y=$this->pageinfo[7]-1; $y>=0; $y--)
			for ($x=0; $x<$this->pageinfo[6]; $x++) {
				pdf_begin_page($this->pid, $this->pageinfo[0] * $this->ratio_h, $this->pageinfo[1] * $this->ratio_v);
				
				if ($this->overlay !== null) {
					$this->overlay->drawoverlay($this->pdfoverlay, $this->pagecount++, $this->pagenumber, $this->pdfimage->maxpagenumber);
					$this->pdfoverlay->flushpage();
					$this->pagenumber += $this->pagestep;
				}
				$this->pagecountobj =& $this->pagecountobj->getnext();
				
				pdf_save($this->pid);
				pdf_scale($this->pid, $this->ratio_h, $this->ratio_v);
				pdf_rect($this->pid, $this->pageinfo[2], $this->pageinfo[5], $pitch_h, $pitch_v);
				pdf_clip($this->pid);
				pdf_translate($this->pid, -$pitch_h * $x, -$pitch_v * $y + $this->pageinfo[5]);
				foreach ($this->jumptolist as $a)
					pdf_add_locallink($this->pid, $a[0], $a[1], $a[2], $a[3], $a[4] + 1, "type fitwindow");
				pdf_place_image($this->pid, $this->inpage, 0, 0, 1);
				pdf_restore($this->pid);
				pdf_end_page($this->pid);
			}
		$this->inpage = FALSE;
		while (count($this->discardimagelist) > 0)
			pdf_close_image($this->pid, array_pop($this->discardimagelist));
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $this->pdfimage;
		$this->pdfimage->flushpage();
		$this->flushpage();
		
		$this->pageinfo = array($width, $height, $margin_l, $margin_t, $margin_r, $margin_b, 1, 1);
		$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
		$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
		$drawsize = $this->pdfimage->get_drawsize();
		$this->pageinfo[6] = max(1, ceil(($drawsize[0] - $this->pageinfo[2]) / $pitch_h));
		$this->width = $this->pageinfo[2] + $pitch_h * $this->pageinfo[6];
		$this->pageinfo[7] = max(1, ceil(($drawsize[1] - $this->pageinfo[3]) / $pitch_v));
		$this->height = $this->pageinfo[3] + $pitch_v * $this->pageinfo[7];
		
		$this->inpage = pdf_begin_template($this->pid, $this->width, $this->height);
		pdf_set_parameter($this->pid, "fillrule", "evenodd");
		$this->setfont();
		$this->jumptolist = array();
		return $this;
	}
	function	close() {
		global	$global;
		
		$this->flushpage();
		pdf_close($this->pid);
		$content = pdf_get_buffer($this->pid);
		pdf_delete($this->pid);
		$global->file->putbody($content);
	}
	function	bitmap($array, $view, $draw) {
		if (($img = $this->pdfimage->get_imageid()) < 0)
			return;
		pdf_save($this->pid);
		pdf_concat($this->pid, ($view[2] - $view[0]) / $draw[2], 0, 0, ($view[3] - $view[1]) / $draw[3], $view[0], $this->height - $view[3]);
		pdf_place_image($this->pid, $img, 0, 0, 1);
		$this->discardimagelist[] = $img;
		pdf_restore($this->pid);
	}
	function	setcolor($rgb) {
		pdf_setcolor($this->pid, "both", "rgb", (($rgb >> 16) & 0xff) / 255.0, (($rgb >> 8) & 0xff) / 255.0, ($rgb & 0xff) / 255.0, FALSE);
	}
	function	setpattern($array) {
		if (($pat = $this->pdfimage->searchpattern($array)) === FALSE) {
			parent::setpattern($array);
			return;
		}
		pdf_setcolor($this->pid, "both", "pattern", $pat, FALSE, FALSE, FALSE);
	}
	function	setlattr($width, $pattern) {
		parent::setlattr($width, $pattern);
		$pos = 0;
		$array = array(0, 0);	# $array[0] = length of mark, $array[1] = length of space
		for ($i=0; $i<count($pattern)*8; $i++) {
			$bit = ($pattern[$i >> 3] & (0x80 >> ($i % 8)))? 1 : 0;
			if (($pos & 1) == $bit)
				$array[++$pos] = 0;	# mark <-> space
			$array[$pos] += $width;
		}
		if ($pos <= 1) {
			$mark = $array[0];
			$space = $array[1];
			if ($mark == 0) {
				$this->linewidth = 0;
				$mark = $space = 0;
			} else if ($space == 0)
				$mark = 0;		# normal line
			pdf_setdash($this->pid, $mark, $space);
			return;
		}
		if (($pos & 1) == 0) {
			$array[0] += $array[$pos--];
			array_pop($array);
		} else if (@$array[0] == 0) {
			$array[1] += $array[$pos--];
			$array[0] += $array[$pos--];
			array_pop($array);
			array_pop($array);
		}
		pdf_setpolydash($this->pid, $array);
	}
	function	polyline($count, $array) {
		pdf_save($this->pid);
		pdf_translate($this->pid, ($this->linewidth - 1) / 2, -($this->linewidth - 1) / 2);
		pdf_setlinewidth($this->pid, $this->linewidth);
		for ($i=0; $i<$count*2; $i+=2) {
			$x = $array[$i];
			$y = $this->height - $array[$i + 1];
			if ($i == 0)
				pdf_moveto($this->pid, $x, $y);
			else
				pdf_lineto($this->pid, $x, $y);
		}
		if (($array[0] == $array[$count * 2 - 2])&&($array[1] == $array[$count * 2 - 1]))
			pdf_closepath($this->pid);
		pdf_stroke($this->pid);
		pdf_restore($this->pid);
	}
	function	polygon($count, $array) {
		pdf_save($this->pid);
		for ($i=0; $i<$count*2; $i+=2) {
			$x = $array[$i];
			$y = $this->height - $array[$i + 1];
			if ($i == 0)
				pdf_moveto($this->pid, $x, $y);
			else
				pdf_lineto($this->pid, $x, $y);
		}
		pdf_closepath($this->pid);
		pdf_fill($this->pid);
		pdf_restore($this->pid);
	}
	function	polybezier($count, $array) {
		pdf_save($this->pid);
		pdf_translate($this->pid, ($this->linewidth - 1) / 2, -($this->linewidth - 1) / 2);
		pdf_setlinewidth($this->pid, $this->linewidth);
		$i = 0;
		$x = $array[$i++];
		$y = $this->height - $array[$i++];
		pdf_moveto($this->pid, $x, $y);
		while ($i < $count * 6) {
			$x1 = $array[$i++];
			$y1 = $this->height - $array[$i++];
			$x2 = $array[$i++];
			$y2 = $this->height - $array[$i++];
			$x3 = $array[$i++];
			$y3 = $this->height - $array[$i++];
			if (($x == $x1)&&($y == $y1)&&($x2 == $x3)&&($y2 == $y3))
				pdf_lineto($this->pid, $x3, $y3);
			else
				pdf_curveto($this->pid, $x1, $y1, $x2, $y2, $x3, $y3);
			$x = $x3;
			$y = $y3;
		}
		if (($array[0] == $array[$count * 6 - 4])&&($array[1] == $array[$count * 6 - 3]))
			pdf_closepath($this->pid);
		pdf_stroke($this->pid);
		pdf_restore($this->pid);
	}
	function	filledbezier($count, $array) {
		pdf_save($this->pid);
		$i = 0;
		$x = $array[$i++];
		$y = $this->height - $array[$i++];
		pdf_moveto($this->pid, $x, $y);
		while ($i < $count * 6) {
			$x1 = $array[$i++];
			$y1 = $this->height - $array[$i++];
			$x2 = $array[$i++];
			$y2 = $this->height - $array[$i++];
			$x3 = $array[$i++];
			$y3 = $this->height - $array[$i++];
			if (($x == $x1)&&($y == $y1)&&($x2 == $x3)&&($y2 == $y3))
				pdf_lineto($this->pid, $x3, $y3);
			else
				pdf_curveto($this->pid, $x1, $y1, $x2, $y2, $x3, $y3);
			$x = $x3;
			$y = $y3;
		}
		pdf_closepath($this->pid);
		pdf_fill($this->pid);
		pdf_restore($this->pid);
	}
	function	setfont($name = "", $class = 0x60c6, $attr = 0) {
		global	$global;
		
		$this->fontattr = $attr;
		$this->fontclass = $class;
		$dir = ($this->fontattr & 0x4000)? "-V" : "-H";
		$this->font = pdf_findfont($this->pid, $global->systeminfo->pdffontname, "90ms-RKSJ".$dir, 0) or die("pdf_findfont failed.");
	}
	function	text($x, $y, $width, $height, $code) {
		if ($height == 0)
			return;
		if ($this->ratio_v == 0)
			return;
#		if (($code & 0xff0000) >= 0x220000)
#			$code = 0x212125;
		$string = chr(0x80 | (($code >> 8) & 0xff)).chr(0x80 | ($code & 0xff));
		if (($this->fontattr & 0x8000))
		#	$string = mb_convert_kana($string, "ask", "EUC-JP");
			$string = mb_convert_kana($string, "as", "EUC-JP");
		$string = mb_convert_encoding($string, "SJIS", "EUC-JP");
		if (($this->fontattr & 0x4000)) {
			$x += $width / 2;
			$y -= $height;
		}
		pdf_save($this->pid);
		pdf_concat($this->pid, $width / $height * $this->ratio_h / $this->ratio_v, 0, 0, 1, 0, 0);
		pdf_setfont($this->pid, $this->font, $height);
		pdf_set_text_pos($this->pid, $x * $height / $width, $this->height - $y - ($height - 1));
		pdf_show($this->pid, $string);
		pdf_restore($this->pid);
	}
	function	textwidth($width, $height, $code) {
		$string = chr(0x80 | (($code >> 8) & 0xff)).chr(0x80 | ($code & 0xff));
		if (($this->fontattr & 0x8000))
		#	$string = mb_convert_kana($string, "ask", "EUC-JP");
			$string = mb_convert_kana($string, "as", "EUC-JP");
		return $width * mb_strwidth($string, "EUC-JP") / 2;
	}
	function	&getjumpto() {
		return $this->pagecountobj;
	}
	function	setjumpto($l, $t, $r, $b, &$jumpto) {
		if ($jumpto === null)
			return;
		$this->jumptolist[] = array($l, $this->height - ($b - 1), $r - 1, $this->height - $t, $jumpto->pagecount);
	}
}


class	genv_pdfoverlay extends genv_pdf {
	var	$inpage = 0;
	function	genv_pdfoverlay(&$parent) {
		$this->ratio_h = $parent->ratio_h;
		$this->ratio_v = $parent->ratio_v;
		$this->pid = $parent->pid;
		$this->pdfimage =& new genv_pdfimage($this);
		$this->discardimagelist = array();
	}
	function	flushpage() {
		if ($this->inpage == 0)
			return;
		pdf_restore($this->pid);
		$this->inpage = 0;
		while (count($this->discardimagelist) > 0)
			pdf_close_image($this->pid, array_pop($this->discardimagelist));
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $this->pdfimage;
		$this->pdfimage->flushpage();
		$this->flushpage();
		
		$this->pageinfo = array($width, $height, $margin_l, $margin_t, $margin_r, $margin_b, 1, 1);
		$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
		$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
		$drawsize = $this->pdfimage->get_drawsize();
		$this->width = $width - $margin_r;
		$this->height = $height;
		$this->inpage = 1;
		pdf_save($this->pid);
		pdf_scale($this->pid, $this->ratio_h, $this->ratio_v);
		pdf_rect($this->pid, $this->pageinfo[2], $this->pageinfo[5], $pitch_h, $pitch_v);
		pdf_clip($this->pid);
		pdf_set_parameter($this->pid, "fillrule", "evenodd");
		$this->setfont();
		return $this;
	}
	function	close() {
		$this->flushpage();
	}
}

class	genv_pdf6 extends genv_pdf {
	var	$fontpathlist;
	var	$fontidlist;
	var	$fontkey = "";
	function	genv_pdf6() {
		global	$global;
		
		parent::genv_pdf();
		$this->pdfoverlay =& new genv_pdf6overlay($this);
		$this->fontpathlist = array();
		$this->fontidlist = array();
		pdf_set_parameter($this->pid, "textformat", "utf8");
	}
	function	setdpi($dpi_h, $dpi_v) {
		$this->ratio_h = 72.0 / $dpi_h;
		$this->ratio_v = 72.0 / $dpi_v;
	}
	function	setfont($name = "", $class = 0x60c6, $attr = 0) {
		$this->fontattr = $attr;
		$this->fontclass = $class;
	}
	function	findfont($code) {
		global	$global;
		
		if (($array = $global->tcmapdb->findfont($code, 0, $this->fontattr, $this->fontclass)) === null)
			return null;
		$name = "";
		switch ($array["fonttype"]) {
			default:
				$name = ":".$array["fonttype"];
			case	-1:
				$path = $array["fontname"];
				if (($index = @$this->fontpathlist[$path]) === null) {
					$this->fontpathlist[$path] = $index = "font".count($this->fontpathlist);
					pdf_set_parameter($this->pid, "FontOutline", "{$index}={$global->systeminfo->fontpath}{$path}");
				}
				$name = $index.$name;
				break;
			case	-2:
				$name = $array["fontname"];
				break;
		}
		$this->fontkey = $name."\t".$array["fontcmap"];
		if (($this->font = @$this->fontidlist[$this->fontkey]) === null) {
			$val = pdf_load_font($this->pid, $name, $array["fontcmap"], "") or die("pdf_load_font failed.");
			$this->font = $this->fontlist[$this->fontkey] = $val;
		}
		return $array["string"];
	}
	function	text($x, $y, $width, $height, $code) {
		if ($height == 0)
			return;
		if ($this->ratio_v == 0)
			return;
		if (($string = $this->findfont($code)) === null)
			return;
		if (substr($this->fontkey, -2) == "-V") {
			$x += $width / 2;
			$y -= $height;
		}
		pdf_save($this->pid);
		pdf_concat($this->pid, $width / $height * $this->ratio_h / $this->ratio_v, 0, 0, 1, 0, 0);
		pdf_setfont($this->pid, $this->font, $height);
		pdf_set_text_pos($this->pid, $x * $height / $width, $this->height - $y - ($height - 1));
		pdf_show($this->pid, $string);
		pdf_restore($this->pid);
	}
	function	textwidth($width, $height, $code) {
		if ($height == 0)
			return 0;
		if (($this->fontattr & 0x8000) == 0)
			return $width;
		if (($string = $this->findfont($code)) === null)
			return $width;
		$w = pdf_stringwidth($this->pid, "-".$string."-", $this->font, $height);
		$w -= pdf_stringwidth($this->pid, "--", $this->font, $height);
		return $w * $width / $height * 0.97;
	}
}

class	genv_pdf6overlay extends genv_pdf6 {
	var	$inpage = 0;
	function	genv_pdf6overlay(&$parent) {
		$this->ratio_h = $parent->ratio_h;
		$this->ratio_v = $parent->ratio_v;
		$this->pid = $parent->pid;
		$this->pdfimage =& new genv_pdfimage($this);
		$this->discardimagelist = array();
	}
	function	flushpage() {
		if ($this->inpage == 0)
			return;
		pdf_restore($this->pid);
		$this->inpage = 0;
		while (count($this->discardimagelist) > 0)
			pdf_close_image($this->pid, array_pop($this->discardimagelist));
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $this->pdfimage;
		$this->pdfimage->flushpage();
		$this->flushpage();
		
		$this->pageinfo = array($width, $height, $margin_l, $margin_t, $margin_r, $margin_b, 1, 1);
		$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
		$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
		$drawsize = $this->pdfimage->get_drawsize();
		$this->width = $width - $margin_r;
		$this->height = $height;
		$this->inpage = 1;
		pdf_save($this->pid);
		pdf_scale($this->pid, $this->ratio_h, $this->ratio_v);
		pdf_rect($this->pid, $this->pageinfo[2], $this->pageinfo[5], $pitch_h, $pitch_v);
		pdf_clip($this->pid);
		pdf_set_parameter($this->pid, "fillrule", "evenodd");
		$this->setfont();
		return $this;
	}
	function	close() {
		$this->flushpage();
	}
}


class	genv_pdfzfimage extends genv {
	var	$parent;
	var	$inpage = 0;
	var	$pageinfo;
	var	$drawsize;
	var	$max_x = 0;
	var	$max_y = 0;
	var	$maxpagenumber = 0;
	function	genv_pdfzfimage(&$parent) {
		$this->parent =& $parent;
		$this->drawsize = array();
	}
	function	flushpage() {
		if (($this->inpage)) {
			$this->drawsize[] = array($this->max_x, $this->max_y);
			$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
			$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
			$this->pageinfo[6] = max(1, ceil(($this->max_x - $this->pageinfo[2]) / $pitch_h));
			$this->pageinfo[7] = max(1, ceil(($this->max_y - $this->pageinfo[3]) / $pitch_v));
			$this->pagenumber += $this->pagestep * $this->pageinfo[6] * $this->pageinfo[7];
			$this->maxpagenumber = max($this->maxpagenumber, $this->pagenumber - $this->pagestep);
#printf("pagenumber(%d) maxpagenumber(%d) pageinfo(%d, %d)<BR>\n", $this->pagenumber, $this->maxpagenumber, $this->pageinfo[6], $this->pageinfo[7]);
#			$count = max(1, ceil(($this->max_x - $this->pageinfo[2]) / ($this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4])));
#			$count *= max(1, ceil(($this->max_y - $this->pageinfo[3]) / ($this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5])));
#			$this->maxpagenumber = max($this->maxpagenumber, $this->pagenumber + $this->pagestep * ($count - 1));
#			$this->pagenumber += $this->pagestep * $count;
		}
		$this->max_x = $this->max_y = 0;
		$this->inpage = 0;
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $null;
		$this->flushpage();
		$this->inpage = 1;
		$this->pageinfo = array($width, $height, $margin_l, $margin_t, $margin_r, $margin_b, 1, 1);
		return $this;
	}
	function	close() {
		$this->flushpage();
	}
	function	get_drawsize() {
		return array_shift($this->drawsize);
	}
	function	bitmap($array, $view, $draw) {
		$this->max_x = max($this->max_x, $view[2]);
		$this->max_y = max($this->max_y, $view[3]);
	}
	function	get_imageid() {
		return array_shift($this->imageid);
	}
	function	setpattern($array) {
	}
	function	polyline($count, $array) {
		for ($i=0; $i<$count*2; $i+=2) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	polygon($count, $array) {
		for ($i=0; $i<$count*2; $i+=2) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	polybezier($count, $array) {
		for ($i=0; $i<=$count*6; $i+=6) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	filledbezier($count, $array) {
		for ($i=0; $i<=$count*6; $i+=6) {
			$this->max_x = max($this->max_x, $array[$i]);
			$this->max_y = max($this->max_y, $array[$i + 1]);
		}
	}
	function	setfont($name = "", $class = 0x60c6, $attr = 0) {
		$this->parent->setfont($name, $class, $attr);
	}
	function	text($x, $y, $width, $height, $code) {
		$this->max_x = max($this->max_x, $x + $this->parent->textwidth($width, $height, $code));
		$this->max_y = max($this->max_y, $y);
	}
	function	textwidth($width, $height, $code) {
		return $this->parent->textwidth($width, $height, $code);
	}
}


class	genv_pdfzf extends genv {
	var	$pdf = null;
	var	$font = null;
	var	$pdfimage = null;
	var	$pdfoverlay = null;
	var	$width = 0;
	var	$height = 0;
	var	$ratio_h = 0.212;
	var	$ratio_v = 0.212;
	var	$fontattr = 0;
	var	$fontclass = 0x60c6;
	var	$inpage = null;
	var	$pageinfo = null;
	var	$pagecount = 1;
	var	$fontlist;
	var	$pagecountobj = null;
	function	genv_pdfzf() {
		global	$global;
		
		set_include_path(get_include_path().PATH_SEPARATOR.$global->systeminfo->zfpath);
		require_once('Zend/Loader/Autoloader.php');
		Zend_Loader_Autoloader::getInstance();
		
		parent::genv();
		$this->fontlist = array();
		$this->pagecountobj =& new pagecount();
		
		$this->pdf =& new Zend_Pdf();
		$this->pdfimage =& new genv_pdfzfimage($this);
		$this->pdfoverlay =& new genv_pdfzfoverlay($this);
		
		$global->file->extendfilename(".pdf");
		$global->file->setfiletype("application/pdf");
	}
	function	setdpi($dpi_h, $dpi_v) {
		$this->ratio_h = 72.0 / $dpi_h;
		$this->ratio_v = 72.0 / $dpi_v;
	}
	function	flushpage() {
		if ($this->inpage === null)
			return;
		$this->inpage->restoreGS();
		$null = null;
		$this->inpage =& $null;
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		if (($width == 0)&&($height == 0))
			return $this->pdfimage;
		$this->pdfimage->flushpage();
		$this->flushpage();
		
		$w = floor($width * $this->ratio_h);
		$h = floor($height * $this->ratio_v);
		$this->inpage =& $this->pdf->newPage("{$w}:{$h}:");
		$this->pdf->pages[] =& $this->inpage;
		
		if ($this->overlay !== null) {
			$this->overlay->drawoverlay($this->pdfoverlay, $this->pagecount++, $this->pagenumber, $this->pdfimage->maxpagenumber);
			$this->pdfoverlay->flushpage();
			$this->pagenumber += $this->pagestep;
		}
		$this->pagecountobj =& $this->pagecountobj->getnext();
		
		$this->pageinfo = array($width, $height, $margin_l, $margin_t, $margin_r, $margin_b, 1, 1);
		$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
		$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
		$drawsize = $this->pdfimage->get_drawsize();
		$this->pageinfo[6] = max(1, ceil(($drawsize[0] - $this->pageinfo[2]) / $pitch_h));
		$this->width = $this->pageinfo[2] + $pitch_h * $this->pageinfo[6];
		$this->pageinfo[7] = max(1, ceil(($drawsize[1] - $this->pageinfo[3]) / $pitch_v));
		$this->height = $this->pageinfo[5] + $pitch_v * $this->pageinfo[7];
		
		$this->inpage->saveGS();
		$this->inpage->scale($this->ratio_h, $this->ratio_v);
#		$this->inpage->translate(0, $this->pageinfo[5] + 0);
		
		$this->setfont();
		return $this;
	}
	function	close() {
		global	$global;
		
		$this->flushpage();
		$content = $this->pdf->render();
		$global->file->putbody($content);
	}
	function	&array2image($array, $jpeg = 0) {
		$gid = genv_gd2::array2bitmap($array);
		$oldfile = tempnam("", "tv");
		if (($jpeg)) {
			imagejpeg($gid, $oldfile);
			$type = "jpeg";
		} else {
			imagepng($gid, $oldfile);
			$type = "png";
		}
		imagedestroy($gid);
		
		$file = "{$oldfile}.{$type}";
		rename($oldfile, $file);
		
		$img = Zend_Pdf_Image::imageWithPath($file);
		unlink($file);
		return $img;
	}
	function	bitmap($array, $view, $draw) {
		global	$global;
		
		$img =& $this->array2image($array, $global->req->jpeg);
		$this->inpage->drawImage($img, $view[0], $this->height - $view[3], $view[2], $this->height - $view[1]);
	}
	function	setcolor($rgb) {
		$c =& new Zend_Pdf_Color_Rgb((($rgb >> 16) & 0xff) / 255.0, (($rgb >> 8) & 0xff) / 255.0, ($rgb & 0xff) / 255.0);
		$this->inpage->setLineColor($c);
		$this->inpage->setFillColor($c);
	}
#	function	setpattern($array) {
#		if (($pat = $this->pdfimage->searchpattern($array)) === FALSE) {
#			parent::setpattern($array);
#			return;
#		}
#		pdf_setcolor($this->pid, "both", "pattern", $pat, FALSE, FALSE, FALSE);
#	}
	function	setlattr($width, $pattern) {
		parent::setlattr($width, $pattern);
		$pos = 0;
		$array = array(0, 0);	# $array[0] = length of mark, $array[1] = length of space
		for ($i=0; $i<count($pattern)*8; $i++) {
			$bit = ($pattern[$i >> 3] & (0x80 >> ($i % 8)))? 1 : 0;
			if (($pos & 1) == $bit)
				$array[++$pos] = 0;	# mark <-> space
			$array[$pos] += $width;
		}
		if ($pos <= 1) {
			$mark = $array[0];
			$space = $array[1];
			if ($mark == 0) {
				$this->linewidth = 0;
				$mark = $space = 0;
			} else if ($space == 0)
				$mark = 0;		# normal line
			if ($mark == 0)
				$this->inpage->setLineDashingPattern(Zend_Pdf_Page::LINE_DASHING_SOLID);
			else
				$this->inpage->setLineDashingPattern(array($mark, $space));
			return;
		}
		if (($pos & 1) == 0) {
			$array[0] += $array[$pos--];
			array_pop($array);
		} else if (@$array[0] == 0) {
			$array[1] += $array[$pos--];
			$array[0] += $array[$pos--];
			array_pop($array);
			array_pop($array);
		}
		$this->inpage->setLineDashingPattern($array);
	}
	function	polyline($count, $array) {
		$this->inpage->saveGS();
		$this->inpage->translate(($this->linewidth - 1) / 2, -($this->linewidth - 1) / 2);
		$this->inpage->setLineWidth($this->linewidth);
		$x = array();
		$y = array();
		for ($i=0; $i<$count*2; $i+=2) {
			$x[] = $array[$i];
			$y[] = $this->height - $array[$i + 1];
		}
		$this->inpage->drawPolygon($x, $y, Zend_Pdf_Page::SHAPE_DRAW_STROKE);
		$this->inpage->restoreGS();
	}
	function	polygon($count, $array) {
		$this->inpage->saveGS();
		$x = array();
		$y = array();
		for ($i=0; $i<$count*2; $i+=2) {
			$x[] = $array[$i];
			$y[] = $this->height - $array[$i + 1];
		}
		$this->inpage->drawPolygon($x, $y, Zend_Pdf_Page::SHAPE_DRAW_FILL, Zend_Pdf_Page::FILL_METHOD_EVEN_ODD);
		$this->inpage->restoreGS();
	}
	function	setfont($name = "", $class = 0x60c6, $attr = 0) {
		$this->fontattr = $attr;
		$this->fontclass = $class;
	}
	function	findfont($code) {
		global	$global;
		
		if (($array = $global->tcmapdb->findfont($code, 1, $this->fontattr, $this->fontclass)) === null)
			return null;
		$path = "";
		switch ($array["fonttype"]) {
			default:
				$path = ":".$array["fonttype"];
			case	-1:
				$path = $array["fontname"].$path;
				if (@$this->fontlist[$path] === null)
					$this->fontlist[$path] =& Zend_Pdf_Font::fontWithPath($global->systeminfo->fontpath.$path);
				$this->font =& $this->fontlist[$path];
				break;
		}
		return $array["string"];
	}
	function	text($x, $y, $width, $height, $code) {
		if ($height == 0)
			return;
		if ($this->ratio_v == 0)
			return;
		if (($string = $this->findfont($code)) === null)
			return;
#		if (substr($this->fontkey, -2) == "-V") {
#			$x += $width / 2;
#			$y -= $height;
#		}
		$this->inpage->saveGS();
		$this->inpage->scale($width / $height * $this->ratio_h / $this->ratio_v, 1);
		$this->inpage->setFont($this->font, $height);
		$this->inpage->drawText($string, $x * $height / $width, $this->height - $y - ($height - 1), "UTF-8");
		$this->inpage->restoreGS();
	}
	function	textwidth($width, $height, $code) {
		if ($height == 0)
			return 0;
		if (($this->fontattr & 0x8000) == 0)
			return $width;
		if (($string = $this->findfont($code)) === null)
			return;
#		$this->inpage->setFont($this->font, $height);
#		$w = $this->inpage->getTextWidth($string, "UTF-8");
		$w = Zend_Pdf_Canvas_Abstract::getTextWidth($string, "UTF-8", $this->font, $height);
		return $w * $width / $height * 0.97;
	}
	function	&getjumpto() {
		return $this->pagecountobj;
	}
	function	setjumpto($l, $t, $r, $b, &$jumpto) {
		if ($jumpto === null)
			return;
		
		$l *=  $this->ratio_h;
		$r = ($r - 1) * $this->ratio_h;
		$t *= $this->ratio_v;
		$b = ($b - 1) * $this->ratio_v;
#		$height = ($this->height + $this->pageinfo[5]) * $this->ratio_v;
		$height = $this->height * $this->ratio_v;
		
		$target =& Zend_Pdf_Destination_Fit::create($jumpto->pagecount);
		$this->inpage->attachAnnotation(Zend_Pdf_Annotation_Link::create($l, $height - $b, $r, $height - $t, $target));
	}
}


class	genv_pdfzfoverlay extends genv_pdfzf {
	var	$parent;
	var	$inpage = null;
	function	genv_pdfzfoverlay(&$parent) {
		$this->ratio_h = $parent->ratio_h;
		$this->ratio_v = $parent->ratio_v;
		$this->fontlist =& $parent->fontlist;
		$this->parent =& $parent;
		$this->pdf =& $parent->pdf;
		$this->pdfimage =& new genv_pdfzfimage($this);
	}
	function	flushpage() {
		if ($this->inpage === null)
			return;
		$this->inpage->restoreGS();
		$null = null;
		$this->inpage =& $null;
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		if (($width == 0)&&($height == 0))
			return $this->pdfimage;
		$this->pdfimage->flushpage();
		$this->flushpage();
		
		$this->pageinfo = array($width, $height, $margin_l, $margin_t, $margin_r, $margin_b, 1, 1);
		$pitch_h = $this->pageinfo[0] - $this->pageinfo[2] - $this->pageinfo[4];
		$pitch_v = $this->pageinfo[1] - $this->pageinfo[3] - $this->pageinfo[5];
		$drawsize = $this->pdfimage->get_drawsize();
		$this->width = $width - $margin_r;
		$this->height = $height;
		
		$this->inpage =& $this->parent->inpage;
		$this->inpage->saveGS();
		$this->inpage->scale($this->ratio_h, $this->ratio_v);
#		pdf_rect($this->pid, $this->pageinfo[2], $this->pageinfo[5], $pitch_h, $pitch_v);
#		pdf_clip($this->pid);
		$this->setfont();
		return $this;
	}
	function	close() {
		$this->flushpage();
	}
}


class	genv_svgpattern extends genv {
	var	$parent;
	var	$pattern;
	var	$buffer = "";
	function	genv_svgpattern(&$parent) {
		$this->parent =& $parent;
		$this->pattern = array();
	}
	function	flushpage() {
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $null;
		$this->flushpage();
		return $this;
	}
	function	close() {
		$this->flushpage();
	}
	function	setpattern($array) {
		if (array_search($array, $this->pattern, TRUE) !== FALSE)
			return;
		$color = $array[0][0];
		for ($v=0; $v<count($array); $v++) {
			for ($h=0; $h<count($array[$v]); $h++)
				if ($color != $array[$v][$h]) {
					$color = -1;
					break;
				}
			if ($color < 0)
				break;
		}
		if ($color >= 0)
			return;		# single color
		$key = sprintf("pat%d", count($this->pattern));
		$w = count($array[0]);
		$h = count($array);
		$file = tempnam("", "tv");
		$gid = $this->parent->array2bitmap($array);
		imagepng($gid, $file);
		imagedestroy($gid);
		$data = base64_encode(file_get_contents($file));
		unlink($file);
		$this->buffer .= <<<EOO
<pattern id="{$key}" x="0" y="0" width="{$w}" height="{$h}" patternUnits="userSpaceOnUse">
<image x="0" y="0" width="{$w}" height="{$h}" xlink:href="data:image/png;base64,{$data}" />
</pattern>

EOO;
		$this->pattern[$key] = $array;
	}
	function	searchpattern($array) {
		return @array_search($array, $this->pattern, TRUE);
	}
}

class	genv_svg extends genv {
	var	$svgpattern;
	var	$linestyle = "";
	var	$color = "#000";
	var	$fontattr = 0;
	var	$fontclass = 0x60c6;
	var	$first = 1;
	var	$buffer = "";
	function	genv_svg() {
		global	$global;
		
		parent::genv();
		$this->svgpattern =& new genv_svgpattern($this);
		$global->file->extendfilename(".svg");
		$global->file->setfiletype("image/svg+xml");
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if ($this->first == 0)
			return $null;		# single page only
		if (($width == 0)&&($height == 0))
			return $this->svgpattern;
		$this->first = 0;
		$this->buffer = "";
		$this->creategenv($width, $height);
		if ($this->overlay !== null)
			$this->overlay->drawoverlay(new genv_svgoverlay($this), 1, $this->pagenumber, $this->pagenumber);
		return $this;
	}
	function	creategenv($width, $height) {
		$width += 0;
		$height += 0;
		$this->buffer .= <<<EOO
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN"
 "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg width="{$width}px" height="{$height}px" version="1.1"
 viewBox="0 0 {$width} {$height}" preserveAspectRatio="none"
 xmlns="http://www.w3.org/2000/svg"
 xmlns:xlink="http://www.w3.org/1999/xlink">

<defs>
{$this->svgpattern->buffer}
</defs>

EOO;
	}
	function	close() {
		global	$global;
		
		$this->buffer .= "</svg>";
		$global->file->putbody($this->buffer);
	}
	function	array2bitmap($array) {
		global	$global;
		
		$width = count($array[0]);
		$height = count($array);
		$black = 0;
		$trans = -1;
		if ($global->req->truecolor > 0) {
			$gid = imagecreatetruecolor($width, $height) or die("imagecreate(array) failed.");
			imagealphablending($gid, FALSE);
			$trans = imagecolorallocatealpha($gid, 0, 0, 1, 0x7f);
		} else {
			$gid = imagecreate($width, $height) or die("imagecreate(array) failed.");
			$black = imagecolorallocate($gid, 0, 0, 0);
			$trans = imagecolorallocate($gid, 0, 0, 1);
		}
		imagecolortransparent($gid, $trans);
		for ($y=0; $y<$height; $y++)
			for ($x=0; $x<$width; $x++) {
				if (($rgb = $array[$y][$x]) >= 0) {
					$pixel = imagecolorresolvealpha($gid, ($rgb >> 16) & 0xff, ($rgb >> 8) & 0xff, $rgb & 0xff, 0);
					if ($pixel == $trans)
						$pixel = $black;
				} else if ($trans >= 0)
					$pixel = $trans;
				else
					continue;
				imagesetpixel($gid, $x, $y, $pixel);
			}
		return $gid;
	}
	function	bitmap($array, $view, $draw) {
		global	$global;
		
		$x = $view[0] + 0;
		$y = $view[1] + 0;
		$w = $view[2] - $view[0];
		$h = $view[3] - $view[1];
		$file = tempnam("", "tv");
		$gid = $this->array2bitmap($array);
		if (($global->req->jpeg)) {
			imagejpeg($gid, $file);
			$type = "jpeg";
		} else {
			imagepng($gid, $file);
			$type = "png";
		}
		imagedestroy($gid);
		$data = base64_encode(file_get_contents($file));
		unlink($file);
		$this->buffer .= <<<EOO
<image x="{$x}" y="{$y}" width="{$w}" height="{$h}" xlink:href="data:image/{$type};base64,{$data}" />

EOO;
	}
	function	setcolor($rgb) {
		$this->color = sprintf("#%06x", $rgb);
	}
	function	setpattern($array) {
		if (($pat = $this->svgpattern->searchpattern($array)) === FALSE) {
			parent::setpattern($array);
			return;
		}
		$this->color = "url(#{$pat})";
	}
	function	setlattr($width, $pattern) {
		parent::setlattr($width, $pattern);
		$pos = 0;
		$array = array(0, 0);	# $array[0] = length of mark, $array[1] = length of space
		for ($i=0; $i<count($pattern)*8; $i++) {
			$bit = ($pattern[$i >> 3] & (0x80 >> ($i % 8)))? 1 : 0;
			if (($pos & 1) == $bit)
				$array[++$pos] = 0;	# mark <-> space
			$array[$pos] += $width;
		}
		if ($pos <= 1) {
			$mark = $array[0];
			$space = $array[1];
			if ($mark == 0) {
				$this->linewidth = 0;
				$mark = $space = 0;
			} else if ($space == 0)
				$mark = 0;		# normal line
			$this->linestyle = sprintf('stroke-width="%d"', $width);
			return;
		}
		if (($pos & 1) == 0) {
			$array[0] += $array[$pos--];
			array_pop($array);
		} else if (@$array[0] == 0) {
			$array[1] += $array[$pos--];
			$array[0] += $array[$pos--];
			array_pop($array);
			array_pop($array);
		}
		$this->linestyle = sprintf('stroke-width="%d" stroke-dasharray="%s"', $width, implode(",", $array));
	}
	function	polyline($count, $array) {
		$offset = ($this->linewidth - 1) / 2;
		$cmd = "";
		for ($i=0; $i<$count*2; $i+=2)
			$cmd .= sprintf("%s %d,%d ", (($cmd == "")? "M" : "L"), $array[$i] + $offset, $array[$i + 1] + $offset);
		if (($array[0] == $array[$count * 2 - 2])&&($array[1] == $array[$count * 2 - 1]))
			$cmd .= "z";
		$this->buffer .= <<<EOO
<path stroke="{$this->color}" fill="none" {$this->linestyle}
 d="{$cmd}" />

EOO;
	}
	function	polygon($count, $array) {
		$cmd = "";
		for ($i=0; $i<$count*2; $i+=2)
			$cmd .= sprintf("%s %d,%d ", (($cmd == "")? "M" : "L"), $array[$i], $array[$i + 1]);
		$this->buffer .= <<<EOO
<path fill="{$this->color}" fill-rule="evenodd" d="{$cmd}z" />

EOO;
	}
	function	polybezier($count, $array) {
		$offset = ($this->linewidth - 1) / 2;
		$i = 0;
		$x = $array[$i++] + $offset;
		$y = $array[$i++] + $offset;
		$cmd = sprintf("M %d,%d ", $x, $y);
		while ($i < $count * 6) {
			$x1 = $array[$i++] + $offset;
			$y1 = $array[$i++] + $offset;
			$x2 = $array[$i++] + $offset;
			$y2 = $array[$i++] + $offset;
			$x3 = $array[$i++] + $offset;
			$y3 = $array[$i++] + $offset;
			if (($x == $x1)&&($y == $y1)&&($x2 == $x3)&&($y2 == $y3))
				$cmd .= sprintf("L %d,%d ", $x3, $y3);
			else
				$cmd .= sprintf("C %d,%d,%d,%d,%d,%d ", $x1, $y1, $x2, $y2, $x3, $y3);
			$x = $x3;
			$y = $y3;
		}
		if (($array[0] == $array[$count * 6 - 4])&&($array[1] == $array[$count * 6 - 3]))
			$cmd .= "z";
		$this->buffer .= <<<EOO
<path stroke="{$this->color}" fill="none" {$this->linestyle}
 d="{$cmd}" />

EOO;
	}
	function	filledbezier($count, $array) {
		$i = 0;
		$x = $array[$i++];
		$y = $array[$i++];
		$cmd = sprintf("M %d,%d ", $x, $y);
		while ($i < $count * 6) {
			$x1 = $array[$i++];
			$y1 = $array[$i++];
			$x2 = $array[$i++];
			$y2 = $array[$i++];
			$x3 = $array[$i++];
			$y3 = $array[$i++];
			if (($x == $x1)&&($y == $y1)&&($x2 == $x3)&&($y2 == $y3))
				$cmd .= sprintf("L %d,%d ", $x3, $y3);
			else
				$cmd .= sprintf("C %d,%d,%d,%d,%d,%d ", $x1, $y1, $x2, $y2, $x3, $y3);
			$x = $x3;
			$y = $y3;
		}
		$this->buffer .= <<<EOO
<path fill="{$this->color}" fill-rule="evenodd" d="{$cmd}z" />

EOO;
	}
	function	setfont($name, $class, $attr) {
		$this->fontattr = $attr;
		$this->fontclass = $class;
	}
	function	text($x, $y, $width, $height, $code) {
		if ($height == 0)
			return;
		if (($code & 0xffff8080) != 0x00210000)
			$code = 0x21222e;
		$string = chr(0x80 | (($code >> 8) & 0xff)).chr(0x80 | ($code & 0xff));
		if (($this->fontattr & 0x8000)&&($width * 4 > $height * 3))
			if (mb_strwidth(mb_convert_kana($string, "as", "EUC-JP")) < 2)
				$width /= 2;
		if ($width * 16 < $height)
			return;
		$string = htmlspecialchars(mb_convert_encoding($string, "UTF-8", "EUC-JP"));
		if (($string == "")||($string == " "))
			return;
		$ratio = $width / $height;
		$height += 0;
		$x /= $ratio;
		$y += $height - 1;
		$ratio = sprintf("%.2f", $ratio);
		$this->buffer .= <<<EOO
<text font-size="{$height}" x="{$x}" y="{$y}" transform="scale({$ratio} 1)" fill="{$this->color}">{$string}</text>

EOO;
	}
	function	textwidth($width, $height, $code) {
		if (($code & 0xffff8080) != 0x00210000)
			return $width;
		$string = chr(0x80 | (($code >> 8) & 0xff)).chr(0x80 | ($code & 0xff));
		if (($this->fontattr & 0x8000)&&($width * 4 > $height * 3))
			$string = mb_convert_kana($string, "as", "EUC-JP");
		return $width * mb_strwidth($string, "EUC-JP") / 2;
	}
}

class	genv_svgoverlay extends genv_svg {
	function	genv_svgoverlay(&$parent) {
		$this->buffer =& $parent->buffer;
	}
	function	&newpage($width, $height, $margin_l = 0, $margin_t = 0, $margin_r = 0, $margin_b = 0) {
		$null = null;
		if (($width == 0)&&($height == 0))
			return $null;
		return $this;
	}
	function	close() {
	}
}

#
# bpack
#


class	huftable {
	var	$fs;
	var	$len;
	var	$table;
	function	huftable(&$fs, $maxcode = 14, $sizebit = 4, $opt = -1) {
		$this->fs =& $fs;
		$this->len = array();
		$this->table = array();
		if (($size = $fs->getbit($sizebit)) == 0) {
			$c = $fs->getbit($sizebit);
			for ($code=0; $code<$maxcode; $code++)
				$this->len[$code] = 0;
			for ($i=0; $i<65536; $i++)
				$this->table[$i] = $c;
			return;
		}
		$code = 0;
		while ($code < $size) {
			if (($len = $fs->getbit(3)) == 7) {
				while (($fs->getbit(1)))
					$len++;
			}
# printf("code(%d) len(%d)<BR>\n", $code, $len);
			$this->len[$code++] = $len;
			if ($code == $opt) {
				$i = $fs->getbit(2);
				while ($i-- > 0)
					$this->len[$code++] = 0;
			}
		}
		while ($code < $maxcode)
			$this->len[$code++] = 0;
		
		$this->maketable($maxcode);
	}
	function	maketable($maxcode) {
		$count = array();
		$start = array(1 => 0);
		$weight = array();
		
		foreach ($this->len as $len)
			@$count[$len]++;
		for ($i=1; $i<=16; $i++)
			$start[$i + 1] = $start[$i] + (@$count[$i] << (16 - $i));
		if ($start[17] != 0x10000)
			die("huftable::start[17] not match.");
		
		for ($code=0; $code<$maxcode; $code++) {
			if (($len = $this->len[$code]) == 0)
				continue;
			$i = $start[$len];
			$nextstart = $i + (1 << (16 - $len));
			while ($i < $nextstart)
				$this->table[$i++] = $code;
			$start[$len] = $nextstart;
		}
	}
	function	get() {
		$val = $this->table[$this->fs->pollbit(16)];
		$this->fs->getbit($this->len[$val]);
		return $val;
	}
}


class	huftable_c	extends huftable {
	function	huftable_c(&$fs, $maxcode = 19, $sizebit = 5, $opt = 3) {
		parent::huftable($fs, $maxcode, $sizebit, $opt);
		$len = array();
		
		$sizebit = 9;
		$maxcode = 510;
		if (($size = $fs->getbit($sizebit)) == 0) {
			$c = $fs->getbit($sizebit);
			for ($code=0; $code<$maxcode; $code++)
				$this->len[$code] = 0;
			for ($i=0; $i<65536; $i++)
				$this->table[$i] = $c;
			return;
		}
# printf("size(%d)<BR>\n", $size);
		$code = 0;
		while ($code < $size) {
			switch ($type = $this->get()) {
				default:
					$len[$code++] = $type - 2;
					continue 2;
				case	0:
					$count = 1;
					break;
				case	1:
					$count = $fs->getbit(4) + 3;
					break;
				case	2:
					$count = $fs->getbit(9) + 20;
					break;
			}
			while ($count-- > 0)
				$len[$code++] = 0;
		}
		while ($code < $maxcode)
			$len[$code++] = 0;
		$this->len = $len;
		$this->maketable($maxcode);
	}
}


class	huftable_p	extends huftable {
	function	get() {
		if (($len = parent::get()) <= 1)
			return $len;
		return (1 << ($len - 1)) | $this->fs->getbit($len - 1);
	}
}


class	lzssstream	extends bytestream {
	var	$fs;
	var	$blocksize = 0;
	var	$ptable = null;
	var	$ctable = null;
	var	$buffer;
	var	$rpos = 0;
	var	$wpos = 0;
	function	lzssstream(&$fs) {
		$this->fs =& $fs;
		$this->buffer = array();
	}
	function	ub() {


# TODO: update crc

		$this->pos++;
		if ($this->wpos != $this->rpos) {
			$val = $this->buffer[$this->rpos];
			$this->rpos = ($this->rpos + 1) & 0x1fff;
# printf("[%02x]", $val);
			return $val;
		}
		if ($this->blocksize <= 0) {
			$this->blocksize = $this->fs->getbit(16);
			$this->ctable =& new huftable_c($this->fs);
			$this->ptable =& new huftable_p($this->fs);
		}
		$this->blocksize--;
		if (($val = $this->ctable->get()) <= 255) {
# printf(" ---- ctable(%d)<BR>\n", $val);
			$this->buffer[$this->wpos] = $val;
			$this->rpos = $this->wpos = ($this->wpos + 1) & 0x1fff;
# printf("[%02x]", $val);
			return $val;
		}
		$size = $val - 256 + 3;
#		$x = $this->ptable->get();
# printf(" ---- ctable(%d) ptable(%d)<BR>\n", $val, $x);
#		$cpos = ($this->wpos - 1 + 0x2000 - $x) & 0x1fff;
		$cpos = ($this->wpos - 1 + 0x2000 - $this->ptable->get()) & 0x1fff;
		while ($size-- > 0) {
			$this->buffer[$this->wpos] = $this->buffer[$cpos];
			$this->wpos = ($this->wpos + 1) & 0x1fff;
			$cpos = ($cpos + 1) & 0x1fff;
		}
# printf("rpos(%d) wpos(%d)<BR>\n", $this->rpos, $this->wpos);
		$val = $this->buffer[$this->rpos];
		$this->rpos = ($this->rpos + 1) & 0x1fff;
# printf("[%02x]", $val);
		return $val;
	}
	function	offset($pos = FALSE, $mode = SEEK_SET) {
		switch ($mode) {
			case	SEEK_SET:
				if ($pos === FALSE)
					return $this->pos;
				$pos -= $this->pos;
			case	SEEK_CUR:
				break;
			default:
				die("lzssstream::offset() called.");
		}
		if ($pos < 0)
			die("lzssstream::offset() called.");
		while ($pos-- > 0)
			$this->ub();
		return $this->pos;
	}
	function	getinfostr() {
		return sprintf("lzssstream offset(%08x) in file top(%08x) end(%08x)", $this->pos, $this->top, $this->end);
	}
}


class	bp_ghead {
	var	$headtype;
	var	$checksum;
	var	$version;
	var	$crc;
	var	$nfiles;
	var	$compmethod;
	var	$time;
	var	$filesize;
	var	$orgsize;
	var	$compsize;
	var	$extsize;
	function	bp_ghead(&$fs) {
		$this->headtype = $fs->ub();
		$this->checksum = $fs->ub();
		$this->version = $fs->uh();
		$this->crc = $fs->uh();
		$this->nfiles = $fs->uh();
		$this->compmethod = $fs->uh();
		$this->time = $fs->uw();
		$this->filesize = $fs->uw();
		$this->orgsize = $fs->uw();
		$this->compsize = $fs->uw();
		$this->extsize = $fs->uw();
	}
	function	uw2sum($val) {
		$sum = ($val >> 24) & 0xff;
		$sum += ($val >> 16) & 0xff;
		$sum += ($val >> 8) & 0xff;
		$sum += $val & 0xff;
		return $sum;
	}
	function	calcchecksum() {
		$sum = $this->uw2sum($this->version);
		$sum += $this->uw2sum($this->crc);
		$sum += $this->uw2sum($this->nfiles);
		$sum += $this->uw2sum($this->compmethod);
		$sum += $this->uw2sum($this->time);
		$sum += $this->uw2sum($this->filesize);
		$sum += $this->uw2sum($this->orgsize);
		$sum += $this->uw2sum($this->compsize);
		$sum += $this->uw2sum($this->extsize);
		
		return $sum & 0xff;
	}
	function	checkhead() {
		if ($this->checksum != $this->calcchecksum())
			die("bp_ghead::checksum error");
		if ($this->version != 0x100)
			die("bp_ghead::version error");
		switch ($this->compmethod) {
			default:
				die("bp_ghead::compmethod error");
			case	0:
			case	5:
				break;
		}
		return 1;
	}
	function	debuglog() {
		printf("headtype(%02x)<BR>\n", $this->headtype);
		printf("checksum(%02x)<BR>\n", $this->checksum);
		printf("version(%04x)<BR>\n", $this->version);
		printf("crc(%04x)<BR>\n", $this->crc);
		printf("nfiles(%04x)<BR>\n", $this->nfiles);
		printf("compmethod(%04x)<BR>\n", $this->compmethod);
		printf("time(%08x)<BR>\n", $this->time);
		printf("filesize(%08x)<BR>\n", $this->filesize);
		printf("orgsize(%08x)<BR>\n", $this->orgsize);
		printf("compsize(%08x)<BR>\n", $this->compsize);
		printf("extsize(%08x)<BR>\n", $this->extsize);
	}
}


class	bp_lhead {
	var	$ftype;
	var	$atype;
#	var	$name;
	var	$orgid;
	var	$compmethod;
	var	$orgsize;
	var	$compsize;
	var	$nlink;
	var	$crc;
	var	$fsize;
	var	$offset;
	var	$nrec;
	var	$ltime;
	var	$atime;
#	var	$mtime;
	var	$ctime;
	function	bp_lhead(&$fs) {
		$this->ftype = $fs->uh();
		$this->atype = $fs->uh();
		$this->name = $fs->uhs(20);
		$this->orgid = $fs->uh();
		$this->compmethod = $fs->uh();
		$this->orgsize = $fs->uw();
		$this->compsize = $fs->uw();
		$fs->uhs(4);		# reserved
		$this->nlink = $fs->uh();
		$this->crc = $fs->uh();
		$this->fsize = $fs->uw();
		$this->offset = $fs->uw();
		$this->nrec = $fs->uw();
		$this->ltime = $fs->uw();
		$this->atime = $fs->uw();
		$this->mtime = $fs->uw();
		$this->ctime = $fs->uw();
	}
	function	debuglog() {
		printf("ftype(%04x)<BR>\n", $this->ftype);
		printf("atype(%04x)<BR>\n", $this->atype);
		debugts($this->name);
		printf("orgid(%04x)<BR>\n", $this->orgid);
		printf("compmethod(%04x)<BR>\n", $this->compmethod);
		printf("orgsize(%08x)<BR>\n", $this->orgsize);
		printf("compsize(%08x)<BR>\n", $this->compsize);
		printf("nlink(%04x)<BR>\n", $this->nlink);
		printf("crc(%04x)<BR>\n", $this->crc);
		printf("fsize(%08x)<BR>\n", $this->fsize);
		printf("offset(%08x)<BR>\n", $this->offset);
		printf("nrec(%08x)<BR>\n", $this->nrec);
		printf("ltime(%08x)<BR>\n", $this->ltime);
		printf("atime(%08x)<BR>\n", $this->atime);
		printf("mtime(%08x)<BR>\n", $this->mtime);
		printf("ctime(%08x)<BR>\n", $this->ctime);
	}
}


class	bp_rhead {
	var	$type;
	var	$subtype;
	var	$size;
	function	bp_rhead(&$fs) {
		$this->type = $fs->uh();
		$this->subtype = $fs->uh();
		$this->size = $fs->uw();
	}
}


#
# main
#

if (is_readable($global->systeminfo->fontpath."tcmap.sq2"))
	$global->tcmapdb =& new tcmap_database($global->systeminfo->fontpath."tcmap");
else
	$global->tcmapdb =& new tcmap_nodatabase();

switch ($global->req->output) {
	default:
	case	3:
#		if ($systeminfo->zfpath !== null) {
#			$genv =& new genv_pdfzf();
#			$genv2 =& new genv_pdfzf();
#		} else {
			$genv =& new genv_pdf();
			$genv2 =& new genv_pdf();
#		}
		break;
	case	4:
		if ($systeminfo->zfpath !== null) {
			$genv =& new genv_pdfzf();
			$genv2 =& new genv_pdfzf();
		} else {
			$genv =& new genv_pdf6();
			$genv2 =& new genv_pdf6();
		}
		break;
}


$fs =& new file_bytestream($fn);
for (;;) {
	if (!$fs->remain())
		die("end of file.");
	if (($type = $fs->uh()) < 0xff00)
		continue;
	if (($size = $fs->uh()) == 0xffff)
		$size = $fs->uw();
	$next = $fs->offset() + $size;
	do {
		if ($type != 0xffe7)
			break;
		if ($size < 0x42)
			break;
		
		$fs->offset(24, SEEK_CUR);
		if ($fs->uh() != 0x8000)
			break;
		if ($fs->uh() != 0xc003)
			break;
		if ($fs->uh() != 0x8000)
			break;
		$fs->offset(32, SEEK_CUR);
		
		$len = $fs->uw();
		
		$gh =& new bp_ghead($fs);
#		$gh->debuglog();
		$gh->checkhead();

# TODO: CRC table

		if ($gh->extsize < 4 + 2 * 26)
			die("extsize too small.");
		
		if ($gh->compmethod == 0)
			$ls =& $fs;
		else
			$ls =& new lzssstream($fs);
		$nextpos = $ls->offset() + $gh->extsize;
# printf("pos(%d), nextpos(%d)<BR>\n", $ls->offset(), $nextpos);
		if (($err = $ls->uh()) != 1)
			die("extbuf:".$err);
#		$ls->uh();	# unknown
#		$fn = $ls->uhs(20);
#		debugts($fn);
		
# printf("pos(%d), nextpos(%d)<BR>\n", $ls->offset(), $nextpos);
		
		$ls->offset($nextpos);
		
		$fileinfo = array();
		for ($i=0; $i<$gh->nfiles; $i++) {
			$lh =& new bp_lhead($ls);
			$fi =& new fileinfo();
			$fi->lh =& $lh;
			$fi->name = $lh->name;
			$fi->mtime = $lh->mtime;
			$fileinfo[] =& $fi;
		}
		for ($i=0; $i<$gh->nfiles; $i++) {
			$lh =& $fileinfo[$i]->lh;
#			printf("<BR>\n================ fid:".$i."<BR>\n");
#			$lh->debuglog();
			$robj =& new memory_bytestream();
			for ($j=0; $j<$lh->nrec; $j++) {
				$h =& new bp_rhead($ls);
#				printf("---- record(%d) subtype(%04x) size(%d)", $h->type, $h->subtype, $h->size);
#				flush();
				switch ($h->type) {
					case	0:		# link
						if ($h->size != 52)
							die("link record size:".$size);
						$ls->offset(40, SEEK_CUR);
						
						$li =& new linkinfo();
						$li->fileinfo =& $fileinfo[$ls->uh()];
						$li->attr = $ls->uh();
						$robj->appendlinkinfo($li);
						
						$ls->offset(8, SEEK_CUR);
						
						break;
					case	1:		# TAD
						$robj->append($ls, $h->size & 0x7ffffffe);
						if (($h->size & 1))
							$ls->ub();
						break;
					default:
						$ls->offset($h->size, SEEK_CUR);
						break;
				}
			}
			
			$fi =& $fileinfo[$i];
			$basescope =& new basescopeparts();
			$basescope->factoryfinder->readall($robj);
			
			$basescope->partslist->rewind();
			$fi->basescope =& $basescope;
			$target =& $basescope->partslist->get();
			if ($target === null)
				continue;
			$fi->jumpto =& $genv2->getjumpto();
			$genv2->setdpi($target->pageattrlist->get("dpih"), $target->pageattrlist->get("dpiv"));
			
			$l = $t = $r = $b = 0;
			$basescope->draw($genv2, $l, $t, $r, $b, 0);
			$genv2->flushpage();
		}
		$null = null;
		$genv2 =& $null;
		for ($i=0; $i<$gh->nfiles; $i++) {
			$basescope =& $fileinfo[$i]->basescope;
			$basescope->partslist->rewind();
			$target =& $basescope->partslist->get();
			if ($target === null)
				continue;
			
			$genv->setdpi($target->pageattrlist->get("dpih"), $target->pageattrlist->get("dpiv"));
			
			$basescope->draw($genv);
			$genv->flushpage();
		}
		if (($global->req->binary)) {
			$global->file->extendfilename(".bin");
			$global->file->setfiletype("application/octet-stream");
			$global->file->setdisposition("attachment");
		}
		if ($global->req->output != 0) {
			$global->file->putheader();
			$genv->close();
		}
		die();
	} while (0);
	$fs->offset($next);
}




?>
