--TEST--
Should load a behaviour context class based on file name with Spec suffix from the current directory
--FILE--
<?php
require_once dirname(__FILE__) . '/../../../_setup.inc';

$loader = new PHPSpec_Runner_Loader_Classname;
$specs = $loader->load('StdClass2Spec.php');

assert('$specs[0] instanceof ReflectionClass');

?>
===DONE===
--EXPECT--
===DONE===