--TEST--
A toString() method will return a formatted string based on Result object
--FILE--
<?php
require_once dirname(__FILE__) . '/../../../_setup.inc';

class Mock extends PHPSpec_Runner_Result {
    public function count() { return 3; }
    public function getRuntime() { return 3; }

}

$textReporter = new PHPSpec_Runner_Reporter_Text(new Mock);

$textReporter->output();

?>
===DONE===
--EXPECT--
Finished in 3 seconds

3 examples, 0 failures


===DONE===