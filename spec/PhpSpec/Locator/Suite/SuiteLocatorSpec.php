<?php

namespace spec\PhpSpec\Locator\Suite;

use PhpSpec\ObjectBehavior;
use PhpSpec\Util\Filesystem;

use SplFileInfo;

class SuiteLocatorSpec extends ObjectBehavior
{
    private $srcPath;
    private $specPath;

    function let(Filesystem $fs)
    {
        $this->srcPath = realpath(__DIR__ . '/../../../../src');
        $this->specPath = realpath(__DIR__ . '/../../../../');
    }

    function it_is_a_locator()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Locator\ResourceLocatorInterface');
    }

    function its_priority_is_zero()
    {
        $this->getPriority()->shouldReturn(0);
    }


    function it_supports_queries_that_use_suite_name(Filesystem $fs, SplFileInfo $file)
    {
        $this->beConstructedWith('SuiteName', '', 'spec', dirname(__DIR__), __DIR__, $fs);

        $this->supportsQuery('SuiteName')->shouldBe(true);
    }

    function it_returns_an_empty_array_when_suite_name_does_not_exist(Filesystem $fs, SplFileInfo $file)
    {
        $this->beConstructedWith('SuiteName', '', 'spec', dirname(__DIR__), __DIR__, $fs);

        $this->supportsQuery('OtherName')->shouldBe(false);
    }
}