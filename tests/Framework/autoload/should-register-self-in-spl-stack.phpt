--TEST--
When included, PHPSpec_Framework::autoload() should register itself in the SPL stack
--FILE--
<?php

// insure something is in the SPL autoload stack
class Foo {
    public static function autoload($class) { }
}
spl_autoload_register(array('Foo', 'autoload'));

$autoload_stack_before = spl_autoload_functions();

// sanity check
assert('!in_array(array("PHPSpec_Framework", "autoload"), $autoload_stack_before)');

require_once dirname(__FILE__) . '/../../../src/PHPSpec/Framework.php';

$autoload_stack_after = spl_autoload_functions();
assert('$autoload_stack_before != $autoload_stack_after');
assert('in_array(array("PHPSpec_Framework", "autoload"), $autoload_stack_after)');

?>
===DONE===
--EXPECT--
===DONE===