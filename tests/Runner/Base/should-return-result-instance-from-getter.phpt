--TEST--
Should return a PHPSpec_Runner_Result instance after calling getResult
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock extends PHPSpec_Runner_Collection {
    public function __construct() {}
}

$base = new PHPSpec_Runner_Base(new Mock);
assert('$base->getResult() instanceof PHPSpec_Runner_Result');

?>
===DONE===
--EXPECT--
===DONE===