--TEST--
Should initialise with a Collection object and object should be retrievable
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class Mock extends PHPSpec_Runner_Collection
{
    public function __construct(){}
}

$base = new PHPSpec_Runner_Base(new Mock);

assert('$base->getCollection() instanceof PHPSpec_Runner_Collection');

?>
===DONE===
--EXPECT--
===DONE===