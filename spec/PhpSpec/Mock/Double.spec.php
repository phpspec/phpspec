<?php

use PhpSpec\Mock\ArrangingCode;
use PhpSpec\Mock\Double;
use PhpSpec\Mock\HeldCalls;
use PhpSpec\Mock\UnexpectedCallException;
use PhpSpec\ProjectRoot;

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

final class DoubleSpecCoin {
    public function __construct(private int $pence) {}
    public function pence(): int { return $this->pence; }
}

interface DoubleSpecStock {
    public function reserve(string $item, int $quantity): bool;
}

interface DoubleSpecTill {
    public function pay(int $amount, string $currency = 'EUR'): int;
    public function find(int $id = 0): string;
    public function tally(string ...$items): int;
    public function note(string $text, array $tags = [], ?string $by = null, array $seen = ['a'], bool $loud = false): void;
}

// Code that is not spec code: a file outside the spec folder calling the double.
define('DOUBLE_SPEC_OUTSIDER', sys_get_temp_dir() . '/phpspec_double_spec_outsider_' . getmypid() . '.php');
if (!function_exists('double_spec_outsider_reserves')) {
    register_shutdown_function(static fn() => @unlink(DOUBLE_SPEC_OUTSIDER));
    file_put_contents(DOUBLE_SPEC_OUTSIDER, "<?php\nfunction double_spec_outsider_reserves(object \$stock) { return \$stock->reserve('tea', 2); }\nfunction double_spec_outsider_reserves_nothing(object \$stock) { return \$stock->reserve(); }\nfunction double_spec_outsider_pays(object \$till, int \$amount, string ...\$currency) { return \$till->pay(\$amount, ...\$currency); }\nfunction double_spec_outsider_reserves_with(object \$stock, mixed ...\$arguments) { return \$stock->reserve(...\$arguments); }\nfunction double_spec_outsider_finds(object \$till, int \$id) { return \$till->find(\$id); }\nfunction double_spec_outsider_greets(object \$double) { return \$double->greet(); }\nfunction double_spec_outsider_reads(object \$book) { return \$book->first()->next()->title(); }\nfunction double_spec_outsider_calls(object \$double, string \$method) { return \$double->\$method(); }\n");
    require DOUBLE_SPEC_OUTSIDER;
}

define('DOUBLE_SPEC_FILE', ProjectRoot::here()->relative(__FILE__));

// The message of what the call throws.
if (!function_exists('double_spec_refusal')) {
    function double_spec_refusal(Closure $call): string
    {
        try {
            $call();
        } catch (LogicException $e) {
            return $e->getMessage();
        }

        return '';
    }
}

// The same, in a file that declares strict types.
define('DOUBLE_SPEC_STRICT_OUTSIDER', sys_get_temp_dir() . '/phpspec_double_spec_strict_outsider_' . getmypid() . '.php');
if (!function_exists('double_spec_strict_outsider_reserves_with')) {
    register_shutdown_function(static fn() => is_file(DOUBLE_SPEC_STRICT_OUTSIDER) && unlink(DOUBLE_SPEC_STRICT_OUTSIDER));
    file_put_contents(DOUBLE_SPEC_STRICT_OUTSIDER, "<?php\n\ndeclare(strict_types=1);\n\nfunction double_spec_strict_outsider_reserves_with(object \$stock, mixed ...\$arguments) { return \$stock->reserve(...\$arguments); }\n");
    require DOUBLE_SPEC_STRICT_OUTSIDER;
}

interface DoubleSpecLedger {
    public function balance(): int;
    public function rate(): float;
    public function owner(): ?DoubleSpecContract;
    public function either(): int|string;
    public function record(string $line): void;
    public function same(): static;
}

interface DoubleSpecKinds {
    public function flag(): bool;
    public function list(): array;
    public function many(): iterable;
    public function action(): callable;
    public function thing(): object;
    public function me(): self;
    public function both(): DoubleSpecIntersectA&DoubleSpecIntersectB;
    public function yes(): true;
    public function no(): false;
}

class DoubleSpecBase {}

class DoubleSpecChild extends DoubleSpecBase {
    public function up(): parent { return new DoubleSpecBase(); }
}

class DoubleSpecBoth implements DoubleSpecIntersectA, DoubleSpecIntersectB {}

interface DoubleSpecMachine {
    public function each(): iterable;
    public function hook(): Closure;
    public function stream(): Generator;
    public function same(): static;
    public function coin(): DoubleSpecCoin;
}

