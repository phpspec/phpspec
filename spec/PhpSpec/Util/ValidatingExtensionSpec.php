<?php

namespace spec\PhpSpec\Util;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use PhpSpec\ServiceContainer;
use PhpSpec\Matcher\IdentityMatcher;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\Extension\ValidatingExtensionTrait;

class ValidatingExtensionSpec extends ObjectBehavior
{
    function let()
    {
        $this->beAnInstanceOf('spec\PhpSpec\Util\ExampleValidatingExtension');
    }

    function it_is_initializable()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $this->shouldHaveType('spec\PhpSpec\Util\ExampleValidatingExtension');
    }

    function it_should_throw_if_container_is_not_set()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $this->shouldThrow()->duringSet('', null);
    }

    function it_should_validate_unprefixed_service(ServiceContainer $c)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $matcher = new \StdClass();
        $id = 'abc';

        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_service_with_new_prefix(ServiceContainer $c)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $matcher = new \StdClass();
        $id = 'new.service';

        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_callable_service_with_new_prefix(ServiceContainer $c)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $matcher = function (ServiceContainer $c) {
            return new \StdClass();
        };
        $id = 'new.service';

        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_correct_matcher_service(ServiceContainer $c, IdentityMatcher $matcher)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $id = 'matchers.abc';

        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_validate_correct_callable_matcher_service(
        ServiceContainer $c, IdentityMatcher $identityMatcher
    ) {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $matcher = function (ServiceContainer $c) use ($identityMatcher) {
            return $identityMatcher->getWrappedObject();
        };
        $id = 'matchers.abc';

        $c->set($id, $matcher)->shouldBeCalled();

        $this->setContainer($c);
        $this->set($id, $matcher);
    }

    function it_should_not_validate_incorrect_matcher_service(ServiceContainer $c)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $matcher = new \StdClass();
        $id = 'matchers.abc';

        $this->setContainer($c);
        $this->shouldThrow()->duringSet($id, $matcher);
    }

    function it_should_not_validate_incorrect_callable_matcher_service(ServiceContainer $c)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new SkippingException('Traits implemented since PHP 5.4');
        }

        $matcher = function (ServiceContainer $c) {
            return new \StdClass();
        };
        $id = 'matchers.abc';

        $this->setContainer($c);
        $this->shouldThrow()->duringSet($id, $matcher);
    }
}

