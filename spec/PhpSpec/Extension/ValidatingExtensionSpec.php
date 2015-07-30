<?php

namespace spec\PhpSpec\Extension;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use PhpSpec\ServiceContainer;
use PhpSpec\Matcher\IdentityMatcher;

class ValidatingExtensionSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Extension\ValidatingExtension');
    }

    function it_should_validate_unprefixed_service(ServiceContainer $c)
    {
        $matcher = new \StdClass();
        $id = 'abc';
        $prefix = '';
        $sid = 'abc';

        $c->getPrefixAndSid($id)->willReturn(array($prefix, $sid));
        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_a_complete_new_service(ServiceContainer $c)
    {
        $matcher = new \StdClass();
        $id = 'new.service';
        $prefix = 'new';
        $sid = 'service';

        $c->getPrefixAndSid($id)->willReturn(array($prefix, $sid));
        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_a_complete_new_service_defined_with_callable(ServiceContainer $c)
    {
        $matcher = function (ServiceContainer $c) {
            return new \StdClass();
        };
        $id = 'new.service';
        $prefix = 'new';
        $sid = 'service';

        $c->getPrefixAndSid($id)->willReturn(array($prefix, $sid));
        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_correct_matcher_service(ServiceContainer $c, IdentityMatcher $matcher)
    {
        $id = 'matchers.abc';
        $prefix = 'matchers';
        $sid = 'abc';

        $c->getPrefixAndSid($id)->willReturn(array($prefix, $sid));
        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_correct_matcher_service_defined_with_callable(
        ServiceContainer $c, IdentityMatcher $identityMatcher
    ) {
        $matcher = function (ServiceContainer $c) use ($identityMatcher) {
            return $identityMatcher->getWrappedObject();
        };
        $id = 'matchers.abc';
        $prefix = 'matchers';
        $sid = 'abc';

        $c->getPrefixAndSid($id)->willReturn(array($prefix, $sid));
        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_not_validate_incorrect_matcher_service(ServiceContainer $c)
    {
        $matcher = new \StdClass();
        $id = 'matchers.abc';
        $prefix = 'matchers';
        $sid = 'abc';

        $c->getPrefixAndSid($id)->willReturn(array($prefix, $sid));

        $this->setContainer($c);
        $this->shouldThrow()->duringSet($id, $matcher);
    }

    function it_should_not_validate_incorrect_matcher_service_defined_with_collable(ServiceContainer $c)
    {
        $matcher = function (ServiceContainer $c) {
            return new \StdClass();
        };
        $id = 'matchers.abc';
        $prefix = 'matchers';
        $sid = 'abc';

        $c->getPrefixAndSid($id)->willReturn(array($prefix, $sid));

        $this->setContainer($c);
        $this->shouldThrow()->duringSet($id, $matcher);
    }
}
