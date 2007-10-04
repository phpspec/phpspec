--TEST--
Phpt runner should fail this specification description (normally the EXPECT section must hold "PASS" text).
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class Foo {
    public $member = 1;
}
$foo = new Foo;

describe($foo)->member->should()->be()->greaterThan(1);

?>
--EXPECT--
expected greater than 1, got 1 (using greaterThan())