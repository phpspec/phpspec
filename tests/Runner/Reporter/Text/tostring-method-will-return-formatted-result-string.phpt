--TEST--
A toString() method will return a formatted string based on Result object
--FILE--
<?php
require_once dirname(__FILE__) . '/../../../_setup.inc';

class Mock extends PHPSpec_Runner_Result {
    public function __construct() {}
    public function __toString() { return ''; }
    public function getPasses() { return array(1,2,3); }
    public function getFailures() { return array(); }
    public function count() { return 3; }
}

$textReporter = new PHPSpec_Runner_Reporter_Text(new Mock);

echo $textReporter;

?>
===DONE===
--EXPECT--
Finished in 0 seconds

3 examples, 0 passed
===DONE===