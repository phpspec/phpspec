--TEST--
getFileName() should return the file path to the Context class
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class describeBoo extends PHPSpec_Context {
}

$context = new describeBoo;

//$filepath = dirname(__FILE__) . '/should-get-file-path-for-current-context-class.php';
$fp = __FILE__;
assert('$context->getFileName() == $fp');

?>
===DONE===
--EXPECT--
===DONE===