class DoubleSpecMagic {
    public function __call(string $name, array $arguments): string { return 'magic'; }
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

interface DoubleSpecByReference {
    public function set(array &$mapping): void;
    public function fill(&...$slots): int;
    public function &all(): array;
    public function &maybe(): ?array;
    public function &anything(): mixed;
    public function &status(): DoubleSpecStatus;
    public function &contract(): DoubleSpecContract;
    public function &loose();
    public function &either(): int|string;
    public function &orNull(): int|string|null;
    public function take(int &$value): mixed;
    public function log(string $message, int ...$context): void;
}

class DoubleSpecHolder {
    public function &get(): array { static $held = []; return $held; }
}

class DoubleSpecUsesHolder {
    public function __construct(private DoubleSpecHolder $holder) {}
}

class DoubleSpecClock {
    public function __construct(public int $at = 0) {}
}

interface DoubleSpecNewDefault {
    public function at(DoubleSpecClock $clock = new DoubleSpecClock(), array $clocks = [new DoubleSpecClock(5)]): int;
    public function maybe(?DoubleSpecClock $clock = new DoubleSpecClock(9), int $mode = 0): ?int;
}

interface DoubleSpecBag extends \Traversable {}
interface DoubleSpecFailure extends \Throwable {}
interface DoubleSpecMoment extends \DateTimeInterface {}
interface DoubleSpecLabelled extends \UnitEnum {}
interface DoubleSpecPayload extends \Serializable {}
interface DoubleSpecSized {
    public function size(): int;
}
interface DoubleSpecNativeSource {
    public function bag(): DoubleSpecBag;
    public function failure(): DoubleSpecFailure;
    public function moment(): DoubleSpecMoment;
    public function label(): DoubleSpecLabelled;
    public function counted(): DoubleSpecBag&DoubleSpecSized;
    public function name(): string;
}
interface DoubleSpecNarrowBag extends \Traversable {
    public function getIterator(): \ArrayIterator;
}
interface DoubleSpecHalfPayload extends \Serializable {
    public function __unserialize(array $data): void;
}
interface DoubleSpecNarrowFailure extends \Throwable {
    public function getPrevious(): ?DoubleSpecNarrowFailure;
}
interface DoubleSpecShortPut {
    public function put(string $key): int;
}
interface DoubleSpecLongPut {
    public function put(string $key, string $value = 'none'): int|string;
}
interface DoubleSpecFillByValue {
    public function fill(array $slots);
}
interface DoubleSpecFillByReference {
    public function fill(array &$slots);
}
interface DoubleSpecPutSource {
    public function puts(): DoubleSpecShortPut&DoubleSpecLongPut;
    public function fills(): DoubleSpecFillByValue&DoubleSpecFillByReference;
    public function count(): int;
}
interface DoubleSpecPage {
    public function title(): string;
    public function next(): DoubleSpecPage;
    public function line(int $number): string;
    public function price(): DoubleSpecCoin;
}
interface DoubleSpecNumbered {
    public function number(): int;
}
interface DoubleSpecBook {
    public function first(): DoubleSpecPage;
    public function numbered(): DoubleSpecPage&DoubleSpecNumbered;
    public function either(): DoubleSpecCoin|DoubleSpecPage;
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

    context("a method that takes or returns by reference, or defaults to what only runs", function () {
        it("stands in for an argument taken by reference, a variadic one among them", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $map = ['version' => 1];
            $slot = 1;

            $double->set($map);

            expect($double->fill($slot))->toBe(0);
        });

        it("returns by reference on every path without a notice", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $raised = [];
            set_error_handler(function (int $level, string $message) use (&$raised): bool {
                $raised[] = $message;

                return true;
            });

            try {
                $double->all();
                $double->maybe();
                $double->anything();
                $double->status();
                $double->contract();
                $double->loose();
                $double->either();
                $double->orNull();
            } finally {
                restore_error_handler();
            }

            expect($raised)->toBe([]);
        });

        it("doubles a class returning by reference, and a class that takes one in its constructor", function () {
            expect(Double::getInstance(DoubleSpecHolder::class)->get())->toBe([]);
            expect(Double::getInstance(DoubleSpecUsesHolder::class))->toBeAnInstanceOf(DoubleSpecUsesHolder::class);
        });

        it("hands a callback each default built with new, built afresh on each call", function () {
            $double = Double::getInstance(DoubleSpecNewDefault::class);
            $seen = [];
            allow($double->at())->toReturnUsing(function (DoubleSpecClock $clock, array $clocks) use (&$seen): int {
                $seen[] = $clock;

                return $clock->at + $clocks[0]->at;
            });

            expect($double->at())->toBe(5);
            expect($double->at())->toBe(5);
            expect($seen[0])->not()->toBe($seen[1]);
        });

        it("builds a default left out before a named argument, and keeps a null given for it", function () {
            $double = Double::getInstance(DoubleSpecNewDefault::class);
            allow($double->maybe(any(), any()))->toReturnUsing(fn(?DoubleSpecClock $clock, int $mode): ?int => $clock === null ? null : $clock->at + $mode);

            expect($double->maybe(mode: 1))->toBe(10);
            expect($double->maybe(null, 1))->toBeNull();
        });

        it("writes back through an argument a callback takes by reference", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $anything = any();
            allow($double->set($anything))->toReturnUsing(function (array &$mapping): void {
                $mapping['seen'] = true;
            });
            $mapping = [];

            $double->set($mapping);

            expect($mapping)->toBe(['seen' => true]);
        });

