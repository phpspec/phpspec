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
interface NativeBaseMarker {}
class NativeBasePlain {}
class NativeBaseFault extends \Error {}
class NativeBaseClock extends \DateTime {}
class NativeBaseList extends \ArrayIterator {}
class NativeBaseSerialized {
    public function __serialize(): array { return []; }
}
class NativeBaseAggregated {
    public function getIterator(): \ArrayIterator { return new \ArrayIterator([]); }
}
class NativeBaseListing {
    final public function getIterator(): array { return []; }
}
class NativeBaseEndless {
    public function getIterator(): array { return []; }
    final public function valid(): true { return true; }
}
class NativeBaseSelfIterating {
    public function getIterator(): self { return $this; }
}
class NativeBaseOuter {
    public function getIterator(): NativeBaseRing { return new NativeBaseRing(); }
}
class NativeBaseRing {
    public function getIterator(): NativeBaseOuter { return new NativeBaseOuter(); }
}
class NativeBaseStaticIterator {
    public static function getIterator(): \ArrayIterator { return new \ArrayIterator([]); }
}
class NativeBaseForever {
    public function getIterator(): array { return []; }
    public function valid(): true { return true; }
}
class NativeBaseValued {
    public static function value(): string { return 'static'; }
}
class NativeBaseHiddenValue {
    private static function value(): string { return 'hidden'; }
}
interface NativeBaseHasValue {
    public function value(): string;
}
interface NativeBaseTicker extends \Iterator {
    public function valid(): true;
}
class NativeBaseAggregating implements \IteratorAggregate {
    public function getIterator(): \ArrayIterator { return new \ArrayIterator([]); }
}
class NativeBaseCircling implements \IteratorAggregate {
    public function getIterator(): self { return $this; }
}
class NativeBaseYielding implements \IteratorAggregate {
    public function getIterator(): \Generator { yield 1; }
}
class NativeBaseNarrowable implements \IteratorAggregate {
    public function getIterator(): \Traversable { return new \ArrayIterator([]); }
}
interface NativeBaseNarrowing extends \IteratorAggregate {
    public function getIterator(): NativeBaseNarrowable&NativeBaseNarrowing;
}
class NativeBaseMaking {
    public function made(): string { return 'made'; }
}
interface NativeBaseMade {
    public static function made(): string;
}
class NativeBaseStuck {
    final public function valid(): bool { return true; }
}
class NativeBaseHopeless {
    final public function getIterator(): array { return []; }
    final public function next(): int { return 0; }
}

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

    it('extends a class the types name, adding what a subclass of it can add', function () {
        $base = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBasePlain::class));
        $listed = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseList::class));

        expect($base->extends())->toBe('\NativeBasePlain');
        expect($base->implements())->toBe(['\IteratorAggregate']);
        expect($base->conflict())->toBeNull();
        expect($listed->implements())->toBe([]);
    });

    it('keeps a class that already is what the interfaces need, an Error or a DateTime', function () {
        $fault = new NativeBase([new ReflectionClass(NativeBaseFailure::class)], $this->nothingRefused, new ReflectionClass(NativeBaseFault::class));
        $clock = new NativeBase([new ReflectionClass(NativeBaseMoment::class)], $this->nothingRefused, new ReflectionClass(NativeBaseClock::class));

        expect($fault->extends())->toBe('\NativeBaseFault');
        expect($fault->refusal())->toBeNull();
        expect($clock->extends())->toBe('\NativeBaseClock');
        expect($clock->declaration('format')?->class)->toBe(DateTime::class);
    });

    it('conflicts with a class no subclass of which can be what the interfaces need', function () {
        $failure = new NativeBase([new ReflectionClass(NativeBaseFailure::class)], $this->nothingRefused, new ReflectionClass(NativeBasePlain::class));
        $moment = new NativeBase([new ReflectionClass(NativeBaseMoment::class)], $this->nothingRefused, new ReflectionClass(NativeBasePlain::class));

        expect($failure->conflict())->toBe('No class can extend both NativeBasePlain and Exception or Error.');
        expect($failure->refusal())->toBe($failure->conflict());
        expect($moment->conflict())->toBe('No class can extend both NativeBasePlain and DateTime or DateTimeImmutable.');
        expect((new NativeBase([new ReflectionClass(NativeBaseClash::class)], $this->nothingRefused))->conflict())->toBe('No class can extend both Exception and DateTimeImmutable.');
    });

    it('refuses what an enum alone can implement without its conflicting, since an enum can be both', function () {
        $marked = new NativeBase([new ReflectionClass(NativeBaseLabelled::class), new ReflectionClass(NativeBaseMarker::class)], $this->nothingRefused);

        expect($marked->conflict())->toBeNull();
        expect($marked->refusal())->toBe('Only an enum can implement UnitEnum.');
    });

    it('leaves out of its code a serialization method the class it extends has', function () {
        $code = (new NativeBase([new ReflectionClass(NativeBasePayload::class)], $this->nothingRefused, new ReflectionClass(NativeBaseSerialized::class)))->code();

        expect($code)->not()->toContain('__serialize');
        expect($code)->toContain('function __unserialize(array $data): void');
    });

    it('makes a class an IteratorAggregate where its getIterator() allows, else an Iterator', function () {
        $over = fn(string $class) => new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass($class));

        expect($over(NativeBaseAggregated::class)->implements())->toBe(['\IteratorAggregate']);
        expect($over(NativeBaseListing::class)->implements())->toBe(['\Iterator']);
        expect($over(NativeBaseListing::class)->refusal())->toBeNull();
    });

    it('refuses a class whose final valid() no double can end iteration with, and conflicts with one no iterator fits', function () {
        $endless = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseEndless::class));
        $hopeless = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseHopeless::class));

        expect($endless->conflict())->toBeNull();
        expect($endless->refusal())->toBe('NativeBaseEndless declares valid() final, so a double of it cannot end its iteration.');
        expect($hopeless->conflict())->toBe('No class extending NativeBaseHopeless can implement Iterator or IteratorAggregate.');
    });

    it('takes a class whose getIterator() comes back to it, at once or through another, to aggregate', function () {
        $self = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseSelfIterating::class));
        $mutual = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseOuter::class));

        expect($self->conflict())->toBeNull();
        expect($self->implements())->toBe(['\IteratorAggregate']);
        expect($mutual->conflict())->toBeNull();
    });

    it('iterates as an Iterator a class whose getIterator() is static', function () {
        expect((new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseStaticIterator::class)))->implements())->toBe(['\Iterator']);
    });

    it('conflicts with a class declaring static, and not private, what an interface declares on an object', function () {
        $valued = new NativeBase([new ReflectionClass(NativeBaseHasValue::class)], $this->nothingRefused, new ReflectionClass(NativeBaseValued::class));
        $hidden = new NativeBase([new ReflectionClass(NativeBaseHasValue::class)], $this->nothingRefused, new ReflectionClass(NativeBaseHiddenValue::class));

        expect($valued->conflict())->toBe('NativeBaseValued declares value() static, so no class extending it can implement NativeBaseHasValue::value().');
        expect($hidden->conflict())->toBeNull();
    });

    it('conflicts with a class declaring on an object what an interface declares static', function () {
        $making = new NativeBase([new ReflectionClass(NativeBaseMade::class)], $this->nothingRefused, new ReflectionClass(NativeBaseMaking::class));

        expect($making->conflict())->toBe('NativeBaseMaking declares made() on an object, so no class extending it can implement the static NativeBaseMade::made().');
    });

    it('refuses an Iterator whose valid() resolves to a type without false, without its conflicting', function () {
        $forever = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseForever::class));
        $ticker = new NativeBase([new ReflectionClass(NativeBaseTicker::class)], $this->nothingRefused);

        expect($forever->conflict())->toBeNull();
        expect($forever->refusal())->toBe('NativeBaseForever declares valid() returning true, so a double of it cannot end its iteration.');
        expect($ticker->refusal())->toBe('NativeBaseTicker declares valid() returning true, so a double of it cannot end its iteration.');
    });

    it('makes a double an empty Iterator where asked, or an aggregate whose iteration ends, saying what keeps a class from either', function () {
        $empty = fn(?string $class) => new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, $class === null ? null : new ReflectionClass($class), [], []);

        expect($empty(null)->implements())->toBe(['\Iterator']);
        expect($empty(NativeBaseSelfIterating::class)->implements())->toBe(['\Iterator']);
        expect($empty(NativeBaseAggregating::class)->refusal())->toBeNull();
        expect($empty(NativeBaseAggregating::class)->implements())->toBe([]);
        expect($empty(NativeBaseCircling::class)->refusal())->toBe('NativeBaseCircling::getIterator() returns NativeBaseCircling, so its iteration could not end.');
        expect($empty(NativeBaseStuck::class)->refusal())->toBe('NativeBaseStuck declares valid() final, so a double of it cannot end its iteration.');
    });

    it('follows the getIterator() every declaration resolves to, ending at a generator, and refuses one coming back to an aggregate being iterated', function () {
        $yielding = new NativeBase([], $this->nothingRefused, new ReflectionClass(NativeBaseYielding::class), [], []);
        $narrowed = new NativeBase([new ReflectionClass(NativeBaseNarrowing::class)], $this->nothingRefused, new ReflectionClass(NativeBaseNarrowable::class), [], []);

        expect($yielding->refusal())->toBeNull();
        expect($narrowed->refusal())->toBe('NativeBaseNarrowable&NativeBaseNarrowing::getIterator() returns NativeBaseNarrowable&NativeBaseNarrowing, so its iteration could not end.');
    });

    it('names a combination of types however its classes are spelled or ordered', function () {
        expect(NativeBase::keyOf(['\\App\\Bag', 'App\\Collection']))->toBe(NativeBase::keyOf(['app\\collection', 'App\\Bag']));
    });

    it('leaves a double that refuses every call free of whether its iteration could end', function () {
        $forever = new NativeBase([new ReflectionClass(NativeBaseBag::class)], $this->nothingRefused, new ReflectionClass(NativeBaseForever::class));

        expect($forever->refusal(false))->toBeNull();
        expect($forever->refusal())->not()->toBeNull();
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
