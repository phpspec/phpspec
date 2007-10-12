--TEST--
Should be able to set a custom PHPSpec_Runner_Result object
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock extends PHPSpec_Runner_Collection
{
    public function __construct(){}
}

class Mock2 extends PHPSpec_Runner_Result
{
    public function __construct(){}
}

$base = new PHPSpec_Runner_Base(new Mock);
$base->setResult(new Mock2);

assert('$base->getResult() instanceof Mock2');

?>
===DONE===
--EXPECT--
===DONE===