--TEST--
Phpt runner should fail this specification description
--FILE--
<?php
require_once dirname(__FILE__) . '/../_setup.inc';

class Foo {
    public $member = 1;
}
$foo = new Foo;

describe($foo)->member->should()->be()->greaterThan(1);

?>
===DONE===
--EXPECT--
===DONE===