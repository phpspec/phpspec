--TEST--
Phpt runner should pass this specification description
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class Foo {
    public $member = 1;
}
$foo = new Foo;

describe($foo)->member->should()->be()->greaterThan(0);

?>
--EXPECT--
PASS