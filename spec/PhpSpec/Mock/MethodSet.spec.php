<?php

use PhpSpec\Mock\MethodContract;
use PhpSpec\Mock\MethodSet;
use PhpSpec\Mock\NativeBase;

interface MethodSetSpecWide {
    public function items(): Traversable;
    public function title(): string;
}

interface MethodSetSpecNarrow {
    public function items(): ArrayIterator;
}

interface MethodSetSpecBag extends Traversable {}

interface MethodSetSpecNarrowBag extends Traversable {
    public function getIterator(): ArrayIterator;
}

interface MethodSetSpecFailure extends Throwable {
    public function getPrevious(): ?MethodSetSpecFailure;
}

interface MethodSetSpecByValue {
    public function fill(array $slots);
}

interface MethodSetSpecByReference {
    public function fill(array &$slots);
}

describe(MethodSet::class, function () {

    this('base', fn() => static fn(string ...$interfaces): NativeBase => new NativeBase(
        array_map(fn(string $interface) => new ReflectionClass($interface), $interfaces),
        static fn(string $interface): ?string => null,
    ));

    this('returns', fn() => static fn(MethodSet $set): array => array_combine(
        array_map(fn(MethodContract $contract) => $contract->name, $set->contracts),
        array_map(fn(MethodContract $contract) => $contract->returnCode, $set->contracts),
    ));

    it('defines each method once, from every type that declares it', function () {
        $set = MethodSet::of([new ReflectionClass(MethodSetSpecWide::class), new ReflectionClass(MethodSetSpecNarrow::class)], null);

        expect(($this->returns)($set))->toBe(['items' => ': \ArrayIterator', 'title' => ': string']);
        expect($set->refusal)->toBeNull();
    });

    it('defines the methods of the interfaces its base brings, as the types narrow them', function () {
        $bag = MethodSet::of([new ReflectionClass(MethodSetSpecBag::class)], ($this->base)(MethodSetSpecBag::class));
        $narrow = MethodSet::of([new ReflectionClass(MethodSetSpecNarrowBag::class)], ($this->base)(MethodSetSpecNarrowBag::class));

        expect(($this->returns)($bag))->toBe(['getIterator' => ': \Traversable']);
        expect(($this->returns)($narrow))->toBe(['getIterator' => ': \ArrayIterator']);
    });

    it('leaves the methods its base declares final, refusing a type that narrows one', function () {
        $failure = MethodSet::of([new ReflectionClass(MethodSetSpecFailure::class)], ($this->base)(MethodSetSpecFailure::class));

        expect($failure->contracts)->toBe([]);
        expect($failure->refusal)->toBe('MethodSetSpecFailure::getPrevious() narrows a method Exception declares final.');
        expect(MethodSet::of([new ReflectionClass(Throwable::class)], ($this->base)(Throwable::class))->refusal)->toBeNull();
    });

    it('refuses types no class can implement every declaration of', function () {
        $set = MethodSet::of([new ReflectionClass(MethodSetSpecByValue::class), new ReflectionClass(MethodSetSpecByReference::class)], null);

        expect($set->refusal)->toBe('MethodSetSpecByValue::fill() takes $slots by value and MethodSetSpecByReference::fill() takes $slots by reference.');
    });
});
