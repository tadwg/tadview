<?php
$systeminfo->welcomemessage = <<<EOO
<!-- write message here -->
<hr>BTRON Club Special Edition 2013では、図形TAD形式と文章TAD形式のデータ(TAD主レコード)をサポートします。
<!-- write message here -->
<BR>
<BR>
<DIV ALIGN="RIGHT">&#169; TAD Working Group, 2002-2015</DIV>
<!-- write message here -->
EOO;

function hook_start() {
	@ini_set("child_terminate", 1);
#	@apache_child_terminate();
	ini_set("max_execution_time", "600");	# 10 minutes
#	ini_set("memory_limit", "32M");
	ini_set("memory_limit", "1024M");	# for zend framework
	ini_set("ignore_user_abort", "0");	# abort when session disconnect
}

$systeminfo->fontpath = 'C:\Apache2.2\htdocs\tadview\fonts\\';
$systeminfo->pdffontname = "KozMinPro-Regular-Acro";
$systeminfo->pdflicense = "0";
$systeminfo->zfpath = "C:/Apache2.2/htdocs/tadview/ZendFramework-1.11.7-minimal/library/";

?>
