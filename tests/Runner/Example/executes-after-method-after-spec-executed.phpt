--TEST--
Executing a spec with a after() method executes that method last
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function after() {
        echo 'after ran';
    }
    public function itShouldBeEmpty(){
        $this->spec(array())->should->beEmpty();
        echo 'spec ran';
    }
}

$ex = new PHPSpec_Runner_Example(new describeEmptyArray, 'itShouldBeEmpty');
$ex->execute();

?>
--EXPECT--
spec ranafter ran