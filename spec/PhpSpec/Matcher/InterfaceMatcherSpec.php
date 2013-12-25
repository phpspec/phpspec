<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Exception\Example\FailureException;

use ArrayObject;

class InterfaceMatcherSpec extends ObjectBehavior
{
    /**
     * @param PhpSpec\Formatter\Presenter\PresenterInterface $presenter
     */
    function let($presenter)
    {
        $presenter->presentString(Argument::any())->willReturnArgument();
        $presenter->presentValue(Argument::any())->willReturn('object');

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldImplement('PhpSpec\Matcher\MatcherInterface');
    }

    function it_responds_to_implement()
    {
        $this->supports('implement', '', array(''))->shouldReturn(true);
    }

    /**
     * @param ArrayObject $object
     */
    function it_matches_interface_instance($object)
    {
        $this->shouldNotThrow()
            ->duringPositiveMatch('implement', $object, array('ArrayAccess'));
    }

    /**
     * @param ArrayObject $object
     */
    function it_matches_interface_inheritance_instance($object)
    {
        $this->shouldNotThrow()
            ->duringPositiveMatch('implement', $object, array('Traversable'));
    }

    function it_matches_against_not_implemented_interfaces()
    {
        $this->shouldNotThrow()
            ->duringNegativeMatch('implement', $this, array('SplObserver'));
    }

    /**
     * @param ArrayObject $object
     */
    function it_does_not_match_wrong_interface($object)
    {
        $this->shouldThrow('PhpSpec\Exception\Fracture\InterfaceNotImplementedException')
            ->duringPositiveMatch('implement', $object, array('SplObserver'));
    }

    /**
     * @param ArrayObject $object
     */
    function it_does_not_match_not_expected_interface($object)
    {
        $this->shouldThrow(new FailureException(
            'Expected object to not implement ArrayAccess, but it does.'
        ))->duringNegativeMatch('implement', $object, array('ArrayAccess'));
    }

    /**
     * @param ArrayObject $object
     */
    function it_throws_exception_if_interface_does_not_exist($object)
    {
        $this->shouldThrow('PhpSpec\Exception\Fracture\InterfaceNotFoundException')
            ->duringPositiveMatch('implement', $object, array('PhoneyInterface'));
    }
}
