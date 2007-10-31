--TEST--
A toString() method will return a formatted string containing any exception details in brief
--FILE--
<?php
require_once dirname(__FILE__) . '/../../../_setup.inc';

class Mock extends PHPSpec_Runner_Result {
    public function __construct() {}
    public function __toString() { return ''; }
    public function getPasses() { return array(1,2); }
    public function getFailures() { return array(); }
    public function getExceptions() { return array(array( new Mock2, new Exception('z'))); }
    public function count() { return 3; }
}

class Mock2 extends PHPSpec_Runner_Example {
    public function __construct() {}
    public function getContextDescription() { return 'x'; }
    public function getSpecificationText() { return 'y'; }
}

$textReporter = new PHPSpec_Runner_Reporter_Text(new Mock);

echo $textReporter;

?>
===DONE===
--EXPECT--

3 Specs Executed:
2 Specs Passed
x => y => z
DONE
===DONE===