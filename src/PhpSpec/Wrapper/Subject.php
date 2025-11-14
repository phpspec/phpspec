<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Wrapper;

use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Subject\Caller;
use PhpSpec\Wrapper\Subject\SubjectWithArrayAccess;
use PhpSpec\Wrapper\Subject\ExpectationFactory;
use PhpSpec\Util\Instantiator;
use ArrayAccess;

/**
 * @method void shouldHaveType($type)
 * @method void shouldNotHaveType($type)
 * @method void shouldReturnAnInstanceOf($type)
 * @method void shouldNotReturnAnInstanceOf($type)
 * @method void shouldBeAnInstanceOf($type)
 * @method void shouldNotBeAnInstanceOf($type)
 * @method void shouldImplement($interface)
 * @method void shouldNotImplement($interface)
 * @method void shouldBe($value)
 * @method void shouldNotBe($value)
 * @method void shouldBeEqualTo($value)
 * @method void shouldNotBeEqualTo($value)
 * @method void shouldReturn($value)
 * @method void shouldNotReturn($value)
 * @method void shouldEqual($value)
 * @method void shouldNotEqual($value)
 * @method void shouldBeLike($value)
 * @method void shouldNotBeLike($value)
 * @method void shouldHaveCount($count)
 * @method void shouldNotHaveCount($count)
 * @method void shouldBeArray()
 * @method void shouldNotBeArray()
 * @method void shouldBeBool()
 * @method void shouldNotBeBool()
 * @method void shouldBeBoolean()
 * @method void shouldNotBeBoolean()
 * @method void shouldBeCallable()
 * @method void shouldNotBeCallable()
 * @method void shouldBeDouble()
 * @method void shouldNotBeDouble()
 * @method void shouldBeFloat()
 * @method void shouldNotBeFloat()
 * @method void shouldBeInt()
 * @method void shouldNotBeInt()
 * @method void shouldBeInteger()
 * @method void shouldNotBeInteger()
 * @method void shouldBeLong()
 * @method void shouldNotBeLong()
 * @method void shouldBeNull()
 * @method void shouldNotBeNull()
 * @method void shouldBeNumeric()
 * @method void shouldNotBeNumeric()
 * @method void shouldBeObject()
 * @method void shouldNotBeObject()
 * @method void shouldBeReal()
 * @method void shouldNotBeReal()
 * @method void shouldBeResource()
 * @method void shouldNotBeResource()
 * @method void shouldBeScalar()
 * @method void shouldNotBeScalar()
 * @method void shouldBeString()
 * @method void shouldNotBeString()
 * @method void shouldBeNan()
 * @method void shouldNotBeNan()
 * @method void shouldBeFinite()
 * @method void shouldNotBeFinite()
 * @method void shouldBeInfinite()
 * @method void shouldNotBeInfinite()
 * @method void shouldBeApproximately($value, $precision)
 * @method void shouldContain($value)
 * @method void shouldNotContain($value)
 * @method void shouldHaveKeyWithValue($key, $value)
 * @method void shouldNotHaveKeyWithValue($key, $value)
 * @method void shouldHaveKey($key)
 * @method void shouldNotHaveKey($key)
 * @method void shouldStartWith($string)
 * @method void shouldNotStartWith($string)
 * @method void shouldEndWith($string)
 * @method void shouldNotEndWith($string)
 * @method void shouldMatch($regex)
 * @method void shouldNotMatch($regex)
 * @method void shouldIterateAs($iterable)
 * @method void shouldYield($iterable)
 * @method void shouldNotIterateAs($iterable)
 * @method void shouldNotYield($iterable)
 * @method void shouldIterateLike($iterable)
 * @method void shouldYieldLike($iterable)
 * @method void shouldNotIterateLike($iterable)
 * @method void shouldNotYieldLike($iterable)
 * @method void shouldStartIteratingAs($iterable)
 * @method void shouldStartYielding($iterable)
 * @method void shouldNotStartIteratingAs($iterable)
 * @method void shouldNotStartYielding($iterable)
 *
 * @template TKey
 * @template TValue
 * @template-implements ArrayAccess<TKey,TValue>
 */
