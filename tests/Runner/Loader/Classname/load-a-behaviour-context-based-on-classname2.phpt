--TEST--
Should load a behaviour context class based on class name from the current directory and create correct Reflection
--FILE--
<?php
require_once dirname(__FILE__) . '/../../../_setup.inc';

$loader = new PHPSpec_Runner_Loader_Classname;
$specs = $loader->load('DescribeStdClass');

assert('$specs[0]->getName() == "DescribeStdClass"');

?>
===DONE===
--EXPECT--
===DONE===