        it("writes back through each argument a by-reference variadic takes", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $anything = cetera();
            allow($double->fill($anything))->toReturnUsing(function (&...$slots): int {
                foreach ($slots as &$slot) {
                    $slot++;
                }

                return count($slots);
            });
            $first = 1;
            $second = 2;

            expect($double->fill($first, $second))->toBe(2);
            expect([$first, $second])->toBe([2, 3]);
        });

        it("hands back the reference a callback returns by reference", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $store = ['a' => 1];
            allow($double->all())->toReturnUsing(function &() use (&$store): array {
                return $store;
            });

            $all = &$double->all();
            $all['b'] = 2;

            expect($store)->toBe(['a' => 1, 'b' => 2]);
        });

        it("converts a scalar it takes by reference, for the callback and the caller, as PHP does", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $anything = any();
            allow($double->take($anything))->toReturnUsing(fn($value) => $value);
            $value = '7';

            expect($double->take($value))->toBe(7);
            expect($value)->toBe(7);
        });

        it("records the named arguments a variadic collects, held to its type", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);

            $double->log('hi', user: '7');

            expect($double->log('hi', user: 7))->toHaveBeenCalled();
            expect($double->log('hi', user: 8))->not()->toHaveBeenCalled();
            expect($double->log('hi', 7))->not()->toHaveBeenCalled();
        });

        it("hands a callback the named arguments a by-reference variadic collects, as references", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $anything = cetera();
            allow($double->fill($anything))->toReturnUsing(function (&...$slots): int {
                foreach ($slots as &$slot) {
                    $slot++;
                }

                return count($slots);
            });
            $first = 1;
            $second = 2;

            expect($double->fill($first, second: $second))->toBe(2);
            expect([$first, $second])->toBe([2, 3]);
        });

        it("records the named arguments a by-reference variadic collects as they were when the call was made", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $anything = cetera();
            allow($double->fill($anything))->toReturnUsing(function (&...$slots): int {
                foreach ($slots as &$slot) {
                    $slot = 9;
                }

                return 0;
            });
            $value = 1;

            $double->fill(first: $value);
            $value = 2;

            $one = 1;
            expect($double->fill(first: $one))->toHaveBeenCalled();
        });

        it("keeps a stub declared with a named by-reference argument to the value it was declared with", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            $pattern = 1;
            allow($double->fill(first: $pattern))->toReturn(5);
            $pattern = 2;

            $one = 1;
            expect($double->fill(first: $one))->toBe(5);
        });

        it("hands back a copy of a value stubbed with toReturn, never the value itself", function () {
            $double = Double::getInstance(DoubleSpecByReference::class);
            allow($double->all())->toReturn(['a' => 1]);

            $all = &$double->all();
            $all['b'] = 2;

            expect($double->all())->toBe(['a' => 1]);
        });

        if (PHP_VERSION_ID >= 80500) {
            it("hands a callback a default closure", function () {
                if (!interface_exists('DoubleSpecClosureDefault')) {
                    eval('interface DoubleSpecClosureDefault { public function run(\\Closure $then = static function (): int { return 9; }): int; }');
                }
                $double = Double::getInstance('DoubleSpecClosureDefault');

                allow($double->run())->toReturnUsing(fn(\Closure $then): int => $then());

                expect($double->run())->toBe(9);
            });
        }
    });

    context("an interface built on one PHP lets no plain class implement", function () {
        it("is an IteratorAggregate when built on Traversable, iterating as stubbed or empty", function () {
            $bag = Double::getInstance(DoubleSpecBag::class);
            allow($bag->getIterator())->toReturn(new ArrayIterator([1, 2]));

            expect(iterator_to_array($bag))->toBe([1, 2]);
            expect(iterator_to_array(dummy(DoubleSpecBag::class)))->toBe([]);
        });

        it("is an Exception when built on Throwable, keeping what Exception declares final", function () {
            $failure = Double::getInstance(DoubleSpecFailure::class);

            expect($failure)->toBeAnInstanceOf(Exception::class);
            expect($failure->getMessage())->toBe('');
            expect(fn() => throw $failure)->toThrow(DoubleSpecFailure::class);
        });

        it("is a DateTimeImmutable when built on DateTimeInterface, its methods stubbed", function () {
            $moment = Double::getInstance(DoubleSpecMoment::class);
            allow($moment->format('Y'))->toReturn('2026');

            expect($moment)->toBeAnInstanceOf(DateTimeImmutable::class);
            expect($moment->format('Y'))->toBe('2026');
        });

        it("refuses one only an enum can implement, saying why", function () {
            expect(fn() => Double::getInstance(DoubleSpecLabelled::class))
                ->toThrow(LogicException::class, 'Cannot create a test double for interface DoubleSpecLabelled. Only an enum can implement UnitEnum.');
        });

        it("stands in for each as a return type, refusing the enum-only one only when it is called", function () {
            $source = dummy(DoubleSpecNativeSource::class);

            expect($source->bag())->toBeAnInstanceOf(DoubleSpecBag::class);
            expect($source->failure())->toBeAnInstanceOf(DoubleSpecFailure::class);
            expect($source->moment())->toBeAnInstanceOf(DoubleSpecMoment::class);
            expect($source->counted())->toBeAnInstanceOf(DoubleSpecSized::class);
            expect($source->name())->toBe('');
            expect(fn() => $source->label())
                ->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of DoubleSpecNativeSource::label(): no double of DoubleSpecLabelled can be made. Only an enum can implement UnitEnum.');
        });

        it("doubles one built on Serializable without the deprecation", function () {
            $raised = [];
            set_error_handler(function (int $level, string $message) use (&$raised): bool {
                $raised[] = $message;

                return true;
            });

            try {
                Double::getInstance(DoubleSpecPayload::class);
            } finally {
                restore_error_handler();
            }

            expect($raised)->toBe([]);
        });
    });

    context("what a call nobody stubbed answers, of a class or interface", function () {
        it("is of that type, refusing to be used, naming the call it answered", function () {
            $page = Double::getInstance(DoubleSpecBook::class)->first();

            expect($page)->toBeAnInstanceOf(DoubleSpecPage::class);
            expect(double_spec_refusal(fn() => $page->line(3)))->toBe(sprintf('DoubleSpecPage::line(3) was called on what DoubleSpecBook::first() answered, at %s:%d. Stub DoubleSpecBook::first().', DOUBLE_SPEC_FILE, __LINE__));
        });

        it("is of every type an intersection names, each refusing to be used", function () {
            $numbered = Double::getInstance(DoubleSpecBook::class)->numbered();

            expect(double_spec_refusal(fn() => $numbered->number()))->toContain('DoubleSpecNumbered::number() was called on what DoubleSpecBook::numbered() answered');
            expect(double_spec_refusal(fn() => $numbered->title()))->toContain('DoubleSpecPage::title() was called on what DoubleSpecBook::numbered() answered');
        });

        it("is of the first type of a union that can have one", function () {
            expect(double_spec_refusal(fn() => Double::getInstance(DoubleSpecBook::class)->either()->title()))->toContain('DoubleSpecPage::title() was called on what DoubleSpecBook::either() answered');
        });
    });

    context("what a dummy answers of a class or interface", function () {
        it("is a dummy of it, so a chain is answered with defaults at any depth", function () {
            $page = dummy(DoubleSpecBook::class)->first();

            expect($page)->toBeAnInstanceOf(DoubleSpecPage::class);
            expect($page->next()->next()->line(3))->toBe('');
        });

        it("is a dummy of every type an intersection names", function () {
            $numbered = dummy(DoubleSpecBook::class)->numbered();

            expect($numbered->number())->toBe(0);
            expect($numbered->title())->toBe('');
        });

        it("is a dummy of the first type of a union that can have one", function () {
            expect(dummy(DoubleSpecBook::class)->either()->title())->toBe('');
        });

        it("refuses a stub or an expectation, naming the call a mock() should answer instead", function () {
            $page = dummy(DoubleSpecBook::class)->first();
            $refusal = 'DoubleSpecPage::next() was %s on what dummy DoubleSpecBook::first() answered. Use mock() instead of dummy() and stub DoubleSpecBook::first().';

            expect(double_spec_refusal(fn() => allow($page->next())))->toBe(sprintf($refusal, 'stubbed'));
            expect(double_spec_refusal(fn() => $page->next()->toReturn(dummy(DoubleSpecPage::class))))->toBe(sprintf($refusal, 'stubbed'));
            expect(double_spec_refusal(fn() => expect($page->next())->toReturn(dummy(DoubleSpecPage::class))))->toBe(sprintf($refusal, 'stubbed'));
            expect(double_spec_refusal(fn() => expect($page->next())->toBeCalled()))->toBe(sprintf($refusal, 'expected'));
            expect(double_spec_refusal(fn() => expect($page->next())->toHaveBeenCalled()))->toBe(sprintf($refusal, 'checked'));
        });

        it("names the chain that led to it, and the first call of it as the one to stub", function () {
            $deeper = dummy(DoubleSpecBook::class)->first()->next();

            expect(double_spec_refusal(fn() => allow($deeper->title())))->toBe('DoubleSpecPage::title() was stubbed on what dummy DoubleSpecBook::first()->next() answered. Use mock() instead of dummy() and stub DoubleSpecBook::first().');
            expect($deeper->price())->toBeAnInstanceOf(DoubleSpecCoin::class);
            expect(double_spec_refusal(fn() => double_spec_outsider_calls($deeper, 'price')))->toBe('PhpSpec cannot stand in for the return type of DoubleSpecPage::price(): DoubleSpecCoin is final, so no double of it can be made. Use mock() instead of dummy() and stub DoubleSpecBook::first().');
        });

        it("names a mock that ignores others as the double to stub the first call of the chain on", function () {
            $book = Double::getInstance(DoubleSpecBook::class);
            $book->______PhpSpecBeLenient();

            expect(double_spec_refusal(fn() => allow($book->first()->title())))->toBe('DoubleSpecPage::title() was stubbed on what dummy DoubleSpecBook::first() answered. Stub DoubleSpecBook::first() with a mock() instead.');
        });
    });

    context("a dummy", function () {
        it("refuses a stub or an expectation on any method", function () {
            $book = dummy(DoubleSpecBook::class);
            $stock = dummy(DoubleSpecStock::class);
            $refusal = '%s() was %s on a dummy. Use mock() instead of dummy().';

            expect(double_spec_refusal(fn() => allow($stock->reserve('tea', 2))->toReturn(true)))->toBe(sprintf($refusal, 'DoubleSpecStock::reserve', 'stubbed'));
            expect(double_spec_refusal(fn() => allow($book->first())))->toBe(sprintf($refusal, 'DoubleSpecBook::first', 'stubbed'));
            expect(double_spec_refusal(fn() => $book->first()->toReturn(dummy(DoubleSpecPage::class))))->toBe(sprintf($refusal, 'DoubleSpecBook::first', 'stubbed'));
            expect(double_spec_refusal(fn() => expect($stock->reserve('tea', 2))->toBeCalled()))->toBe(sprintf($refusal, 'DoubleSpecStock::reserve', 'expected'));
            expect(double_spec_refusal(fn() => expect($book->first())->toHaveBeenCalled()))->toBe(sprintf($refusal, 'DoubleSpecBook::first', 'checked'));
        });

        it("still has what it answered judged as any value", function () {
            expect(dummy(DoubleSpecStock::class)->reserve('tea', 2))->toBeFalse();
        });

        it("says to use mock() instead when it cannot stand in for a final return type the code under spec calls", function () {
            expect(dummy(DoubleSpecMachine::class)->coin())->toBeAnInstanceOf(DoubleSpecCoin::class);
            expect(double_spec_refusal(fn() => double_spec_outsider_calls(dummy(DoubleSpecMachine::class), 'coin')))->toBe('PhpSpec cannot stand in for the return type of DoubleSpecMachine::coin(): DoubleSpecCoin is final, so no double of it can be made. Use mock() instead of dummy() and stub DoubleSpecMachine::coin().');
        });
    });

    context("types declaring one method differently", function () {
        it("doubles an interface narrowing the getIterator() its base brings", function () {
            $bag = Double::getInstance(DoubleSpecNarrowBag::class);
            allow($bag->getIterator())->toReturn(new ArrayIterator([1]));

            expect(iterator_to_array($bag))->toBe([1]);
            expect(fn() => allow($bag->getIterator())->toReturn(new EmptyIterator()))->toThrow(LogicException::class, 'DoubleSpecNarrowBag::getIterator() returns ArrayIterator, so it cannot return EmptyIterator.');
        });

        it("doubles a Serializable interface declaring one of the methods its base brings", function () {
            expect(Double::getInstance(DoubleSpecHalfPayload::class))->toBeAnInstanceOf(DoubleSpecHalfPayload::class);
        });

        it("refuses at once a double no class can be", function () {
            expect(fn() => Double::getInstance(DoubleSpecNarrowFailure::class))->toThrow(LogicException::class, 'Cannot create a test double for interface DoubleSpecNarrowFailure. DoubleSpecNarrowFailure::getPrevious() narrows a method Exception declares final.');
        });

        it("answers with a double taking every call each declaration takes, and holds what it returns to every one of them", function () {
            $puts = dummy(DoubleSpecPutSource::class)->puts();
            $stub = Double::getInstance(DoubleSpecPutSource::class)->puts();

            expect($puts->put('cover'))->toBe(0);
            expect($puts->put('cover', 'blue'))->toBe(0);
            expect(double_spec_refusal(fn() => $stub->put('cover', 'blue')))->toContain('DoubleSpecShortPut::put("cover", "blue") was called on what DoubleSpecPutSource::puts() answered');
        });

        it("refuses only the call answering what no class can be", function () {
            $source = dummy(DoubleSpecPutSource::class);

            expect($source->count())->toBe(0);
            expect(fn() => $source->fills())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of DoubleSpecPutSource::fills(): no double of DoubleSpecFillByValue&DoubleSpecFillByReference can be made. DoubleSpecFillByValue::fill() takes $slots by value and DoubleSpecFillByReference::fill() takes $slots by reference.');
        });
    });

    it("implements the built-in magic method an interface declares, and keeps one its base implements", function () {
        expect((string) dummy(Stringable::class))->toBe('');
        expect((string) dummy(DoubleSpecFailure::class))->toContain('DoubleSpecFailure');
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

    it("throws the very exception instance a stub was given", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $notFound = new \RuntimeException('Not found');
        allow($double->greet())->toThrow($notFound);

        try {
            double_spec_outsider_greets($double);
        } catch (\RuntimeException $thrown) {
            expect($thrown)->toBe($notFound);

            return;
        }

        expect(false)->toBeTrue();
    });

    it("returns a usable default for a return type it cannot wrap: an empty iterable, a closure, a generator, itself", function() {
        $machine = Double::getInstance(DoubleSpecMachine::class);

        expect($machine->each())->toBe([]);
        expect($machine->hook())->toBeAnInstanceOf(\Closure::class);
        expect($machine->stream())->toBeAnInstanceOf(\Generator::class);
        expect($machine->same())->toBe($machine);
    });

    it("hands the spec a placeholder for a final return type, so allow() can stub it", function() {
        $machine = Double::getInstance(DoubleSpecMachine::class);
        $coin = new DoubleSpecCoin(50);

        expect($machine->coin())->toBeAnInstanceOf(DoubleSpecCoin::class);

        allow($machine->coin())->toReturn($coin);

        expect($machine->coin())->toBe($coin);
    });

    it("says why a lenient double cannot stand in for a final return type the code under spec calls, still handing the spec's own call one to stub", function() {
        $machine = Double::getInstance(DoubleSpecMachine::class);
        $machine->______PhpSpecBeLenient();
        $coin = new DoubleSpecCoin(5);

        expect(double_spec_refusal(fn() => double_spec_outsider_calls($machine, 'coin')))->toBe('PhpSpec cannot stand in for the return type of DoubleSpecMachine::coin(): DoubleSpecCoin is final, so no double of it can be made. Stub the call with allow(), return an interface, or make the class non-final.');

        allow($machine->coin())->toReturn($coin);

        expect(double_spec_outsider_calls($machine, 'coin'))->toBe($coin);
    });

    context("a call made from code outside the spec folder", function () {
        beforeEach(function () {
            $this->saved = ArrangingCode::roots();
            ArrangingCode::under(__DIR__);
        });

        afterEach(function () {
            ArrangingCode::under(...$this->saved);
        });

        it("holds a call nothing declared, answered with the type's default, and refuses it naming the call and where it came from", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);

            expect(double_spec_outsider_reserves($stock))->toBeFalse();

            $refusal = HeldCalls::take();
            expect($refusal)->toBeAnInstanceOf(UnexpectedCallException::class);
            expect($refusal?->getMessage())->toContain('DoubleSpecStock::reserve("tea", 2) was called but not expected');
            expect($refusal?->getMessage())->toContain(str_replace('\\', '/', realpath(DOUBLE_SPEC_OUTSIDER)) . ':2');
        });

        it("lets toHaveBeenCalled() after the act claim the call it checks", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);

            double_spec_outsider_reserves($stock);
            expect($stock->reserve('tea', 2))->toHaveBeenCalled();

            expect(HeldCalls::take())->toBeNull();
        });

        it("answers a call that allow() declared", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            allow($stock->reserve('tea', 2))->toReturn(true);

            expect(double_spec_outsider_reserves($stock))->toBeTrue();
        });

        it("answers a call that allow() declared without stubbing it, with the type's default", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            allow($stock->reserve('tea', 2));

            expect(double_spec_outsider_reserves($stock))->toBeFalse();
            expect($stock->reserve('tea', 2))->toHaveBeenCalled();
        });

        it("answers a call that expect() declared before the act", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            expect($stock->reserve('tea', 2))->toBeCalled();

            expect(double_spec_outsider_reserves($stock))->toBeFalse();
        });

        it("still refuses the same method with arguments nobody declared", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            allow($stock->reserve('coffee', 1))->toReturn(true);

            double_spec_outsider_reserves($stock);

            expect(HeldCalls::take()?->getMessage())->toContain('reserve("tea", 2)');
        });

        it("answers the rest with defaults once a stub ends in ignoreOthers()", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            allow($stock->reserve('coffee', 1))->toReturn(true)->ignoreOthers();

            expect(double_spec_outsider_reserves($stock))->toBeFalse();
        });

        it("refuses a chain nobody declared at its first call, before the use of what that call answered", function () {
            $book = Double::getInstance(DoubleSpecBook::class);

            expect(fn() => double_spec_outsider_reads($book))->toThrow(UnexpectedCallException::class);

            expect(HeldCalls::take()?->getMessage())->toContain('DoubleSpecBook::first() was called but not expected');
        });

        it("holds the use of what a declared call answered, so code that catches it still fails", function () {
            $book = Double::getInstance(DoubleSpecBook::class);
            allow($book->first());

            try {
                double_spec_outsider_reads($book);
            } catch (UnexpectedCallException) {
            }

            expect(HeldCalls::take()?->getMessage())->toContain('DoubleSpecPage::next() was called on what DoubleSpecBook::first() answered, at ' . str_replace('\\', '/', realpath(DOUBLE_SPEC_OUTSIDER)));
        });

        it("answers every call with a default once made lenient", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            $stock->______PhpSpecBeLenient();

            expect(double_spec_outsider_reserves($stock))->toBeFalse();
        });
    });

    it("counts method calls", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $double->greet();
        $double->greet();
        $stack = $double->______PhpSpecGetStubbedCalls();
        expect($stack->countCallsTo('greet'))->toBe(2);
    });

    it("lets expect() verify a call to a method whose return type allows null", function() {
        $double = Double::getInstance(DoubleSpecClass::class);
        $double->nullableReturn();

        expect($double->nullableReturn())->toHaveBeenCalled();
        expect($double->nullableUnionReturn())->not()->toHaveBeenCalled();
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

    it("names the interface it doubles when a method it does not define is called", function() {
        $double = Double::getInstance(DoubleSpecContract::class);

        expect(fn() => $double->undefinedOne())->toThrow(\Error::class, 'Call to undefined method DoubleSpecContract::undefinedOne()');
    });

    it("names the class it doubles when a method it does not define is called", function() {
        $double = Double::getInstance(DoubleSpecClass::class);

        expect(fn() => $double->undefinedOne())->toThrow(\Error::class, 'Call to undefined method DoubleSpecClass::undefinedOne()');
    });

    it("leaves a __call the doubled class defines in charge of unknown methods", function() {
        $double = Double::getInstance(DoubleSpecMagic::class);

        expect(fn() => $double->anything())->not()->toThrow();
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

    it("refuses to double a final class, naming the two ways out", function() {
        expect(fn() => Double::getInstance(DoubleSpecFinalClass::class))->toThrow(
            \LogicException::class,
            "Cannot create a test double for final class DoubleSpecFinalClass. Extract an interface and type-hint against it, or make the class non-final.",
        );
    });

    it("refuses to double a readonly class the same way", function() {
        expect(fn() => Double::getInstance(DoubleSpecReadonlyClass::class))->toThrow(
            \LogicException::class,
            "Cannot create a test double for readonly class DoubleSpecReadonlyClass. Extract an interface and type-hint against it, or make the class non-final.",
        );
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

    it("stubs return using callback on interface-return method via wrapper toReturnUsing", function() {
        $double = Double::getInstance(DoubleSpecClassWithDeps::class);
        $double->getDep()->toReturnUsing(fn() => mock(DoubleSpecContract::class));
        $result = $double->getDep();
        expect($result)->toBeAnInstanceOf(DoubleSpecContract::class);
    });

    it("stubs exception on void-return method via allow()->toThrow()", function() {
        $double = Double::getInstance(DoubleSpecVoidReturn::class);
        allow($double->doWork())->toThrow(\RuntimeException::class, 'void-fail');
        expect(fn() => $double->doWork())->toThrow(\RuntimeException::class, 'void-fail');
    });

    context("a call no object of the real class could take", function () {
        beforeEach(function () {
            $this->saved = ArrangingCode::roots();
            ArrangingCode::under(__DIR__);
        });

        afterEach(function () {
            ArrangingCode::under(...$this->saved);
        });

        it("is refused where the spec writes it with fewer arguments than the method requires, saying what to write", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            $remedy = ' Write the arguments it is called with, any() for one whose value does not matter, or cetera() for the rest.';

            expect(fn() => $stock->reserve())->toThrow(LogicException::class, 'DoubleSpecStock::reserve() takes 2 arguments (string $item, int $quantity), so a call written with none can never be made.' . $remedy);
            expect(fn() => $stock->reserve('tea'))->toThrow(LogicException::class, 'DoubleSpecStock::reserve() takes 2 arguments (string $item, int $quantity), so a call written with 1 can never be made.' . $remedy);
            expect(fn() => Double::getInstance(DoubleSpecTill::class)->pay())->toThrow(LogicException::class, "DoubleSpecTill::pay() takes at least 1 argument (int \$amount, string \$currency = 'EUR'), so a call written with none can never be made." . $remedy);
        });

        it("names the parameters as the method declares them, defaults included", function () {
            expect(fn() => Double::getInstance(DoubleSpecTill::class)->note())->toThrow(LogicException::class, "DoubleSpecTill::note() takes at least 1 argument (string \$text, array \$tags = [], ?string \$by = null, array \$seen = [...], bool \$loud = false), so a call written with none can never be made. Write the arguments it is called with, any() for one whose value does not matter, or cetera() for the rest.");
        });

        it("is refused where the spec writes it with more arguments than the method takes", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);

            expect(fn() => $stock->reserve('tea', 2, 'now'))->toThrow(LogicException::class, 'DoubleSpecStock::reserve() takes at most 2 arguments (string $item, int $quantity); the call is written with 3.');
            expect(fn() => Double::getInstance(DoubleSpecTill::class)->tally('a', 'b', 'c', 'd'))->not()->toThrow();
        });

        it("takes any() for one argument and cetera() for the rest, the rest being last", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);

            expect(fn() => $stock->reserve(any(), any()))->not()->toThrow();
            expect(fn() => $stock->reserve('tea', cetera()))->not()->toThrow();
            expect(fn() => $stock->reserve(cetera()))->not()->toThrow();
            expect(fn() => $stock->reserve(cetera(), 2))->toThrow(LogicException::class, 'cetera() stands for the rest of the arguments to DoubleSpecStock::reserve(), so nothing can follow it.');
        });

        it("fails a call the code under spec makes with too few arguments, as PHP would, however the method was declared", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            allow($stock->reserve(cetera()))->toReturn(true);

            try {
                double_spec_outsider_reserves_nothing($stock);
            } catch (ArgumentCountError $e) {
                expect($e->getMessage())->toBe(sprintf('Too few arguments to function DoubleSpecStock::reserve(), 0 passed in %s on line 3 and exactly 2 expected', str_replace('\\', '/', realpath(DOUBLE_SPEC_OUTSIDER))));

                return;
            }

            expect(false)->toBeTrue();
        });

        it("fails a call the code under spec makes with an argument of the wrong type, as PHP would", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            allow($stock->reserve(cetera()))->toReturn(true);

            expect(fn() => double_spec_outsider_reserves_with($stock, 'tea', 'two'))->toThrow(TypeError::class, sprintf('DoubleSpecStock::reserve(): Argument #2 ($quantity) must be of type int, string given, called in %s on line 5', str_replace('\\', '/', realpath(DOUBLE_SPEC_OUTSIDER))));
            expect(fn() => double_spec_strict_outsider_reserves_with($stock, 'tea', '2'))->toThrow(TypeError::class, sprintf('DoubleSpecStock::reserve(): Argument #2 ($quantity) must be of type int, string given, called in %s on line 5', str_replace('\\', '/', realpath(DOUBLE_SPEC_STRICT_OUTSIDER))));
        });

        it("takes an argument the way the code under spec hands it to the real object, coerced where its file allows", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);
            allow($stock->reserve('tea', 2))->toReturn(true);

            expect(double_spec_outsider_reserves_with($stock, 'tea', '2'))->toBeTrue();
        });

        it("is refused where the spec writes it with an argument the method could never take", function () {
            $stock = Double::getInstance(DoubleSpecStock::class);

            expect(fn() => $stock->reserve('tea', 'two'))->toThrow(LogicException::class, 'DoubleSpecStock::reserve(): Argument #2 ($quantity) must be of type int, string given, so a call written with it can never be made.');
            expect(fn() => $stock->reserve(any(), [2]))->toThrow(LogicException::class, 'DoubleSpecStock::reserve(): Argument #2 ($quantity) must be of type int, array given, so a call written with it can never be made.');
            expect(fn() => $stock->reserve('tea', any()))->not()->toThrow();
        });

        it("takes a call that leaves out an argument with a default as the call with that default", function () {
            $till = Double::getInstance(DoubleSpecTill::class);
            allow($till->pay(10))->toReturn(1);
            allow($till->pay(20, 'EUR'))->toReturn(2);

            expect(double_spec_outsider_pays($till, 10, 'EUR'))->toBe(1);
            expect(double_spec_outsider_pays($till, 20))->toBe(2);
            double_spec_outsider_pays($till, 10, 'USD');
            expect(HeldCalls::take())->toBeAnInstanceOf(UnexpectedCallException::class);
        });

        it("answers no stub to a call written with a matcher, which declares a call and is not one", function () {
            $till = Double::getInstance(DoubleSpecTill::class);
            allow($till->pay(cetera()))->toReturnUsing(fn(int $amount) => $amount * 2);
            allow($till->pay(any()))->toThrow(new \RuntimeException('Closed'));

            expect(double_spec_outsider_pays($till, 5))->toBe(10);
            expect(fn() => double_spec_outsider_pays($till, 5))->toThrow(\RuntimeException::class, 'Closed');
        });

        it("lets an error a stub's callback raises through, where it used to answer with a default", function () {
            $till = Double::getInstance(DoubleSpecTill::class);
            allow($till->pay(cetera()))->toReturnUsing(fn(int $amount) => intdiv($amount, 0));

            expect(fn() => double_spec_outsider_pays($till, 5))->toThrow(\DivisionByZeroError::class);
            allow($till->pay(cetera()))->toReturnUsing(fn(int $amount, string $currency, string $note) => 1);
            expect(fn() => double_spec_outsider_pays($till, 5))->toThrow(\ArgumentCountError::class);
        });
        it("holds a call written without arguments to a call made with none", function () {
            $till = Double::getInstance(DoubleSpecTill::class);
            allow($till->find())->toReturn('first');

            expect($till->find())->toBe('first');
            expect($till->find(0))->toBe('first');
            expect($till->find(5))->toBe('');
        });
    });

    context("a stub given what the method cannot return", function () {
        it("is refused where the value is not of the type the method returns, held strictly", function () {
            $ledger = Double::getInstance(DoubleSpecLedger::class);

            expect(fn() => allow($ledger->balance())->toReturn('42'))->toThrow(LogicException::class, 'DoubleSpecLedger::balance() returns int, so it cannot return string "42".');
            expect(fn() => expect($ledger->balance())->toReturn('42'))->toThrow(LogicException::class, 'DoubleSpecLedger::balance() returns int, so it cannot return string "42".');
            expect(fn() => allow($ledger->owner())->toReturn(new stdClass()))->toThrow(LogicException::class, 'DoubleSpecLedger::owner() returns ?DoubleSpecContract, so it cannot return stdClass.');
            expect(fn() => allow($ledger->either())->toReturn(1.5))->toThrow(LogicException::class, 'DoubleSpecLedger::either() returns string|int, so it cannot return float 1.5.');
            expect(fn() => allow($ledger->same())->toReturn(new stdClass()))->toThrow(LogicException::class, 'DoubleSpecLedger::same() returns static, so it cannot return stdClass.');
        });

        it("takes what the method could return", function () {
            $ledger = Double::getInstance(DoubleSpecLedger::class);
            allow($ledger->rate())->toReturn(3);
            allow($ledger->owner())->toReturn(null);
            allow($ledger->either())->toReturn('many');
            allow($ledger->same())->toReturn($ledger);

            expect($ledger->rate())->toBe(3.0);
            expect($ledger->either())->toBe('many');
        });

        it("answers a method returning true or false, unstubbed, with that value", function () {
            $kinds = Double::getInstance(DoubleSpecKinds::class);

            expect($kinds->yes())->toBeTrue();
            expect($kinds->no())->toBeFalse();
        });

        it("holds each kind of type as PHP holds a return under strict types", function () {
            $kinds = Double::getInstance(DoubleSpecKinds::class);
            $child = Double::getInstance(DoubleSpecChild::class);
            $holds = [
                [fn() => allow($kinds->flag()), false, 1],
                [fn() => allow($kinds->list()), [], 'x'],
                [fn() => allow($kinds->many()), new ArrayIterator([]), 'x'],
                [fn() => allow($kinds->action()), fn() => 1, 'not_a_function_anywhere'],
                [fn() => allow($kinds->thing()), new stdClass(), 'x'],
                [fn() => allow($kinds->me()), $kinds, new stdClass()],
                [fn() => allow($kinds->both()), new DoubleSpecBoth(), new stdClass()],
                [fn() => allow($kinds->yes()), true, false],
                [fn() => allow($child->up()), new DoubleSpecBase(), new stdClass()],
            ];

            foreach ($holds as [$stub, $could, $never]) {
                expect(fn() => $stub()->toReturn($could))->not()->toThrow();
                expect(fn() => $stub()->toReturn($never))->toThrow(LogicException::class);
            }
        });

        it("is refused on a method that returns void, which has nothing to return", function () {
            $ledger = Double::getInstance(DoubleSpecLedger::class);

            expect(fn() => allow($ledger->record('sale'))->toReturn(true))->toThrow(LogicException::class, 'DoubleSpecLedger::record() returns void, so toReturn() has nothing to give it: declare the call with allow() alone.');
        });

        it("holds what a callback answers to the type the method returns", function () {
            $ledger = Double::getInstance(DoubleSpecLedger::class);
            allow($ledger->balance())->toReturnUsing(fn() => 'lots');

            expect(fn() => $ledger->balance())->toThrow(LogicException::class, 'DoubleSpecLedger::balance() returns int, so its toReturnUsing() callback cannot answer string "lots".');
        });
    });

    context("stubs answering in the order they were declared", function () {
        beforeEach(function () {
            $this->saved = ArrangingCode::roots();
            ArrangingCode::under(__DIR__);
        });

        afterEach(function () {
            ArrangingCode::under(...$this->saved);
        });

        it("answer the code under spec one after the other, the last one answering on", function () {
            $till = Double::getInstance(DoubleSpecTill::class);
            allow($till->pay(1))->toThrow(new \RuntimeException('Declined'));
            allow($till->pay(1))->toReturn(5);

            expect(fn() => double_spec_outsider_pays($till, 1))->toThrow(\RuntimeException::class, 'Declined');
            expect(double_spec_outsider_pays($till, 1))->toBe(5);
            expect(double_spec_outsider_pays($till, 1))->toBe(5);
        });

        it("are used in order whatever arguments they overlap on", function () {
            $till = Double::getInstance(DoubleSpecTill::class);
            allow($till->find(any()))->toReturn('default');
            allow($till->find(42))->toReturn('special');

            expect(double_spec_outsider_finds($till, 42))->toBe('default');
            expect(double_spec_outsider_finds($till, 42))->toBe('special');
            expect(double_spec_outsider_finds($till, 99))->toBe('default');
        });

        it("are read by the spec without being used up, an exception showing only to toThrow()", function () {
            $till = Double::getInstance(DoubleSpecTill::class);
            allow($till->pay(2))->toReturn(7);
            allow($till->pay(3))->toThrow(new \RuntimeException('No'));

            expect($till->pay(2))->toBe(7);
            expect($till->pay(3))->toBe(0);
            expect(fn() => $till->pay(3))->toThrow(\RuntimeException::class, 'No');
            expect(double_spec_outsider_pays($till, 2))->toBe(7);
            expect(fn() => double_spec_outsider_pays($till, 3))->toThrow(\RuntimeException::class, 'No');
        });
    });

});