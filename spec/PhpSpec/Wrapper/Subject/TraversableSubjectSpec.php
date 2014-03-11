<?php

namespace spec\PhpSpec\Wrapper\Subject;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\PresenterInterface;

class TraversableSubjectSpec extends ObjectBehavior
{
    function _it_gets_current_when_handling_array(PresenterInterface $presenter)
    {
        $this->beConstructedWith(array(1,2,3), $presenter);

        $this->current()->shouldReturn(1);
    }

    function _it_gets_current_when_handling_traversable(PresenterInterface $presenter, \AppendIterator $iterator)
    {
        $iterator->current()->willReturn(1);

        $this->beConstructedWith($iterator, $presenter);

        $this->current()->shouldReturn(1);
    }

    function _it_throws_exception_when_wrapped_object_is_not_array_and_not_traversable(PresenterInterface $presenter)
    {
        $this->beConstructedWith('string', $presenter);

        $this->shouldThrow('PhpSpec\Exception\Fracture\InterfaceNotImplementedException')->duringCurrent();
    }

    function _it_checks_valid_when_handling_array(PresenterInterface $presenter)
    {
        $this->beConstructedWith(array(1,2,3), $presenter);

        $this->valid()->shouldReturn(true);
    }

    function _it_calls_valid_when_handling_traversable(PresenterInterface $presenter, \AppendIterator $iterator)
    {
        $iterator->valid()->willReturn(true);

        $this->beConstructedWith($iterator, $presenter);

        $this->valid()->shouldReturn(true);
    }

    function _it_throws_exception_for_valid_when_wrapped_object_is_not_array_and_not_traversable(PresenterInterface $presenter)
    {
        $this->beConstructedWith('string', $presenter);

        $this->shouldThrow('PhpSpec\Exception\Fracture\InterfaceNotImplementedException')->duringValid();
    }

    function _it_gets_next_when_handling_array(PresenterInterface $presenter)
    {
        $this->beConstructedWith(array(1,2,3), $presenter);

        $this->next();
        $this->current()->shouldReturn(2);
    }

    function _it_calls_next_when_handling_traversable(PresenterInterface $presenter, \AppendIterator $iterator)
    {
        $iterator->next()->shouldBeCalled();

        $this->beConstructedWith($iterator, $presenter);

        $this->next();
    }

    function _it_throws_exception_for_next_when_wrapped_object_is_neither_array_nor_traversable(PresenterInterface $presenter)
    {
        $this->beConstructedWith('string', $presenter);

        $this->shouldThrow('PhpSpec\Exception\Fracture\InterfaceNotImplementedException')->duringNext();
    }

    function _it_gets_key_when_handling_array(PresenterInterface $presenter)
    {
        $this->beConstructedWith(array(1,2,3), $presenter);

        $this->key()->shouldReturn(0);
    }

    function _it_calls_key_when_handling_traversable(PresenterInterface $presenter, \AppendIterator $iterator)
    {
        $iterator->key()->willReturn('key');

        $this->beConstructedWith($iterator, $presenter);

        $this->key()->shouldReturn('key');
    }

    function _it_throws_exception_for_key_when_wrapped_object_is_neither_array_nor_traversable(PresenterInterface $presenter)
    {
        $this->beConstructedWith('string', $presenter);

        $this->shouldThrow('PhpSpec\Exception\Fracture\InterfaceNotImplementedException')->duringKey();
    }

    function _it_rewinds_when_handling_array(PresenterInterface $presenter)
    {
        $this->beConstructedWith(array(1,2,3), $presenter);
        $this->next();
        $this->rewind();

        $this->current()->shouldReturn(1);
    }

    function _it_calls_rewind_when_handling_traversable(PresenterInterface $presenter, \AppendIterator $iterator)
    {
        $iterator->rewind()->shouldBeCalled();

        $this->beConstructedWith($iterator, $presenter);

        $this->rewind();
    }

    function _it_throws_exception_for_rewind_when_wrapped_object_is_neither_array_nor_traversable(PresenterInterface $presenter)
    {
        $this->beConstructedWith('string', $presenter);

        $this->shouldThrow('PhpSpec\Exception\Fracture\InterfaceNotImplementedException')->duringRewind();
    }
} 