<?php
declare(strict_types = 1);

namespace spec\PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ObjectStateMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn('val1', 'val2');
        $presenter->presentString(Argument::any())->willReturnArgument();

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Matcher\Matcher');
    }

    function it_infers_matcher_alias_name_from_methods_prefixed_with_is()
    {
        $subject = new \ReflectionClass($this);

        $this->supports('beAbstract', $subject, [])->shouldReturn(true);
    }

    function it_throws_exception_if_checker_method_not_found()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldThrow('PhpSpec\Exception\Fracture\MethodNotFoundException')
            ->duringPositiveMatch('beSimple', $subject, []);
    }

    function it_positive_matches_if_state_checker_returns_true()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldNotThrow()->duringPositiveMatch('beUserDefined', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_false()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beFinal', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_null()
    {
        $subject = new class
        {
            public function isMatched()
            {

            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beMatched', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_integer()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return 123;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beMatched', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_float()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return 1.2;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beMatched', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_string()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return '';
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beMatched', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_array()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return [];
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beMatched', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_object()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return $this;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beMatched', $subject, []);
    }

    function it_does_not_positive_match_if_state_checker_returns_resource()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return curl_init();
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('beMatched', $subject, []);
    }

    function it_negative_matches_if_state_checker_returns_false()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldNotThrow()->duringNegativeMatch('beFinal', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_true()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beUserDefined', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_null()
    {
        $subject = new class
        {
            public function isMatched()
            {

            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beMatched', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_integer()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return 123;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beMatched', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_float()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return 1.2;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beMatched', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_string()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return '';
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beMatched', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_array()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return [];
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beMatched', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_object()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return $this;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beMatched', $subject, []);
    }

    function it_does_not_negative_match_if_state_checker_returns_resource()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return curl_init();
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('beMatched', $subject, []);
    }

    function it_infers_matcher_alias_name_from_methods_prefixed_with_has()
    {
        $subject = new \ReflectionClass($this);

        $this->supports('haveProperty', $subject, ['something'])->shouldReturn(true);
    }

    function it_throws_exception_if_has_checker_method_not_found()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldThrow('PhpSpec\Exception\Fracture\MethodNotFoundException')
            ->duringPositiveMatch('haveAnything', $subject, ['str']);
    }

    function it_positive_matches_if_has_checker_returns_true()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldNotThrow()->duringPositiveMatch(
            'haveMethod', $subject, ['it_positive_matches_if_has_checker_returns_true']
        );
    }

    function it_does_not_positive_match_if_has_state_checker_returns_false()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveProperty', $subject, ['other']);
    }

    function it_does_not_positive_match_if_has_checker_returns_null()
    {
        $subject = new class
        {
            public function hasMatch()
            {

            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveMatch', $subject, []);
    }

    function it_does_not_positive_match_if_has_checker_returns_integer()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return 123;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveMatch', $subject, []);
    }

    function it_does_not_positive_match_if_has_checker_returns_float()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return 1.2;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveMatch', $subject, []);
    }

    function it_does_not_positive_match_if_has_checker_returns_string()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return '';
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveMatch', $subject, []);
    }

    function it_does_not_positive_match_if_has_checker_returns_array()
    {
        $subject = new class
        {
            public function hasMatched()
            {
                return [];
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveMatched', $subject, []);
    }

    function it_does_not_positive_match_if_has_checker_returns_object()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return $this;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveMatch', $subject, []);
    }

    function it_does_not_positive_match_if_has_checker_returns_resource()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return curl_init();
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringPositiveMatch('haveMatch', $subject, []);
    }

    function it_negative_matches_if_has_checker_returns_false()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldNotThrow()->duringNegativeMatch(
            'haveMethod', $subject, ['other']
        );
    }

    function it_does_not_negative_match_if_has_state_checker_returns_true()
    {
        $subject = new \ReflectionClass($this);

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch(
                'haveMethod',
                $subject,
                [
                    'it_does_not_negative_match_if_has_state_checker_returns_true'
                ]
            );
    }

    function it_does_not_negative_match_if_has_checker_returns_null()
    {
        $subject = new class
        {
            public function hasMatch()
            {

            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('haveMatch', $subject, []);
    }

    function it_does_not_negative_match_if_has_checker_returns_integer()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return 123;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('haveMatch', $subject, []);
    }

    function it_does_not_negative_match_if_has_checker_returns_float()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return 1.2;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('haveMatch', $subject, []);
    }

    function it_does_not_negative_match_if_has_checker_returns_string()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return '';
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('haveMatch', $subject, []);
    }

    function it_does_not_negative_match_if_has_checker_returns_array()
    {
        $subject = new class
        {
            public function hasMatched()
            {
                return [];
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('haveMatched', $subject, []);
    }

    function it_does_not_negative_match_if_has_checker_returns_object()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return $this;
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('haveMatch', $subject, []);
    }

    function it_does_not_negative_match_if_has_checker_returns_resource()
    {
        $subject = new class
        {
            public function hasMatch()
            {
                return curl_init();
            }
        };

        $this->shouldThrow('PhpSpec\Exception\Example\MethodFailureException')
            ->duringNegativeMatch('haveMatch', $subject, []);
    }

    function it_does_not_match_if_subject_is_callable()
    {
        $subject = function () {};

        $this->supports('beCallable', $subject, [])->shouldReturn(false);
    }

    function it_does_not_throw_when_positive_match_true()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return true;
            }
        };

        $this->positiveMatch('beMatched', $subject, [])->shouldBe(null);
    }

    function it_does_not_throw_when_negative_match_false()
    {
        $subject = new class
        {
            public function isMatched()
            {
                return false;
            }
        };

        $this->negativeMatch('beMatched', $subject, [])->shouldBe(null);
    }
}
