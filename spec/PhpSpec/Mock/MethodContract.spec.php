<?php

use PhpSpec\Mock\ContractParameter;
use PhpSpec\Mock\MethodContract;

interface MethodContractSpecShort {
    public function put(string $key);
    public function fill(array $slots);
    public function log(string $message, ...$context);
    public function items(): Traversable;
    public function size(): int;
    public function &held(): array;
}

interface MethodContractSpecLong {
    public function put(int|string $key, string $value = 'none');
    public function fill(array &$slots);
    public function log(string $message, string $level, string $channel);
    public function items(): ArrayIterator;
    public function size(): string;
    public function held(): array;
}

class MethodContractSpecGuarded {
    final protected function rewind(): void {}
}

interface MethodContractSpecFailure extends Throwable {
    public function getPrevious(): ?MethodContractSpecFailure;
}

describe(MethodContract::class, function () {

    this('of', fn() => static fn(string $method): MethodContract => MethodContract::of(
        MethodContractSpecShort::class,
        [new ReflectionMethod(MethodContractSpecShort::class, $method), new ReflectionMethod(MethodContractSpecLong::class, $method)],
        [MethodContractSpecShort::class, MethodContractSpecLong::class],
    ));

    it('takes a method with one declaration as it is declared', function () {
        $contract = MethodContract::of(MethodContractSpecLong::class, [new ReflectionMethod(MethodContractSpecLong::class, 'put')], [MethodContractSpecLong::class]);

        expect(array_map(fn(ContractParameter $parameter) => $parameter->name, $contract->parameters))->toBe(['key', 'value']);
        expect($contract->required)->toBe(1);
        expect($contract->takes())->toBe(2);
    });

    it('takes every call each declaration takes, held to every type one of them takes', function () {
        $contract = ($this->of)('put');
        [$key, $value] = $contract->parameters;

        expect($contract->required)->toBe(1);
        expect($contract->takes())->toBe(2);
        expect($key->takes)->toBe('string|int');
        expect($value->optional)->toBeTrue();
        expect($value->default())->toBe('none');
    });

    it('is variadic where a declaration is, after every parameter the longest declares', function () {
        $contract = ($this->of)('log');

        expect(array_map(fn(ContractParameter $parameter) => $parameter->name, $contract->parameters))->toBe(['message', 'level', 'channel', 'context']);
        expect($contract->takes())->toBeNull();
        expect($contract->required)->toBe(1);
    });

    it('refuses declarations that take a parameter one by value and one by reference, which no class can implement', function () {
        expect(fn() => ($this->of)('fill'))->toThrow(LogicException::class, 'MethodContractSpecShort::fill() takes $slots by value and MethodContractSpecLong::fill() takes $slots by reference.');
    });

    it('returns what every declared return type admits', function () {
        expect(($this->of)('items')->returnCode)->toBe(': \ArrayIterator');
    });

    it('is declared never where no value is admitted by every return type, saying why', function () {
        $contract = ($this->of)('size');

        expect($contract->returnCode)->toBe(': never');
        expect($contract->noValue)->toBe('MethodContractSpecShort::size() returns int and MethodContractSpecLong::size() returns string, so no value can be returned for both.');
    });

    it('returns by reference where a declaration does', function () {
        expect(($this->of)('held')->byReference)->toBeTrue();
    });

    it('knows whether a method a class declares final satisfies a declaration', function () {
        $previous = new ReflectionMethod(Exception::class, 'getPrevious');

        expect(MethodContract::satisfies($previous, new ReflectionMethod(Throwable::class, 'getPrevious'), []))->toBeTrue();
        expect(MethodContract::satisfies($previous, new ReflectionMethod(MethodContractSpecFailure::class, 'getPrevious'), []))->toBeFalse();
    });

    it('knows a method that is not public satisfies no declaration', function () {
        expect(MethodContract::satisfies(new ReflectionMethod(MethodContractSpecGuarded::class, 'rewind'), new ReflectionMethod(Iterator::class, 'rewind'), []))->toBeFalse();
    });
});
