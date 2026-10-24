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

class MethodSetSpecBase {
    final public function value(): int|string { return 'kept'; }
    public function name(): string { return 'base'; }
}

interface MethodSetSpecHasValue {
    public function value(): int|string|null;
    public function name(): string;
}

interface MethodSetSpecNarrowValue {
    public function value(): string;
}

class MethodSetSpecHidden {
    private function value(): int { return 1; }
    final protected function name(): string { return 'guarded'; }
}

interface MethodSetSpecValueOnly {
    public function value(): string;
}

interface MethodSetSpecNameOnly {
    public function name(): string;
}

abstract class MethodSetSpecModel {
    abstract public static function named(string $name): static;
    public static function create(): static { return new static(); }
}

interface MethodSetSpecCreating {
    public static function create(): static;
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

    it('keeps the final methods of a class the types name, and merges the others', function () {
        $types = [new ReflectionClass(MethodSetSpecBase::class), new ReflectionClass(MethodSetSpecHasValue::class)];
        $set = MethodSet::of($types, new NativeBase([$types[1]], static fn(string $interface): ?string => null, $types[0]));

        expect(($this->returns)($set))->toBe(['name' => ': string']);
        expect($set->refusal)->toBeNull();
    });

    it('refuses an interface narrowing a final method of the class the types name', function () {
        $types = [new ReflectionClass(MethodSetSpecBase::class), new ReflectionClass(MethodSetSpecNarrowValue::class)];
        $set = MethodSet::of($types, new NativeBase([$types[1]], static fn(string $interface): ?string => null, $types[0]));

        expect($set->refusal)->toBe('MethodSetSpecNarrowValue::value() narrows a method MethodSetSpecBase declares final.');
    });

    it('leaves out a private method of the class, which a subclass declares afresh', function () {
        $types = [new ReflectionClass(MethodSetSpecHidden::class), new ReflectionClass(MethodSetSpecValueOnly::class)];
        $set = MethodSet::of($types, new NativeBase([$types[1]], static fn(string $interface): ?string => null, $types[0]));

        expect(($this->returns)($set))->toBe(['value' => ': string']);
    });

    it('refuses an interface declaring publicly a method the class declares final and protected', function () {
        $types = [new ReflectionClass(MethodSetSpecHidden::class), new ReflectionClass(MethodSetSpecNameOnly::class)];
        $set = MethodSet::of($types, new NativeBase([$types[1]], static fn(string $interface): ?string => null, $types[0]));

        expect($set->refusal)->toBe('MethodSetSpecNameOnly::name() narrows a method MethodSetSpecHidden declares final.');
    });

    it('defines an abstract static method, and leaves one the class implements, to suit an interface too', function () {
        $model = MethodSet::of([new ReflectionClass(MethodSetSpecModel::class)], null);
        $types = [new ReflectionClass(MethodSetSpecModel::class), new ReflectionClass(MethodSetSpecCreating::class)];
        $creating = MethodSet::of($types, new NativeBase([$types[1]], static fn(string $interface): ?string => null, $types[0]));

        expect(array_map(fn(MethodContract $contract) => [$contract->name, $contract->static], $model->contracts))->toBe([['named', true]]);
        expect(array_map(fn(MethodContract $contract) => $contract->name, $creating->contracts))->toBe(['named']);
    });

    it('refuses types no class can implement every declaration of', function () {
        $set = MethodSet::of([new ReflectionClass(MethodSetSpecByValue::class), new ReflectionClass(MethodSetSpecByReference::class)], null);

        expect($set->refusal)->toBe('MethodSetSpecByValue::fill() takes $slots by value and MethodSetSpecByReference::fill() takes $slots by reference.');
    });
});
