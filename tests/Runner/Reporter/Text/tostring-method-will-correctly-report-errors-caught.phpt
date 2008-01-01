--TEST--
A toString() method will return a formatted string containing any error details in brief
--FILE--
<?php
require_once dirname(__FILE__) . '/../../../_setup.inc';

class Mock extends PHPSpec_Runner_Result {
    public function count() { return 3; }
    public function getRuntime() { return 3; }
    public function countErrors() {return 1;}
    public function getTypes($type) { return array(new Mock2); }
}

class Mock2 extends PHPSpec_Runner_Example_Error {
    public function __construct() {}
    public function getContextDescription() { return 'x'; }
    public function getSpecificationText() { return 'y'; }
    public function getMessage() { return 'z'; }
}

$textReporter = new PHPSpec_Runner_Reporter_Text(new Mock);

$textReporter->output();

?>
===DONE===
--EXPECT--
Finished in 3 seconds

3 examples, 0 failures, 1 error

Errors:

1)
'x y' ERROR



===DONE===