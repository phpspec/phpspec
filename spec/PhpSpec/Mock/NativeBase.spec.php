<?php

use PhpSpec\Mock\NativeBase;

interface NativeBaseBag extends \Traversable {}
interface NativeBaseAggregate extends \IteratorAggregate {}
interface NativeBaseFailure extends \Throwable {}
interface NativeBaseMoment extends \DateTimeInterface {}
interface NativeBaseLabelled extends \UnitEnum {}
interface NativeBaseClash extends \Throwable, \DateTimeInterface {}
interface NativeBasePayload extends \Serializable {}
interface NativeBaseHalfPayload extends \Serializable {
    public function __unserialize(array $data): void;
}
interface NativeBaseSized extends \Countable {}

describe(NativeBase::class, function () {

    this('nothingRefused', fn() => static fn(string $interface): ?string => null);

    it('makes a Traversable interface an IteratorAggregate', function () {
        $base = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused);

        expect($base->implements())->toBe(['\IteratorAggregate']);
        expect(array_map(fn(ReflectionClass $interface) => $interface->getName(), $base->interfaces()))->toBe([IteratorAggregate::class]);
        expect((new NativeBase([new ReflectionClass(NativeBaseAggregate::class)], $this->nothingRefused))->implements())->toBe([]);
    });

    it('builds a Throwable interface on Exception, declaring the methods Exception declares', function () {
        $base = new NativeBase([new ReflectionClass(NativeBaseFailure::class)], $this->nothingRefused);

        expect($base->extends())->toBe('\Exception');
        expect($base->declaration('getMessage')?->isFinal())->toBeTrue();
        expect($base->declaration('describe'))->toBeNull();
    });

    it('builds a DateTimeInterface interface on DateTimeImmutable', function () {
        $base = new NativeBase([new ReflectionClass(NativeBaseMoment::class)], $this->nothingRefused);

        expect($base->extends())->toBe('\DateTimeImmutable');
        expect($base->declaration('format')?->isFinal())->toBeFalse();
    });

    it('refuses an interface only an enum can implement, and one that needs two bases', function () {
        expect((new NativeBase([new ReflectionClass(NativeBaseLabelled::class)], $this->nothingRefused))->refusal())->toBe('Only an enum can implement UnitEnum.');
        expect((new NativeBase([new ReflectionClass(NativeBaseClash::class)], $this->nothingRefused))->refusal())->toBe('No class can extend both Exception and DateTimeImmutable.');
        expect((new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused))->refusal())->toBeNull();
    });

    it('gives a Serializable interface the methods that spare its deprecation', function () {
        $code = (new NativeBase([new ReflectionClass(NativeBasePayload::class)], $this->nothingRefused))->code();

        expect($code)->toContain('function __serialize(): array');
        expect($code)->toContain('function __unserialize(array $data): void');
    });

    it('leaves out of that code a method the interface declares itself', function () {
        $code = (new NativeBase([new ReflectionClass(NativeBaseHalfPayload::class)], $this->nothingRefused))->code();

        expect($code)->toContain('function __serialize(): array');
        expect($code)->not()->toContain('__unserialize');
    });

    it('asks about any other built-in interface, refusing what PHP refuses in its words', function () {
        $asked = [];
        $refusedBy = function (string $interface) use (&$asked): ?string {
            $asked[] = $interface;

            return $interface === 'Countable' ? 'Countable cannot be implemented here' : null;
        };

        $base = new NativeBase([new ReflectionClass(NativeBaseSized::class)], $refusedBy);
        new NativeBase([new ReflectionClass(NativeBaseBag::class)], $refusedBy);

        expect($base->refusal())->toBe('PHP refuses a class implementing Countable: Countable cannot be implemented here.');
        expect($asked)->toBe(['Countable']);
    });
});
