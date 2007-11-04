--TEST--
A toString() method will return a formatted string containing any exception details in brief
--FILE--
<?php
require_once dirname(__FILE__) . '/../../../_setup.inc';

/**
 * This is about as Mockist as it gets!
 */

class Mock extends PHPSpec_Runner_Result {
    public function __construct() {}
    public function __toString() { return ''; }
    public function getPasses() { return array(1,2); }
    public function getFailures() { return array(); }
    public function getExceptions() { return array( new Mock2 ); }
    public function getErrors() { return array(); }
    public function count() { return 3; }
}

class Mock2 extends PHPSpec_Runner_Example_Exception {
    public function __construct() {}
    public function getContextDescription() { return 'x'; }
    public function getSpecificationText() { return 'y'; }
    public function getMessage() { return 'z'; }
}

$textReporter = new PHPSpec_Runner_Reporter_Text(new Mock);

echo $textReporter;

?>
===DONE===
--EXPECT--
Finished in 0 seconds

3 examples, 0 passed
===DONE===