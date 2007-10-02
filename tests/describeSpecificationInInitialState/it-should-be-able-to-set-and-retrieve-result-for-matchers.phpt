--TEST--
Should be able to set and retrieve result for matchers
--FILE--
<?php
require dirname(__FILE__) . '/../_setup.inc';
require_once 'PHPSpec/Specification.php';

class Foo {}

$spec = PHPSpec_Specification::getSpec('Foo');
$spec->setMatcherResult(true);
assert('$spec->getMatcherResult() === true');

?>
===DONE===
--EXPECT--
===DONE===