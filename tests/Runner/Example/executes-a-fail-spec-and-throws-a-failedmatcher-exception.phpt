--TEST--
Executes an passing specification without throwing any exceptions
--FILE--
<?php
require_once dirname(__FILE__) . '/../../_setup.inc';

class describeEmptyArray extends PHPSpec_Context
{
    public function itShouldBeEmpty(){
        $this->spec(array())->shouldNot->beEmpty();
    }
}

try {
    $ex = new PHPSpec_Runner_Example(new describeEmptyArray, 'itShouldBeEmpty');
    $ex->execute();
    assert(false);
} catch(PHPSpec_Runner_FailedMatcherException $e) {
    assert(true);
} catch(Exception $e) {
    assert(false);
}


?>
===DONE===
--EXPECT--
===DONE===