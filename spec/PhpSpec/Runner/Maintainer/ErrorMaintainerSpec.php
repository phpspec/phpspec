<?php

namespace spec\PhpSpec\Runner\Maintainer;

use PhpSpec\Exception\Example as ExampleException;
use PhpSpec\ObjectBehavior;
use Phpspec\Runner\Maintainer\ErrorMaintainer;

class ErrorMaintainerSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(0);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ErrorMaintainer::class);
    }

    function it_returns_false_when_error_suppresed_or_no_error_reporting()
    {
        $oldLevel = error_reporting(0);

        $this->errorHandler(0, 'error message', 'file', 1)->shouldBe(false);

        error_reporting($oldLevel);
    }

    /**
     * In PHP 8 error_reporting() will not return 0 anymore when
     * and error is suppressed with the error control operator "@":
     *
     * @link https://www.php.net/manual/en/language.operators.errorcontrol.php
     */
    function it_returns_false_when_error_suppressed_in_php_8()
    {
        /**
         * Based on manual testing and:
         * @link https://www.php.net/manual/en/language.operators.errorcontrol.php#125938
         */
        $oldLevel = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);

        $this->errorHandler(E_WARNING, 'error message', 'file', 1)->shouldBe(false);

        error_reporting($oldLevel);
    }

    function it_return_true_when_recoverable_level_and_message_match()
    {
        $oldLevel = error_reporting(E_ALL);

        $msg = 'Argument 1 passed to ' . self::class . '::test() must be an instance of string, string given';
        $this->errorHandler(E_RECOVERABLE_ERROR, $msg, 'file', 1)->shouldBe(true);

        error_reporting($oldLevel);
    }

    function it_throws_error_exception_when_message_not_match()
    {
        $oldLevel = error_reporting(E_ALL);

        $this->shouldThrow(ExampleException\ErrorException::class)
            ->during('errorHandler', [E_ERROR, 'error message', 'file', 1]);

        error_reporting($oldLevel);
    }
}
