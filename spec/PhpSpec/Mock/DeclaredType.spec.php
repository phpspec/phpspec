<?php

use PhpSpec\Mock\DeclaredType;

class DeclaredTypeSpecPaper {}
class DeclaredTypeSpecInk {}
final class DeclaredTypeSpecCoin {}
final class DeclaredTypeSpecTally implements Countable {
    public function count(): int { return 0; }
}
class_alias(DeclaredTypeSpecPaper::class, 'DeclaredTypeSpecSheet');
class DeclaredTypeSpecFault extends Error {}
interface DeclaredTypeSpecFailure extends Throwable {}
interface DeclaredTypeSpecMarker {}

interface DeclaredTypeSpecReturns {
    public function narrow(): ArrayIterator;
    public function wide(): Traversable;
    public function counted(): Countable;
    public function scalar(): int|string;
    public function word(): string|bool;
    public function maybeCounted(): ?Countable;
    public function maybeWide(): ?Traversable;
    public function number(): int;
    public function text(): string;
    public function nothing(): void;
    public function anything(): mixed;
    public function yes(): true;
    public function no(): false;
    public function thing(): object;
    public function paper(): DeclaredTypeSpecPaper;
    public function ink(): DeclaredTypeSpecInk;
    public function coin(): DeclaredTypeSpecCoin;
    public function tally(): DeclaredTypeSpecTally;
    public function same(): static;
    public function sheet(): DeclaredTypeSpecSheet;
    public function cased(): declaredtypespecpaper;
    public function tallied(): DeclaredTypeSpecTally&Countable;
}

describe(DeclaredType::class, function () {

    this('type', fn() => static fn(string $method): DeclaredType => DeclaredType::of(
        (new ReflectionMethod(DeclaredTypeSpecReturns::class, $method))->getReturnType(),
        new ReflectionMethod(DeclaredTypeSpecReturns::class, $method),
        [DeclaredTypeSpecReturns::class],
    ));

    it('meets two classes in the narrower one', function () {
        expect(($this->type)('narrow')->meet(($this->type)('wide'))->code())->toBe('\ArrayIterator');
        expect(($this->type)('wide')->meet(($this->type)('narrow'))->code())->toBe('\ArrayIterator');
    });

    it('meets two unrelated interfaces in their intersection', function () {
        expect(($this->type)('counted')->meet(($this->type)('wide'))->code())->toBe('\Countable&\Traversable');
    });

    it('meets two unions in what both admit', function () {
        expect(($this->type)('scalar')->meet(($this->type)('word'))->code())->toBe('string');
    });

    it('admits null only where every type does', function () {
        expect(($this->type)('maybeCounted')->meet(($this->type)('maybeWide'))->code())->toBe('(\Countable&\Traversable)|null');
        expect(($this->type)('maybeCounted')->meet(($this->type)('wide'))->code())->toBe('\Countable&\Traversable');
    });

    it('admits no value where no value can be both', function () {
        expect(($this->type)('number')->meet(($this->type)('text'))->admitsNoValue())->toBeTrue();
        expect(($this->type)('nothing')->meet(($this->type)('number'))->admitsNoValue())->toBeTrue();
        expect(($this->type)('paper')->meet(($this->type)('ink'))->admitsNoValue())->toBeTrue();
        expect(($this->type)('coin')->meet(($this->type)('counted'))->admitsNoValue())->toBeTrue();
        expect(($this->type)('tally')->meet(($this->type)('counted'))->code())->toBe('\DeclaredTypeSpecTally');
    });

    it('meets mixed in the other type, but never in void', function () {
        expect(($this->type)('anything')->meet(($this->type)('number'))->code())->toBe('int');
        expect(($this->type)('anything')->meet(($this->type)('nothing'))->admitsNoValue())->toBeTrue();
    });

    it('meets static in itself where the double is of the other type', function () {
        expect(($this->type)('same')->meet(DeclaredType::allOf([DeclaredTypeSpecReturns::class]))->code())->toBe('static');
        expect(($this->type)('same')->meet(($this->type)('paper'))->admitsNoValue())->toBeTrue();
    });

    it('joins two types in what either admits, written as PHP lets it be written', function () {
        expect(($this->type)('number')->join(($this->type)('text'))->code())->toBe('int|string');
        expect(($this->type)('yes')->join(($this->type)('no'))->code())->toBe('bool');
        expect(($this->type)('paper')->join(($this->type)('thing'))->code())->toBe('object');
        expect(($this->type)('anything')->join(($this->type)('number'))->isMixed())->toBeTrue();
    });

    it('knows one type within another', function () {
        expect(($this->type)('narrow')->within(($this->type)('wide')))->toBeTrue();
        expect(($this->type)('wide')->within(($this->type)('narrow')))->toBeFalse();
        expect(($this->type)('number')->within(($this->type)('anything')))->toBeTrue();
        expect(($this->type)('nothing')->within(($this->type)('anything')))->toBeFalse();
    });

    it('shows classes as a reader writes them', function () {
        expect(($this->type)('maybeCounted')->shown())->toBe('Countable|null');
    });

    it('names a class as PHP does, however the declaration spells it', function () {
        expect(($this->type)('paper')->meet(($this->type)('sheet'))->code())->toBe('\DeclaredTypeSpecPaper');
        expect(($this->type)('paper')->meet(($this->type)('cased'))->code())->toBe('\DeclaredTypeSpecPaper');
    });

    it('writes an intersection as the fewest types it needs', function () {
        expect(($this->type)('tallied')->code())->toBe('\DeclaredTypeSpecTally');
    });

    it('knows an object can be every type where what a class extends allows it, whether or not a double can', function () {
        expect(DeclaredType::allOf([DeclaredTypeSpecFault::class, DeclaredTypeSpecFailure::class])->admitsNoValue())->toBeFalse();
        expect(DeclaredType::allOf([DeclaredTypeSpecPaper::class, DeclaredTypeSpecFailure::class])->admitsNoValue())->toBeTrue();
        expect(DeclaredType::allOf([UnitEnum::class, DeclaredTypeSpecMarker::class])->admitsNoValue())->toBeFalse();
        expect(DeclaredType::allOf([Throwable::class, DateTimeInterface::class])->admitsNoValue())->toBeTrue();
    });

    it('knows the classes no one object can be', function () {
        expect(DeclaredType::allOf([DeclaredTypeSpecPaper::class, DeclaredTypeSpecInk::class])->admitsNoValue())->toBeTrue();
        expect(DeclaredType::allOf([DeclaredTypeSpecPaper::class, Countable::class])->admitsNoValue())->toBeFalse();
    });
});
