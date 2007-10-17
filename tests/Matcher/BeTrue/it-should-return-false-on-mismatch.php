<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

$true = new PHPSpec_Matcher_BeTrue(true);
assert('!$true->matches(false)');

?>
===DONE===
