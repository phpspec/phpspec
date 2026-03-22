<?php

use PhpSpec\Mock\Double;

interface DoubleSpecContract {
    public function doSomething(): string;
}

class DoubleSpecClass {
    public function greet(): string { return "hello"; }
    public function noReturn() {}
    public function nullableReturn(): ?string { return null; }
    public function unionReturn(): string|int { return ''; }
    public function nullableUnionReturn(): string|null { return null; }
}

class DoubleSpecClassWithDeps {
    public function __construct(private DoubleSpecContract $dep, private string $name) {}
    public function getDep(): DoubleSpecContract { return $this->dep; }
}

interface DoubleSpecNullableContract {
    public function find(int $id): ?string;
    public function process(string|int $value): string|int;
}

class DoubleSpecClassWithNullableDep {
    public function __construct(private ?DoubleSpecContract $dep) {}
}

enum DoubleSpecStatus {
    case Active;
    case Inactive;
}

interface DoubleSpecEnumContract {
    public function getStatus(): DoubleSpecStatus;
}

class DoubleSpecClassWithEnumDep {
    public function __construct(private DoubleSpecStatus $status) {}
    public function getStatus(): DoubleSpecStatus { return $this->status; }
}

class DoubleSpecPrivateConstructor {
    private function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
}

class DoubleSpecUntypedDep {
    public function __construct(private $value) {}
}

class DoubleSpecScalarDeps {
    public function __construct(
        private int $count,
        private float $rate,
        private bool $active,
        private \Closure $fn,
        private mixed $anything
    ) {}
}

class DoubleSpecUnionDep {
    public function __construct(private string|int $value) {}
}

interface DoubleSpecFloatReturn {
    public function getRate(): float;
}

interface DoubleSpecIntReturn {
    public function getCount(): int;
}

interface DoubleSpecBoolReturn {
    public function isActive(): bool;
}

interface DoubleSpecArrayReturn {
    public function getItems(): array;
}

interface DoubleSpecMixedReturn {
    public function getData(): mixed;
}

interface DoubleSpecVariadic {
    public function log(string $message, string ...$context): void;
}

interface DoubleSpecConcreteReturn {
    public function getObject(): \stdClass;
}

interface DoubleSpecNeverReturn {
    public function crash(): never;
}

interface DoubleSpecVoidReturn {
    public function doWork(): void;
}

interface DoubleSpecNullableUnionReturn {
    public function find(): string|null;
}

interface DoubleSpecMultiScalarReturn {
    public function getName(): string;
    public function getCount(): int;
    public function getRate(): float;
    public function isActive(): bool;
    public function getItems(): array;
}

interface DoubleSpecIntersectA {}
interface DoubleSpecIntersectB {}

class DoubleSpecIntersectionDep {
    public function __construct(private DoubleSpecIntersectA&DoubleSpecIntersectB $dep) {}
}

class DoubleSpecUnionClassDep {
    public function __construct(private DoubleSpecContract|\stdClass $dep) {}
}

interface DoubleSpecClassUnionReturn {
    public function getItem(): DoubleSpecContract|\stdClass;
}

interface DoubleSpecClassInUnionReturn {
    public function get(): DoubleSpecContract|int;
}

interface DoubleSpecIntersectionReturn {
    public function get(): DoubleSpecIntersectA&DoubleSpecIntersectB;
}

interface DoubleSpecDeep4 {
    public function leaf(): string;
}
interface DoubleSpecDeep3 {
    public function next(): DoubleSpecDeep4;
}
interface DoubleSpecDeep2 {
    public function next(): DoubleSpecDeep3;
}
interface DoubleSpecDeep1 {
    public function next(): DoubleSpecDeep2;
}
interface DoubleSpecDeepRoot {
    public function next(): DoubleSpecDeep1;
}

class DoubleSpecOptionalParam {
    public function __construct(private string $required, private string $optional = 'default') {}
}

class DoubleSpecThrowableDep {
    public function __construct(private \Throwable $dep) {}
    public function getDep(): \Throwable { return $this->dep; }
}

class DoubleSpecUnionNullDep {
    public function __construct(private string|null $value) {}
}

trait DoubleSpecTrait {
    public function helper(): string { return 'help'; }
}

class DoubleSpecWithStackProperty {
    protected string $stack = '';
    public function push(string $item): void { $this->stack .= $item; }
    public function getStack(): string { return $this->stack; }
}

final class DoubleSpecFinalClass {
    public function greet(): string { return 'hello'; }
}

readonly class DoubleSpecReadonlyClass {
    public function __construct(public string $name) {}
}

