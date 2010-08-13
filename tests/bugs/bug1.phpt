--TEST--
Issue 1: Static function Runner:run() uses $this
--FILE--
<?php
require_once 'PHPSpec.php';

$options = array('recursive' => true,
                 'specdocs' => true,
                 'reporter' => 'html');
PHPSpec_Runner::run($options);
?>
--EXPECTREGEX--
.html.*