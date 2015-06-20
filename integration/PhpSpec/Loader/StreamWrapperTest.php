<?php

namespace integration\PhpSpec\Loader;

use PhpSpec\Loader\StreamWrapper;
use PhpSpec\Loader\Transformer\TypeHintRewriter;

class StreamWrapperTest extends \PHPUnit_Framework_Testcase
{
    function setUp()
    {
        StreamWrapper::register(array(
            new TypeHintRewriter()
        ));
    }

    /**
     * @test
     */
    function it_loads_a_spec_with_no_typehints()
    {
        require 'phpspec://'.__DIR__.'/examples/ExampleSpec.php';

        $reflection = new \ReflectionClass('integration\PhpSpec\Loader\examples\ExampleSpec');
        $method = $reflection->getMethod('it_requires_a_stdclass');
        $parameters = $method->getParameters();

        $this->assertNull($parameters[0]->getClass());
    }
}
