<?php
$systeminfo->welcomemessage = <<<EOO
<HR>
<P align="right">&copy; <A href="http://www.tad-wg.jp/">TAD Working Group</A>, 2002-2016</P>
<!-- write message here -->
<!-- write message here -->
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

$systeminfo->fontpath = '/var/www/html/fonts/';
$systeminfo->pdffontname = "KozMinPro-Regular-Acro";
$systeminfo->pdflicense = "0";
$systeminfo->zfpath = "/var/www/html/ZendFramework-1.12.18-minimal/library/";

?>
