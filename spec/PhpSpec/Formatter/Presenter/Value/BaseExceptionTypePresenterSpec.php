<?php

namespace spec\PhpSpec\Formatter\Presenter\Value;

use PhpSpec\Exception\ErrorException;
use PhpSpec\ObjectBehavior;

class BaseExceptionTypePresenterSpec extends ObjectBehavior
{
    function it_is_a_type_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Value\ExceptionTypePresenter');
    }

    function it_should_support_exceptions()
    {
        $this->supports(new \Exception())->shouldReturn(true);
    }

    function it_should_present_an_exception_as_a_string()
    {
        $this->present(new \Exception('foo'))
            ->shouldReturn('[exc:Exception("foo")]');
    }

    function it_should_present_an_error_as_a_string()
    {
        $this->present(new ErrorException(new \Error('foo')))
            ->shouldReturn('[err:Error("foo")]');
    }

    function it_should_present_a_parse_error_with_file_and_line_number()
    {
        $this->present(new ErrorException(new class() extends \ParseError {
            public function __construct()
            {
                $this->message = 'Something is not correct';
                $this->file = '/app/some/file.php';
                $this->line = 42;
            }
        }))
            ->shouldContain('Something is not correct in "/app/some/file.php" on line 42');
    }
}