class Subject implements ArrayAccess, ObjectWrapper
{
    public function __construct(
        private mixed $subject,
        private Wrapper $wrapper,
        private WrappedObject $wrappedObject,
        private Caller $caller,
        private SubjectWithArrayAccess $arrayAccess,
        private ExpectationFactory $expectationFactory
    ) {
    }

    public function beAnInstanceOf(string $className, array $arguments = array()): void
    {
        $this->wrappedObject->beAnInstanceOf($className, $arguments);
    }

    public function beConstructedWith(): void
    {
        $this->wrappedObject->beConstructedWith(\func_get_args());
    }

    public function beConstructedThrough(string|array|callable $factoryMethod, array $arguments = array()): void
    {
        $this->wrappedObject->beConstructedThrough($factoryMethod, $arguments);
    }

    public function getWrappedObject() : mixed
    {
        if ($this->subject) {
            return $this->subject;
        }

        return $this->subject = $this->caller->getWrappedObject();
    }


    public function callOnWrappedObject(string $method, array $arguments = array()): Subject
    {
        return $this->caller->call($method, $arguments);
    }

    public function setToWrappedObject(string $property, mixed $value = null) : void
    {
        $this->caller->set($property, $value);
    }

    public function getFromWrappedObject(string $property) : string|Subject
    {
        return $this->caller->get($property);
    }

    /**
     * @psalm-param TKey $key
     */
    public function offsetExists(mixed $key): bool
    {
        return $this->arrayAccess->offsetExists($key);
    }

    /**
     * @psalm-param TKey $key
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function offsetGet(mixed $key): Subject
    {
        return $this->wrap($this->arrayAccess->offsetGet($key));
    }

    /**
     * @psalm-param TKey $offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->arrayAccess->offsetSet($offset, $value);
    }

    /**
     * @psalm-param TKey $key
     */
    public function offsetUnset(mixed $key): void
    {
        $this->arrayAccess->offsetUnset($key);
    }

    public function __call(string $method, array $arguments = array()) : mixed
    {
        if (0 === strpos($method, 'should')) {
            return $this->callExpectation($method, $arguments);
        }

        if (preg_match('/^beConstructedThrough(?P<method>[0-9A-Z]+)/i', $method, $matches)) {
            return $this->beConstructedThrough(lcfirst($matches['method']), $arguments);
        }

        if (preg_match('/^beConstructed(?P<method>[0-9A-Z]+)/i', $method, $matches)) {
            return $this->beConstructedThrough(lcfirst($matches['method']), $arguments);
        }

        return $this->caller->call($method, $arguments);
    }


    public function __invoke(): Subject
    {
        return $this->caller->call('__invoke', \func_get_args());
    }

    public function __set(string $property, mixed $value = null)
    {
        return $this->caller->set($property, $value);
    }

    public function __get(string $property) : string|Subject
    {
        return $this->caller->get($property);
    }

    private function wrap(mixed $value): Subject
    {
        return $this->wrapper->wrap($value);
    }

    private function callExpectation(string $method, array $arguments) : mixed
    {
        $subject = $this->makeSureWeHaveASubject();

        $expectation = $this->expectationFactory->create($method, $subject, $arguments);

        if (0 === strpos($method, 'shouldNot')) {
            return $expectation->match(lcfirst(substr($method, 9)), $this, $arguments, $this->wrappedObject);
        }

        return $expectation->match(lcfirst(substr($method, 6)), $this, $arguments, $this->wrappedObject);
    }

    private function makeSureWeHaveASubject() : mixed
    {
        if (null === $this->subject && $this->wrappedObject->getClassName()) {
            $instantiator = new Instantiator();
            $className = $this->wrappedObject->getClassName();
            /** @var string $className */
            return $instantiator->instantiate($className);
        }

        return $this->subject;
    }
}