describe(Double::class, function() {

    it("creates a double of an interface", function() {
        $double = Double::getInstance(DoubleSpecContract::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecContract::class);
    });

    it("creates a double of a class", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecClass::class);
    });

    it("tracks method calls", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $double->greet();
        $stack = $double->______PhpSpecGetStubbedCalls();
        expect($stack->exist("greet", []))->toBe(true);
    });

    it("returns a MatchableDouble for methods without return type", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $result = $double->noReturn();
        expect($result)->toBeAnInstanceOf(\PhpSpec\Mock\MatchableDouble::class);
    });

    it("knows the name of the class being doubled", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        expect($double->______PhpSpecNameOfClassDoubled())->toBe("\\DoubleSpecClass");
    });

    it("auto-resolves constructor dependencies", function() {
        $double = Double::getInstance(DoubleSpecClassWithDeps::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecClassWithDeps::class);
    });

    it("is usable via the mock() function", function() {
        $double = mock(DoubleSpecContract::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecContract::class);
    });

    it("stubs return value with toReturn on no-return-type method", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $double->noReturn()->toReturn('stubbed');
        $result = $double->noReturn();
        expect($result)->toBe('stubbed');
    });

    it("stubs return value with toReturn on interface-return method", function(DoubleSpecContract $dep) {
        $double = Double::getInstance(DoubleSpecClassWithDeps::class);
        $double->getDep()->toReturn($dep);
        $result = $double->getDep();
        expect($result)->toBe($dep);
    });

    it("stubs scalar return value via allow()", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        allow($double->greet())->toReturn('world');
        $result = $double->greet();
        expect($result)->toBe('world');
    });

    it("stubs exception with toThrow on no-return-type method", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $double->noReturn()->toThrow(\RuntimeException::class, 'boom');
        expect(fn() => $double->noReturn())->toThrow(\RuntimeException::class, 'boom');
    });

    it("stubs exception via allow()->toThrow()", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        allow($double->greet())->toThrow(\InvalidArgumentException::class, 'bad');
        expect(fn() => $double->greet())->toThrow(\InvalidArgumentException::class, 'bad');
    });

    it("counts method calls", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $double->greet();
        $double->greet();
        $stack = $double->______PhpSpecGetStubbedCalls();
        expect($stack->countCallsTo('greet'))->toBe(2);
    });

    it("handles nullable return types", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $result = $double->nullableReturn();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBeNull();
    });

    it("handles union return types", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $result = $double->unionReturn();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBe('');
    });

    it("handles nullable union return types", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $result = $double->nullableUnionReturn();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBeNull();
    });

    it("creates doubles of interfaces with nullable return types", function() {
        $double = Double::getInstance(DoubleSpecNullableContract::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecNullableContract::class);
        $result = $double->find(1);
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBeNull();
    });

    it("creates doubles of interfaces with union param types", function() {
        $double = Double::getInstance(DoubleSpecNullableContract::class);
        $result = $double->process('hello');
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBe('');
    });

    it("auto-resolves nullable constructor dependencies", function() {
        $double = Double::getInstance(DoubleSpecClassWithNullableDep::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecClassWithNullableDep::class);
    });

    it("handles enum return types", function() {
        $double = Double::getInstance(DoubleSpecEnumContract::class);
        $result = $double->getStatus();
        expect($result)->toBe(DoubleSpecStatus::Active);
    });

    it("auto-resolves enum constructor dependencies", function() {
        $double = Double::getInstance(DoubleSpecClassWithEnumDep::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecClassWithEnumDep::class);
        expect($double->getStatus())->toBe(DoubleSpecStatus::Active);
    });

    it("throws when mock() is called with non-existent class", function() {
        expect(fn() => mock('NonExistentClass12345'))->toThrow(
            \InvalidArgumentException::class,
            "Cannot create mock: class or interface 'NonExistentClass12345' does not exist. Make sure the class is autoloaded or the file is included."
        );
    });

    it("stubs return with callback via allow()->toReturnUsing()", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        allow($double->greet())->toReturnUsing(fn(...$args) => 'dynamic');
        expect($double->greet())->toBe('dynamic');
    });

    it("registers doubles in the static registry", function() {
        $double = Double::getInstance(DoubleSpecContract::class);
        $className = get_class($double);
        expect(isset(Double::$doubleRegistry[$className]))->toBe(true);
    });

    it("handles private constructor", function() {
        $double = Double::getInstance(DoubleSpecPrivateConstructor::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecPrivateConstructor::class);
    });

    it("handles untyped constructor parameter", function() {
        $double = Double::getInstance(DoubleSpecUntypedDep::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecUntypedDep::class);
    });

    it("handles scalar constructor dependencies", function() {
        $double = Double::getInstance(DoubleSpecScalarDeps::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecScalarDeps::class);
    });

    it("handles union type constructor dependency", function() {
        $double = Double::getInstance(DoubleSpecUnionDep::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecUnionDep::class);
    });

    it("handles float return type", function() {
        $double = Double::getInstance(DoubleSpecFloatReturn::class);
        $result = $double->getRate();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBeOfType('float');
    });

    it("handles int return type", function() {
        $double = Double::getInstance(DoubleSpecIntReturn::class);
        $result = $double->getCount();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBe(0);
    });

    it("handles bool return type", function() {
        $double = Double::getInstance(DoubleSpecBoolReturn::class);
        $result = $double->isActive();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBe(false);
    });

    it("handles array return type", function() {
        $double = Double::getInstance(DoubleSpecArrayReturn::class);
        $result = $double->getItems();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBe([]);
    });

    it("handles mixed return type", function() {
        $double = Double::getInstance(DoubleSpecMixedReturn::class);
        $result = $double->getData();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBeNull();
    });

    it("handles variadic parameters", function() {
        $double = Double::getInstance(DoubleSpecVariadic::class);
        $double->log('test', 'ctx1', 'ctx2');
        $stack = $double->______PhpSpecGetStubbedCalls();
        expect($stack->exist("log", ['test', 'ctx1', 'ctx2']))->toBe(true);
    });

    it("handles methods returning concrete classes", function() {
        $double = Double::getInstance(DoubleSpecConcreteReturn::class);
        $result = $double->getObject();
        expect($result)->toBeAnInstanceOf(\stdClass::class);
    });

    it("stubs concrete-class return via allow()->toReturn()", function() {
        $double = Double::getInstance(DoubleSpecConcreteReturn::class);
        $obj = new \stdClass();
        $obj->name = 'test';
        allow($double->getObject())->toReturn($obj);
        expect($double->getObject()->name)->toBe('test');
    });

    it("stubs concrete-class return via allow()->toReturnUsing()", function() {
        $double = Double::getInstance(DoubleSpecConcreteReturn::class);
        allow($double->getObject())->toReturnUsing(fn() => (object)['x' => 42]);
        expect($double->getObject()->x)->toBe(42);
    });

    it("stubs exception with toThrow on interface-return method", function() {
        $double = Double::getInstance(DoubleSpecClassWithDeps::class);
        $double->getDep()->toThrow(\RuntimeException::class, 'dep-error');
        expect(fn() => $double->getDep())->toThrow(\RuntimeException::class, 'dep-error');
    });

    it("handles never return type", function() {
        $double = Double::getInstance(DoubleSpecNeverReturn::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecNeverReturn::class);
        expect(fn() => $double->crash())->toThrow(\RuntimeException::class);
    });

    it("handles void return type", function() {
        $double = Double::getInstance(DoubleSpecVoidReturn::class);
        $double->doWork();
        $stack = $double->______PhpSpecGetStubbedCalls();
        expect($stack->exist("doWork", []))->toBe(true);
    });

    it("handles nullable union return type on interface", function() {
        $double = Double::getInstance(DoubleSpecNullableUnionReturn::class);
        $result = $double->find();
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        expect($result)->toBeNull();
    });

    it("handles multi-scalar return interface", function() {
        $double = Double::getInstance(DoubleSpecMultiScalarReturn::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecMultiScalarReturn::class);
    });

    it("handles intersection type constructor dependency", function() {
        // The mock of a single interface won't satisfy A&B, so construction errors
        expect(fn() => Double::getInstance(DoubleSpecIntersectionDep::class))->toThrow(\TypeError::class);
    });

    it("handles union class constructor dependency", function() {
        $double = Double::getInstance(DoubleSpecUnionClassDep::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecUnionClassDep::class);
    });

    it("handles union return type with only classes (no scalars)", function() {
        $double = Double::getInstance(DoubleSpecClassUnionReturn::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecClassUnionReturn::class);
    });

    it("handles union return type with class and scalar", function() {
        $double = Double::getInstance(DoubleSpecClassInUnionReturn::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecClassInUnionReturn::class);
    });

    it("handles intersection return type", function() {
        $double = Double::getInstance(DoubleSpecIntersectionReturn::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecIntersectionReturn::class);
    });

    it("handles deep nesting hitting depth limit", function() {
        $double = Double::getInstance(DoubleSpecDeepRoot::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecDeepRoot::class);
    });

    it("handles optional constructor parameters", function() {
        $double = Double::getInstance(DoubleSpecOptionalParam::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecOptionalParam::class);
    });

    it("handles Throwable constructor dependency", function() {
        $double = Double::getInstance(DoubleSpecThrowableDep::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecThrowableDep::class);
    });

    it("handles union-null syntax in constructor dependency", function() {
        $double = Double::getInstance(DoubleSpecUnionNullDep::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecUnionNullDep::class);
    });

    it("stubs return using callback on interface-return method via allow()", function(DoubleSpecContract $dep) {
        $double = Double::getInstance(DoubleSpecClassWithDeps::class);
        allow($double->getDep())->toReturnUsing(fn() => $dep);
        expect($double->getDep())->toBe($dep);
    });

    it("stubs exception on interface-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecClassWithDeps::class);
        allow($double->getDep())->toThrow(\LogicException::class, 'dep-fail');
        expect(fn() => $double->getDep())->toThrow(\LogicException::class, 'dep-fail');
    });

    it("stubs callback on void-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $called = false;
        allow($double->noReturn())->toReturnUsing(function() use (&$called) {
            $called = true;
            return 'called';
        });
        $result = $double->noReturn();
        expect($called)->toBeTrue();
        expect($result)->toBe('called');
    });

    it("stubs return using callback on string-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        allow($double->greet())->toReturnUsing(fn() => 'dynamic-hello');
        expect($double->greet())->toBe('dynamic-hello');
    });

    it("stubs exception on string-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        allow($double->greet())->toThrow(\RuntimeException::class, 'greet-fail');
        expect(fn() => $double->greet())->toThrow(\RuntimeException::class, 'greet-fail');
    });

    it("stubs return value on nullable-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecNullableContract::class);
        allow($double->find(1))->toReturn('found');
        expect($double->find(1))->toBe('found');
    });

    it("stubs return value on union-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecNullableContract::class);
        allow($double->process('test'))->toReturn(42);
        expect($double->process('test'))->toBe(42);
    });

    it("stubs exception on enum-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecEnumContract::class);
        allow($double->getStatus())->toThrow(\RuntimeException::class, 'enum-fail');
        expect(fn() => $double->getStatus())->toThrow(\RuntimeException::class, 'enum-fail');
    });

    it("stubs exception on int-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecIntReturn::class);
        allow($double->getCount())->toThrow(\RuntimeException::class, 'int-fail');
        expect(fn() => $double->getCount())->toThrow(\RuntimeException::class, 'int-fail');
    });

    it("stubs return value on bool-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecBoolReturn::class);
        allow($double->isActive())->toReturn(true);
        expect($double->isActive())->toBeTrue();
    });

    it("stubs return value on array-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecArrayReturn::class);
        allow($double->getItems())->toReturn([1, 2, 3]);
        expect($double->getItems())->toBe([1, 2, 3]);
    });

    it("stubs return value on float-return method via allow()", function() {
        $double = Double::getInstance(DoubleSpecFloatReturn::class);
        allow($double->getRate())->toReturn(3.14);
        expect($double->getRate())->toBe(3.14);
    });

    it("exposes args via LastCallDouble", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $lcd = new \PhpSpec\Mock\LastCallDouble($double, 'greet', ['a', 'b']);
        expect($lcd->______PhpSpecGetArgs())->toBe(['a', 'b']);
        expect($lcd->______PhpSpecGetMethod())->toBe('greet');
        expect($lcd->______PhpSpecGetDouble())->toBe($double);
    });

    it("throws when trying to double a final class", function() {
        expect(fn() => Double::getInstance(DoubleSpecFinalClass::class))->toThrow(\LogicException::class);
    });

    it("throws when trying to double a readonly class", function() {
        expect(fn() => Double::getInstance(DoubleSpecReadonlyClass::class))->toThrow(\LogicException::class);
    });

    it("throws when trying to double a trait", function() {
        expect(fn() => Double::getInstance(DoubleSpecTrait::class))->toThrow(\LogicException::class);
    });

    it("throws when trying to double an enum", function() {
        expect(fn() => Double::getInstance(DoubleSpecStatus::class))->toThrow(\LogicException::class);
    });

    it("generates mock class in PhpspecDouble namespace", function() {
        $double = Double::getInstance(DoubleSpecContract::class);
        $className = get_class($double);
        expect(str_starts_with($className, 'PhpspecDouble\\'))->toBeTrue();
    });

    it("uses prefixed internal properties to avoid conflicts", function() {
        $double = Double::getInstance(DoubleSpecWithStackProperty::class);
        expect($double)->toBeAnInstanceOf(DoubleSpecWithStackProperty::class);
        // The mock should work even though the parent class has a $stack property
        $double->push('test');
        $stack = $double->______PhpSpecGetStubbedCalls();
        expect($stack->exist('push', ['test']))->toBe(true);
    });

    it("caches class definitions for repeated getInstance calls", function() {
        $double1 = Double::getInstance(DoubleSpecContract::class);
        $double2 = Double::getInstance(DoubleSpecContract::class);
        expect(get_class($double1))->toBe(get_class($double2));
    });

});
