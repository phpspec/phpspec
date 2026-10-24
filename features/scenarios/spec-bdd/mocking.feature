Feature: Mocking
  As a developer
  I want to create test doubles for collaborators
  So that I can test objects in isolation

  Scenario: Creating a mock from an interface
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger)->toBeAnInstanceOf(App\Logger::class);
      """
    When I run the spec
    Then all examples should pass

  Scenario: A double stands in for a method that takes or returns by reference, or defaults to a new object
    Given a class "src/App/Shapes.php":
      """
      <?php

      namespace App;

      class Clock
      {
          public function __construct(public int $at = 0) {}
      }

      interface Mapping
      {
          public function set(array &$mapping): void;

          public function &all(): array;

          public function fill(&...$slots): int;

          public function at(Clock $clock = new Clock()): int;
      }

      class Holder
      {
          public function &get(): array
          {
              static $held = [];

              return $held;
          }
      }

      class UsesHolder
      {
          public function __construct(private Holder $holder) {}
      }
      """
    And a spec file "spec/App/Mapping.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Shapes.php';

      use App\Clock;
      use App\Holder;
      use App\Mapping;
      use App\UsesHolder;

      describe(Mapping::class, function () {
          it('takes an argument by reference', function () {
              $mapping = mock(Mapping::class);
              $map = ['version' => 1];

              expect($mapping->set($map))->toBeCalled();

              $mapping->set($map);
          });

          it('returns by reference', function () {
              $mapping = mock(Mapping::class);
              $holder = mock(Holder::class);

              allow($mapping->all())->toReturn(['a' => 1]);
              allow($holder->get())->toReturn(['b' => 2]);

              expect($mapping->all())->toBe(['a' => 1]);
              expect($holder->get())->toBe(['b' => 2]);
              expect(mock(UsesHolder::class))->toBeAnInstanceOf(UsesHolder::class);
          });

          it('takes a variadic by reference', function () {
              $mapping = mock(Mapping::class);
              $slot = 1;

              allow($mapping->fill($slot))->toReturn(3);

              expect($mapping->fill($slot))->toBe(3);
          });

          it('hands a callback the default a parameter builds with new', function () {
              $mapping = mock(Mapping::class);

              allow($mapping->at())->toReturnUsing(fn(Clock $clock): int => $clock->at + 7);

              expect($mapping->at())->toBe(7);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "4"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A stub keeps PHP's references and defaults, so what its callback writes reaches the caller
    Given a class "src/App/Cache.php":
      """
      <?php

      namespace App;

      class Clock
      {
          public function __construct(public int $at = 0) {}
      }

      interface Cache
      {
          public function get(string $key, ?array &$metadata = null): string;

          public function fill(&...$slots): int;

          public function &all(): array;

          public function at(Clock $clock = new Clock(), int $mode = 0): int;
      }
      """
    And a spec file "spec/App/Cache.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Cache.php';

      use App\Cache;
      use App\Clock;

      describe(Cache::class, function () {
          it('writes through an argument taken by reference', function () {
              $cache = mock(Cache::class);
              $anything = any();
              allow($cache->get('key', $anything))->toReturnUsing(function (string $key, ?array &$metadata): string {
                  $metadata = ['hit' => true];

                  return 'value';
              });
              $metadata = null;

              expect($cache->get('key', $metadata))->toBe('value');
              expect($metadata)->toBe(['hit' => true]);
          });

          it('writes through arguments taken by reference one by one', function () {
              $cache = mock(Cache::class);
              $anything = cetera();
              allow($cache->fill($anything))->toReturnUsing(function (&...$slots): int {
                  foreach ($slots as &$slot) {
                      $slot *= 10;
                  }

                  return count($slots);
              });
              $first = 1;
              $second = 2;

              expect($cache->fill($first, $second))->toBe(2);
              expect([$first, $second])->toBe([10, 20]);
          });

          it('hands back the reference a callback returns by reference', function () {
              $cache = mock(Cache::class);
              $store = ['a' => 1];
              allow($cache->all())->toReturnUsing(function &() use (&$store): array {
                  return $store;
              });

              $all = &$cache->all();
              $all['b'] = 2;

              expect($store)->toBe(['a' => 1, 'b' => 2]);
          });

          it('builds a default left out before a named argument', function () {
              $cache = mock(Cache::class);
              allow($cache->at(mode: 3))->toReturnUsing(fn(Clock $clock, int $mode): int => $clock->at + $mode);

              expect($cache->at(mode: 3))->toBe(3);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "4"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double sees the named arguments a variadic collects, and converts what it takes by reference as PHP does
    Given a class "src/App/Journal.php":
      """
      <?php

      namespace App;

      interface Journal
      {
          public function log(string $message, string ...$context): void;

          public function fill(&...$slots): int;

          public function take(int &$value): mixed;
      }
      """
    And a spec file "spec/App/Journal.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Journal.php';

      use App\Journal;

      describe(Journal::class, function () {
          it('records the named arguments a variadic collects', function () {
              $journal = mock(Journal::class);

              $journal->log('hi', user: 'ann');

              expect($journal->log('hi', user: 'ann'))->toHaveBeenCalled();
              expect($journal->log('hi', user: 'bob'))->not()->toHaveBeenCalled();
          });

          it('writes through named arguments a by-reference variadic collects', function () {
              $journal = mock(Journal::class);
              $anything = cetera();
              allow($journal->fill($anything))->toReturnUsing(function (&...$slots): int {
                  foreach ($slots as &$slot) {
                      $slot *= 10;
                  }

                  return count($slots);
              });
              $first = 1;
              $second = 2;

              expect($journal->fill(first: $first, second: $second))->toBe(2);
              expect([$first, $second])->toBe([10, 20]);
          });

          it('converts a scalar taken by reference, for the callback and the caller', function () {
              $journal = mock(Journal::class);
              $anything = any();
              allow($journal->take($anything))->toReturnUsing(fn($value) => $value);
              $value = '7';

              expect($journal->take($value))->toBe(7);
              expect($value)->toBe(7);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "3"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double stands in for an interface built on one PHP lets no plain class implement
    Given a class "src/App/Natives.php":
      """
      <?php

      namespace App;

      interface Bag extends \Traversable {}

      interface BagSource
      {
          public function items(): Bag;
      }

      interface Failure extends \Throwable {}

      interface Moment extends \DateTimeInterface {}

      interface Labelled extends \UnitEnum {}

      interface LabelSource
      {
          public function label(): Labelled;

          public function name(): string;
      }

      interface Payload extends \Serializable {}
      """
    And a spec file "spec/App/Natives.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Natives.php';

      use App\Bag;
      use App\BagSource;
      use App\Failure;
      use App\Labelled;
      use App\LabelSource;
      use App\Moment;
      use App\Payload;

      describe('Natives', function () {
          it('iterates a double of a Traversable interface, empty or as stubbed', function () {
              $bag = mock(Bag::class);
              allow($bag->getIterator())->toReturn(new ArrayIterator([1, 2]));

              expect(iterator_to_array(dummy(Bag::class)))->toBe([]);
              expect(iterator_to_array($bag))->toBe([1, 2]);
              expect(dummy(BagSource::class)->items())->toBeAnInstanceOf(Bag::class);
          });

          it('throws a double of a Throwable interface', function () {
              $failure = dummy(Failure::class);

              expect(fn() => throw $failure)->toThrow(Failure::class);
          });

          it('stubs a double of a DateTimeInterface interface', function () {
              $moment = mock(Moment::class);
              allow($moment->format('Y'))->toReturn('2026');

              expect($moment->format('Y'))->toBe('2026');
          });

          it('refuses a double of an interface only an enum can implement, and that return type only when called', function () {
              $source = dummy(LabelSource::class);

              expect(fn() => mock(Labelled::class))->toThrow(LogicException::class, 'Cannot create a test double for interface App\Labelled. Only an enum can implement UnitEnum.');
              expect($source->name())->toBe('');
              expect(fn() => $source->label())->toThrow(LogicException::class);
          });

          it('doubles a Serializable interface without a deprecation', function () {
              expect(dummy(Payload::class))->toBeAnInstanceOf(Payload::class);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "5"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double of interfaces declaring one method differently takes every call each declares and returns what each admits
    Given a class "src/App/Shelf.php":
      """
      <?php

      namespace App;

      interface NarrowBag extends \Traversable
      {
          public function getIterator(): \ArrayIterator;
      }

      interface HalfPayload extends \Serializable
      {
          public function __unserialize(array $data): void;
      }

      interface Pages
      {
          public function count(): int;
      }

      interface Indexed
      {
          public function at(int $page): string;
      }

      interface Wide
      {
          public function items(): Pages;

          public function put(string $key);
      }

      interface Narrow
      {
          public function items(): Indexed;

          public function put(string $key, string $value = 'none');
      }

      interface Sized
      {
          public function size(): int;
      }

      interface Named
      {
          public function size(): string;
      }

      class Paper {}

      class Ink {}

      interface Printed
      {
          public function sheet(): Paper;
      }

      interface Inked
      {
          public function sheet(): Ink;
      }

      interface ByValue
      {
          public function fill(array $slots);
      }

      interface ByReference
      {
          public function fill(array &$slots);
      }

      interface Failure extends \Throwable
      {
          public function getPrevious(): ?Failure;
      }

      interface Shelf
      {
          public function both(): Wide&Narrow;

          public function sized(): Sized&Named;

          public function printed(): Printed&Inked;

          public function filled(): ByValue&ByReference;

          public function bound(): Paper&Ink;

          public function title(): string;
      }
      """
    And a spec file "spec/App/Shelf.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Shelf.php';

      use App\Failure;
      use App\HalfPayload;
      use App\NarrowBag;
      use App\Shelf;

      describe('Shelf', function () {
          it('iterates a double of an interface narrowing getIterator()', function () {
              $bag = mock(NarrowBag::class);
              allow($bag->getIterator())->toReturn(new ArrayIterator([1, 2]));

              expect(iterator_to_array($bag))->toBe([1, 2]);
              expect(iterator_to_array(dummy(NarrowBag::class)))->toBe([]);
          });

          it('doubles a Serializable interface declaring __unserialize()', function () {
              expect(dummy(HalfPayload::class))->toBeAnInstanceOf(HalfPayload::class);
          });

          it('takes every call each interface declares, and returns what each admits', function () {
              $both = dummy(Shelf::class)->both();
              $both->put('cover');
              $both->put('cover', 'blue');

              expect($both->items()->count())->toBe(0);
              expect($both->items()->at(1))->toBe('');
          });

          it('refuses only a call no value can answer for every interface', function () {
              $shelf = dummy(Shelf::class);

              expect($shelf->title())->toBe('');
              expect(fn() => $shelf->sized()->size())->toThrow(LogicException::class, 'App\Sized::size() returns int and App\Named::size() returns string, so no value can be returned for both.');
              expect(fn() => $shelf->printed()->sheet())->toThrow(LogicException::class, 'App\Printed::sheet() returns App\Paper and App\Inked::sheet() returns App\Ink, so no value can be returned for both.');
              expect(fn() => $shelf->bound())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Shelf::bound(): no double of App\Paper&App\Ink can be made. No object can be both App\Paper and App\Ink.');
          });

          it('refuses a double no class can be, at once, and as a return type only when called', function () {
              $shelf = dummy(Shelf::class);

              expect(fn() => mock(Failure::class))->toThrow(LogicException::class, 'Cannot create a test double for interface App\Failure. App\Failure::getPrevious() narrows a method Exception declares final.');
              expect(fn() => $shelf->filled())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Shelf::filled(): no double of App\ByValue&App\ByReference can be made. App\ByValue::fill() takes $slots by value and App\ByReference::fill() takes $slots by reference.');
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "5"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double of a class with an interface keeps what the class already is, and names a class however it is written
    Given a class "src/App/Mixed.php":
      """
      <?php

      namespace App;

      class Paper {}

      class_alias(Paper::class, 'App\Sheet');

      interface ByName
      {
          public function get(): Paper;
      }

      interface ByAlias
      {
          public function get(): Sheet;
      }

      interface ByCase
      {
          public function get(): paper;
      }

      class Base
      {
          final public function value(): string
          {
              return 'kept';
          }
      }

      interface HasValue
      {
          public function value(): string;
      }

      class PlainBase {}

      interface Bag extends \Traversable {}

      final class Tally implements \Countable
      {
          public function count(): int
          {
              return 3;
          }
      }

      interface Failure extends \Throwable {}

      class Fault extends \Error {}

      interface Moment extends \DateTimeInterface {}

      class Clock extends \DateTime {}

      interface Marker {}

      interface Source
      {
          public function named(): ByName&ByAlias;

          public function cased(): ByName&ByCase;

          public function aliased(): Paper&Sheet;

          public function valued(): Base&HasValue;

          public function bag(): PlainBase&Bag;

          public function tally(): Tally&\Countable;

          public function fault(): Fault&Failure;

          public function clock(): Clock&Moment;

          public function marked(): \UnitEnum&Marker;

          public function failing(): PlainBase&Failure;

          public function title(): string;
      }

      class Teller
      {
          public function __construct(private Source $source) {}

          public function tally(): Tally
          {
              return $this->source->tally();
          }
      }
      """
    And a spec file "spec/App/Mixed.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Mixed.php';

      use App\Paper;
      use App\Source;
      use App\Tally;
      use App\Teller;

      describe('Mixed', function () {
          it('answers a class named through an alias or in another case as that class', function () {
              $source = dummy(Source::class);

              expect($source->named()->get())->toBeAnInstanceOf(Paper::class);
              expect($source->cased()->get())->toBeAnInstanceOf(Paper::class);
              expect($source->aliased())->toBeAnInstanceOf(Paper::class);
          });

          it('keeps the final method of the class it extends', function () {
              expect(dummy(Source::class)->valued()->value())->toBe('kept');
          });

          it('adds to a class the interface a subclass of it can implement', function () {
              expect(iterator_to_array(dummy(Source::class)->bag()))->toBe([]);
          });

          it('answers an intersection a final class already satisfies as that class', function () {
              $source = mock(Source::class);
              allow($source->tally())->toReturn(new Tally());

              expect($source->tally()->count())->toBe(3);
              expect(dummy(Source::class)->tally())->toBeAnInstanceOf(Tally::class);
              expect(fn() => (new Teller(dummy(Source::class)))->tally())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::tally(): App\Tally is final, so no double of it can be made. Use mock() instead of dummy() and stub App\Source::tally().');
          });

          it('keeps a class that already is what the interface needs', function () {
              $source = dummy(Source::class);

              expect($source->fault())->toBeAnInstanceOf(Error::class);
              expect($source->clock())->toBeAnInstanceOf(DateTime::class);
          });

          it('refuses only the call answering what no double can be', function () {
              $source = dummy(Source::class);

              expect($source->title())->toBe('');
              expect(fn() => $source->failing())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::failing(): no double of App\PlainBase&App\Failure can be made. No object can be both App\PlainBase and App\Failure.');
              expect(fn() => $source->marked())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::marked(): no double of UnitEnum&App\Marker can be made. Only an enum can implement UnitEnum.');
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "6"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double of a class with a Serializable or Traversable interface builds on what the class already has
    Given a class "src/App/Built.php":
      """
      <?php

      namespace App;

      interface Payload extends \Serializable {}

      interface Bag extends \Traversable {}

      class Open
      {
          public function __serialize(): array
          {
              return ['open'];
          }
      }

      class Sealed
      {
          final public function __unserialize(array $data): void {}
      }

      class Aggregated
      {
          public function getIterator(): \ArrayIterator
          {
              return new \ArrayIterator([1]);
          }
      }

      class Listing
      {
          public function getIterator(): array
          {
              return [];
          }
      }

      class Locked
      {
          final public function getIterator(): array
          {
              return [];
          }
      }

      class Cursor implements \Iterator
      {
          public function current(): mixed { return 1; }
          public function key(): mixed { return 0; }
          public function next(): void {}
          public function rewind(): void {}
          public function valid(): bool { return true; }
      }

      class Endless
      {
          public function getIterator(): array
          {
              return [];
          }

          final public function valid(): true
          {
              return true;
          }
      }

      class Hopeless
      {
          final public function getIterator(): array
          {
              return [];
          }

          final public function next(): int
          {
              return 0;
          }
      }

      interface Source
      {
          public function open(): Open&Payload;

          public function sealed(): Sealed&Payload;

          public function aggregated(): Aggregated&Bag;

          public function listing(): Listing&Bag;

          public function locked(): Locked&Bag;

          public function cursor(): Cursor&Bag;

          public function endless(): Endless&Bag;

          public function hopeless(): Hopeless&Bag;
      }
      """
    And a spec file "spec/App/Built.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Built.php';

      use App\Open;
      use App\Payload;
      use App\Sealed;
      use App\Source;

      describe('Built', function () {
          it('keeps the serialization methods the class already has', function () {
              $source = dummy(Source::class);

              expect($source->open())->toBeAnInstanceOf(Payload::class);
              expect($source->open())->toBeAnInstanceOf(Open::class);
              expect($source->sealed())->toBeAnInstanceOf(Sealed::class);
          });

          it('aggregates where the class has a getIterator() an aggregate can have', function () {
              expect(dummy(Source::class)->aggregated())->toBeAnInstanceOf(IteratorAggregate::class);
          });

          it('iterates as an Iterator, empty, where the class has a getIterator() no aggregate can have', function () {
              $source = dummy(Source::class);

              expect(iterator_to_array($source->listing()))->toBe([]);
              expect(iterator_to_array($source->locked()))->toBe([]);
              expect($source->locked())->toBeAnInstanceOf(Iterator::class);
          });

          it('keeps the iterator the class already is', function () {
              expect(iterator_to_array(dummy(Source::class)->cursor()))->toBe([]);
          });

          it('refuses only the call answering a class whose iteration it cannot end, or that cannot iterate', function () {
              $source = dummy(Source::class);

              expect(fn() => $source->endless())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::endless(): no double of App\Endless&App\Bag can be made. App\Endless declares valid() final, so a double of it cannot end its iteration.');
              expect(fn() => $source->hopeless())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::hopeless(): no double of App\Hopeless&App\Bag can be made. No object can be both App\Hopeless and App\Bag.');
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "5"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double of a class with a Traversable interface decides its iterator through self-references and static methods, and only where it can end
    Given a class "src/App/Loops.php":
      """
      <?php

      namespace App;

      interface Bag extends \Traversable {}

      interface Failure extends \Throwable {}

      interface HasValue
      {
          public function value(): string;
      }

      interface Ticker extends \Iterator
      {
          public function valid(): true;
      }

      class Collection
      {
          public function getIterator(): self
          {
              return $this;
          }
      }

      class Outer
      {
          public function getIterator(): Ring
          {
              return new Ring();
          }
      }

      class Ring
      {
          public function getIterator(): Outer
          {
              return new Outer();
          }
      }

      class Coil
      {
          public function getIterator(): Coil&Failure
          {
              throw new \LogicException();
          }
      }

      class Shelf
      {
          public static function getIterator(): \ArrayIterator
          {
              return new \ArrayIterator([]);
          }
      }

      class Valued
      {
          public static function value(): string
          {
              return 'static';
          }
      }

      class Hidden
      {
          private static function value(): string
          {
              return 'hidden';
          }
      }

      class Forever
      {
          public function getIterator(): array
          {
              return [];
          }

          public function valid(): true
          {
              return true;
          }
      }

      interface Source
      {
          public function collection(): Collection&Bag;

          public function outer(): Outer&Bag;

          public function coil(): Coil&Bag;

          public function shelf(): Shelf&Bag;

          public function valued(): Valued&HasValue;

          public function hidden(): Hidden&HasValue;

          public function forever(): Forever&Bag;
      }
      """
    And a spec file "spec/App/Loops.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Loops.php';

      use App\Collection;
      use App\Outer;
      use App\Source;
      use App\Ticker;

      describe('Loops', function () {
          it('answers a class whose getIterator() returns itself, or a class returning it back', function () {
              $source = dummy(Source::class);

              expect($source->collection())->toBeAnInstanceOf(Collection::class);
              expect($source->outer())->toBeAnInstanceOf(Outer::class);
          });

          it('settles a self-reference running into what no object can be as an Iterator', function () {
              expect(iterator_to_array(dummy(Source::class)->coil()))->toBe([]);
          });

          it('iterates as an Iterator a class whose getIterator() is static', function () {
              expect(iterator_to_array(dummy(Source::class)->shelf()))->toBe([]);
          });

          it('refuses a class declaring static what an interface declares on an object, unless it is private', function () {
              $source = dummy(Source::class);

              expect(fn() => $source->valued())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::valued(): no double of App\Valued&App\HasValue can be made. No object can be both App\Valued and App\HasValue.');
              expect($source->hidden()->value())->toBe('');
          });

          it('refuses a double whose valid() cannot return false, which could never end its iteration', function () {
              expect(fn() => dummy(Source::class)->forever())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::forever(): no double of App\Forever&App\Bag can be made. App\Forever declares valid() returning true, so a double of it cannot end its iteration.');
              expect(fn() => mock(Ticker::class))->toThrow(LogicException::class, 'Cannot create a test double for interface App\Ticker. App\Ticker declares valid() returning true, so a double of it cannot end its iteration.');
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "5"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A dummy aggregate answers its getIterator() with an empty iterator, or says what keeps it from one when iterated
    Given a class "src/App/Iterated.php":
      """
      <?php

      namespace App;

      interface Bag extends \Traversable {}

      class Collection
      {
          public function getIterator(): self
          {
              return $this;
          }
      }

      class Aggregate implements \IteratorAggregate
      {
          public function getIterator(): self
          {
              return $this;
          }
      }

      class Stuck
      {
          public function getIterator(): array
          {
              return [];
          }

          final public function valid(): bool
          {
              return true;
          }
      }

      interface Source
      {
          public function collection(): Collection&Bag;
      }

      interface Shelf extends \IteratorAggregate
      {
          public function getIterator(): Aggregate;
      }

      interface Stalled extends \IteratorAggregate
      {
          public function getIterator(): Stuck&Bag;
      }
      """
    And a spec file "spec/App/Iterated.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Iterated.php';

      use App\Aggregate;
      use App\Shelf;
      use App\Source;
      use App\Stalled;

      describe('Iterated', function () {
          it('iterates as empty a dummy whose getIterator() returns its own class', function () {
              expect(iterator_to_array(dummy(Source::class)->collection()))->toBe([]);
          });

          it('refuses an empty iterator only when the unstubbed getIterator() is called, naming what keeps it from one', function () {
              $shelf = dummy(Shelf::class);

              expect($shelf)->toBeAnInstanceOf(Shelf::class);
              expect(fn() => iterator_to_array($shelf))->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Shelf::getIterator(): no empty iterator of App\Aggregate can be made. App\Aggregate::getIterator() returns App\Aggregate, so its iteration could not end.');
              expect(fn() => iterator_to_array(dummy(Stalled::class)))->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Stalled::getIterator(): no empty iterator of App\Stuck&App\Bag can be made. App\Stuck declares valid() final, so a double of it cannot end its iteration.');
          });

          it('leaves a mock to stub what getIterator() returns', function () {
              $shelf = mock(Shelf::class);
              $aggregate = new Aggregate();
              allow($shelf->getIterator())->toReturn($aggregate);

              expect($shelf->getIterator())->toBe($aggregate);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "3"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double of a class with an interface holds the class's protected final methods to the interface, and leaves its private ones out
    Given a class "src/App/Visible.php":
      """
      <?php

      namespace App;

      interface Bag extends \Traversable {}

      interface Rewinding
      {
          public function rewind(): void;
      }

      class Guarded
      {
          public function getIterator(): self
          {
              return $this;
          }

          final protected function rewind(): void {}
      }

      class Locked
      {
          final protected function rewind(): void {}
      }

      class Hidden
      {
          public function getIterator(): self
          {
              return $this;
          }

          private function valid(): true
          {
              return true;
          }
      }

      interface Source
      {
          public function guarded(): Guarded&Bag;

          public function locked(): Locked&Rewinding;

          public function hidden(): Hidden&Bag;
      }
      """
    And a spec file "spec/App/Visible.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Visible.php';

      use App\Source;

      describe('Visible', function () {
          it('refuses only the call needing the protected final method to be public', function () {
              $source = dummy(Source::class);

              expect(fn() => iterator_to_array($source->guarded()))->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Guarded&App\Bag::getIterator(): no empty iterator of App\Guarded&Traversable can be made. Iterator::rewind() narrows a method App\Guarded declares final.');
              expect(fn() => $source->locked())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::locked(): no double of App\Locked&App\Rewinding can be made. App\Rewinding::rewind() narrows a method App\Locked declares final.');
          });

          it('ends the iteration of a class whose valid() is private', function () {
              expect(iterator_to_array(dummy(Source::class)->hidden()))->toBe([]);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "2"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A dummy aggregate answers its getIterator() with an aggregate whose iteration ends, whichever member of a union it is
    Given a class "src/App/Nested.php":
      """
      <?php

      namespace App;

      class Cursor implements \Iterator
      {
          public function rewind(): void {}
          public function next(): void {}
          public function current(): mixed { return null; }
          public function key(): mixed { return null; }
          public function valid(): bool { return false; }
      }

      class Healthy implements \IteratorAggregate
      {
          public function getIterator(): \ArrayIterator
          {
              return new \ArrayIterator([1, 2]);
          }
      }

      class Middle implements \IteratorAggregate
      {
          public function getIterator(): Healthy
          {
              return new Healthy();
          }
      }

      class Selfish implements \IteratorAggregate
      {
          public function getIterator(): self
          {
              return $this;
          }
      }

      interface Single extends \IteratorAggregate
      {
          public function getIterator(): Healthy;
      }

      interface Nested extends \IteratorAggregate
      {
          public function getIterator(): Middle;
      }

      interface Forward extends \IteratorAggregate
      {
          public function getIterator(): Healthy|Cursor;
      }

      interface Backward extends \IteratorAggregate
      {
          public function getIterator(): Cursor|Healthy;
      }

      interface LoopFirst extends \IteratorAggregate
      {
          public function getIterator(): Selfish|Cursor;
      }

      interface Looping extends \IteratorAggregate
      {
          public function getIterator(): Selfish;
      }

      interface PingPong extends \IteratorAggregate
      {
          public function getIterator(): Ping;
      }
      """
    And a class "src/App/Ping.php":
      """
      <?php

      namespace App;

      class Ping implements \IteratorAggregate
      {
          public function getIterator(): Pong
          {
              return new Pong();
          }
      }
      """
    And a class "src/App/Pong.php":
      """
      <?php

      namespace App;

      class Pong implements \IteratorAggregate
      {
          public function getIterator(): Ping
          {
              return new Ping();
          }
      }
      """
    And a spec file "spec/App/Nested.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Nested.php';

      use App\Backward;
      use App\Forward;
      use App\LoopFirst;
      use App\Looping;
      use App\Nested;
      use App\PingPong;
      use App\Single;

      describe('Nested', function () {
          it('iterates as empty through aggregates whose iteration ends', function () {
              expect(iterator_to_array(dummy(Single::class)))->toBe([]);
              expect(iterator_to_array(dummy(Nested::class)))->toBe([]);
          });

          it('answers a union with its first member whose iteration ends, whatever their order', function () {
              expect(iterator_to_array(dummy(Forward::class)))->toBe([]);
              expect(iterator_to_array(dummy(Backward::class)))->toBe([]);
              expect(iterator_to_array(dummy(LoopFirst::class)))->toBe([]);
          });

          it('refuses an aggregate whose iteration comes back to it, naming where', function () {
              expect(fn() => iterator_to_array(dummy(Looping::class)))->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Looping::getIterator(): no empty iterator of App\Selfish can be made. App\Selfish::getIterator() returns App\Selfish, so its iteration could not end.');
              expect(fn() => iterator_to_array(dummy(PingPong::class)))->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\PingPong::getIterator(): no empty iterator of App\Ping can be made. App\Pong::getIterator() returns App\Ping, so its iteration could not end.');
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "3"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A dummy aggregate never iterates an aggregate already being iterated, and ends at a generator
    Given a class "src/App/Narrowed.php":
      """
      <?php

      namespace App;

      class Broad implements \IteratorAggregate
      {
          public function getIterator(): \Traversable
          {
              return new \ArrayIterator([]);
          }
      }

      class Cursor implements \Iterator
      {
          public function rewind(): void {}
          public function next(): void {}
          public function current(): mixed { return null; }
          public function key(): mixed { return null; }
          public function valid(): bool { return false; }
      }

      interface Narrow extends \IteratorAggregate
      {
          public function getIterator(): (Broad&Narrow)|Cursor;
      }

      interface Entry extends \IteratorAggregate
      {
          public function getIterator(): Broad&Narrow;
      }

      class Yielding implements \IteratorAggregate
      {
          public function getIterator(): \Generator
          {
              yield 1;
          }
      }

      interface Yielded extends \IteratorAggregate
      {
          public function getIterator(): Yielding;
      }
      """
    And a spec file "spec/App/Narrowed.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Narrowed.php';

      use App\Entry;
      use App\Yielded;

      describe('Narrowed', function () {
          it('takes the member that ends where an interface narrows getIterator() back to an aggregate being iterated', function () {
              expect(iterator_to_array(dummy(Entry::class)))->toBe([]);
          });

          it('iterates as empty an aggregate whose getIterator() returns a generator', function () {
              expect(iterator_to_array(dummy(Yielded::class)))->toBe([]);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "2"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double implements an abstract static method by refusing it, and keeps a concrete one
    Given a class "src/App/Statics.php":
      """
      <?php

      namespace App;

      interface FromData
      {
          public static function fromData(array $data): static;
      }

      interface Factory
      {
          public function make(): FromData;
      }

      abstract class Model
      {
          abstract public static function named(string $name): static;

          public static function create(): static
          {
              return new static();
          }

          public function name(): string
          {
              return 'model';
          }
      }

      interface Named
      {
          public static function named(string $name): static;
      }

      interface Instanced
      {
          public function named(string $name): static;
      }

      interface Source
      {
          public function both(): Named&Instanced;

          public function title(): string;
      }
      """
    And a spec file "spec/App/Statics.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Statics.php';

      use App\Factory;
      use App\FromData;
      use App\Model;
      use App\Source;

      describe('Statics', function () {
          it('doubles an interface with a static method, which refuses to be called', function () {
              $data = mock(FromData::class);

              expect($data)->toBeAnInstanceOf(FromData::class);
              expect(fn() => $data::fromData([]))->toThrow(LogicException::class, 'App\FromData::fromData() is static, so no double can answer it.');
          });

          it('answers a method returning such an interface', function () {
              expect(dummy(Factory::class)->make())->toBeAnInstanceOf(FromData::class);
          });

          it('keeps a static method the class implements, and refuses its abstract one', function () {
              $model = mock(Model::class);

              expect($model::create())->toBeAnInstanceOf(Model::class);
              expect(fn() => $model::named('box'))->toThrow(LogicException::class, 'App\Model::named() is static, so no double can answer it.');
          });

          it('refuses only the call answering types declaring one method static and on an object', function () {
              $source = dummy(Source::class);

              expect($source->title())->toBe('');
              expect(fn() => $source->both())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Source::both(): no double of App\Named&App\Instanced can be made. App\Named::named() is static and App\Instanced::named() is not.');
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "4"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A method returning a built-in interface answers with a double of it, and allow() stubs what the spec's own call names
    Given a class "src/App/Clock.php":
      """
      <?php

      namespace App;

      final class Coin {}

      interface Clock
      {
          public function now(): \DateTimeInterface;

          public function size(): \Countable;

          public function label(): \Stringable;

          public function data(): \JsonSerializable;

          public function failure(): \Throwable;

          public function items(): \Traversable;

          public function coin(): Coin;

          public function lock(): \WeakMap;
      }
      """
    And a spec file "spec/App/Clock.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Clock.php';

      use App\Clock;
      use App\Coin;

      describe('Clock', function () {
          it('stubs a method returning DateTimeInterface', function (Clock $clock) {
              allow($clock->now())->toReturn(new DateTimeImmutable('2026-01-01'));

              expect($clock->now()->format('Y'))->toBe('2026');
          });

          it('answers every built-in interface a method returns with a dummy of it', function () {
              $clock = dummy(Clock::class);

              expect($clock->now()->format('Y'))->toBe('');
              expect(count($clock->size()))->toBe(0);
              expect((string) $clock->label())->toBe('');
              expect($clock->data()->jsonSerialize())->toBeNull();
              expect($clock->failure())->toBeAnInstanceOf(Throwable::class);
              expect(iterator_to_array($clock->items()))->toBe([]);
          });

          it('stubs a final return type on a mock that ignores the rest', function (Clock $clock) {
              allow($clock->now())->toReturn(new DateTimeImmutable())->ignoreOthers();
              allow($clock->coin())->toReturn(new Coin());

              expect($clock->coin())->toBeAnInstanceOf(Coin::class);
          });

          it('refuses a return type no double can stand in for, giving no advice no stub could follow', function () {
              expect(fn() => dummy(Clock::class)->lock())->toThrow(LogicException::class, 'PhpSpec cannot stand in for the return type of App\Clock::lock(): WeakMap is final, so no double of it can be made.');
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "4"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double answers a class whose constructor is final, null for a nullable built-in interface, and a session handler without PHP's warnings
    Given a class "src/App/Sources.php":
      """
      <?php

      namespace App;

      class Shell
      {
          final public function __construct() {}

          public function name(): string
          {
              return 'shell';
          }
      }

      interface Source
      {
          public function shell(): Shell;

          public function maybeNow(): ?\DateTimeInterface;

          public function maybeSize(): ?\Countable;

          public function session(): \SessionHandlerInterface;
      }
      """
    And a spec file "spec/App/Sources.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Sources.php';

      use App\Shell;
      use App\Source;

      describe('Sources', function () {
          it('answers a class whose constructor is final', function () {
              $source = mock(Source::class);
              $shell = new Shell();
              allow($source->shell())->toReturn($shell);

              expect($source->shell())->toBe($shell);
              expect(dummy(Source::class)->shell()->name())->toBe('');
          });

          it('answers null for a nullable built-in interface, and still takes a stub', function () {
              $source = mock(Source::class);
              $now = new DateTimeImmutable('2026-01-01');
              allow($source->maybeNow())->toReturn($now);

              expect($source->maybeNow())->toBe($now);
              expect(dummy(Source::class)->maybeNow())->toBeNull();
              expect(dummy(Source::class)->maybeSize())->toBeNull();
          });

          it('answers a session handler', function () {
              expect(dummy(Source::class)->session())->toBeAnInstanceOf(SessionHandlerInterface::class);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "3"
    And the summary should state "warnings" as "0"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: A double of a class is made without running its constructor
    Given a class "src/App/Built.php":
      """
      <?php

      namespace App;

      final readonly class Money
      {
          public function __construct(public int $cents) {}
      }

      class Till
      {
          public function __construct(private Money $float) {}

          public function total(): int
          {
              return $this->float->cents;
          }
      }

      interface Rates
      {
          public function rate(): float;
      }

      class Converter
      {
          private float $rate;

          public function __construct(Rates $rates)
          {
              $this->rate = $rates->rate();
          }

          public function convert(int $cents): int
          {
              return (int) ($cents * $this->rate);
          }
      }

      class Guarded
      {
          public int $calls = 3;

          public function __construct(public string $zone)
          {
              if ($zone === '') {
                  throw new \InvalidArgumentException('A zone is needed.');
              }
          }
      }
      """
    And a spec file "spec/App/Built.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/Built.php';

      use App\Converter;
      use App\Guarded;
      use App\Till;

      describe('Built', function () {
          it('doubles a class whose constructor needs a readonly class', function () {
              expect(mock(Till::class)->total())->toBe(0);
          });

          it('doubles a class whose constructor calls what it is given, refusing none of it', function () {
              expect(mock(Converter::class)->convert(5))->toBe(0);
          });

          it('doubles a class whose constructor would refuse made-up arguments, keeping declared defaults', function () {
              $guarded = mock(Guarded::class);

              expect($guarded->calls)->toBe(3);
              expect(fn() => $guarded->zone)->toThrow(Error::class);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the summary should state "passing" as "3"
    And the summary should state "deprecations" as "0"
    And the summary should state "notices" as "0"
    And the summary should state "actionable" as "0"

  Scenario: Typed satisfy() predicates on one method take only values, however many declarations it has
    Given a class "src/App/Members.php":
      """
      <?php
      namespace App;

      final class Member
      {
          public function __construct(public string $name) {}
      }

      interface Members
      {
          public function save(Member $member): bool;
      }

      class Signup
      {
          public function __construct(private Members $members) {}

          public function join(string $name): bool
          {
              return $this->members->save(new Member($name));
          }
      }
      """
    And a spec file "spec/App/Signup.spec.php":
      """
      <?php
      require_once __DIR__ . '/../../src/App/Members.php';

      use App\Member;
      use App\Members;
      use App\Signup;

      describe('Signup', function () {
          it('answers each member as its declaration says', function (Members $members) {
              allow($members->save(satisfy(fn (Member $member) => $member->name === 'Alice')))->toReturn(true);
              allow($members->save(satisfy(fn (Member $member) => $member->name === 'Bob')))->toReturn(false);

              expect((new Signup($members))->join('Alice'))->toBeTrue();
              expect((new Signup($members))->join('Bob'))->toBeFalse();
          });

          it('checks a call it declared the same way', function (Members $members) {
              allow($members->save(satisfy(fn (Member $member) => $member->name === 'Alice')))->toReturn(true);

              (new Signup($members))->join('Alice');

              expect($members->save(satisfy(fn (Member $member) => $member->name === 'Alice')))->toHaveBeenCalled();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Verifying a method was called
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('hello'))->toBeCalled();
      $logger->log('hello');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Verifying a method was called with specific arguments
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('hello'))->toBeCalled();
      $logger->log('hello');
      """
    When I run the spec
    Then all examples should pass

  Scenario: A count on toHaveBeenCalled() is judged at once from the calls made so far
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      $logger->log('hello');
      $logger->log('hello');
      expect($logger->log('hello'))->toHaveBeenCalled()->twice();
      expect($logger->log('hello'))->toHaveBeenCalled()->atLeast(1)->times();
      expect($logger->log(any()))->toHaveBeenCalled()->exactly(2)->times();
      """
    When I run the spec
    Then all examples should pass

  Scenario: A count the calls made so far do not meet fails on its line, naming the count received
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      $logger->log('hello');
      $logger->log('hello');
      expect($logger->log('hello'))->toHaveBeenCalled()->once();
      """
    When I run the spec
    Then the output should contain "Expected App\Logger::log() to have been called exactly 1 time(s), but was called 2 time(s)"
    And the exit code should be 1

  Scenario: toHaveBeenCalled() judges the calls made before it was written
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      $logger->log('paid');
      expect($logger->log('paid'))->toHaveBeenCalled();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Verifying call count
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;

      interface Counter {
          public function increment(): void;
      }
      """
    And a spec with example:
      """
      $counter = mock(App\Counter::class);
      expect($counter->increment())->toBeCalled()->exactly(3)->times();
      $counter->increment();
      $counter->increment();
      $counter->increment();
      """
    When I run the spec
    Then all examples should pass

  Scenario: An equal value object matches a stub and a verification
    Given a class "src/App/Money.php":
      """
      <?php
      namespace App;

      class Money {
          public function __construct(public readonly int $pence) {}
      }
      """
    And an interface "src/App/Discount.php":
      """
      <?php
      namespace App;

      interface Discount {
          public function applyTo(Money $amount): Money;
      }
      """
    And an interface "src/App/Ledger.php":
      """
      <?php
      namespace App;

      interface Ledger {
          public function record(Money $amount): void;
      }
      """
    And a spec with example:
      """
      $discount = mock(App\Discount::class);
      allow($discount->applyTo(new App\Money(900)))->toReturn(new App\Money(720));
      expect($discount->applyTo(new App\Money(900))->pence)->toBe(720);

      $ledger = mock(App\Ledger::class);
      $ledger->record(new App\Money(5));
      expect($ledger->record(new App\Money(5)))->toHaveBeenCalled();
      """
    When I run the spec
    Then all examples should pass

  Scenario: A call stubbed first can still be verified
    Given an interface "src/App/Catalogue.php":
      """
      <?php
      namespace App;

      interface Catalogue {
          public function name(): string;
      }
      """
    And a spec with example:
      """
      $catalogue = mock(App\Catalogue::class);
      allow($catalogue->name())->toReturn('winter');
      expect($catalogue->name())->toBeCalled();

      expect($catalogue->name())->toBe('winter');
      """
    When I run the spec
    Then all examples should pass

  Scenario: A mock injected into a this() is there as a property, as the docs show
    Given an interface "src/App/Mailer.php":
      """
      <?php
      namespace App;

      interface Mailer {
          public function send(string $message): void;
      }
      """
    And a class "src/App/Notifier.php":
      """
      <?php
      namespace App;

      final class Notifier {
          public function __construct(private Mailer $mailer) {}
          public function notify(string $message): void { $this->mailer->send($message); }
      }
      """
    And a spec file "spec/App/Notifier.spec.php":
      """
      <?php
      describe('App\Notifier', function () {
          this('notifier', fn (App\Mailer $mailer) => new App\Notifier($mailer));

          it('sends an email', function () {
              expect($this->mailer->send('hello'))->toBeCalled();
              $this->notifier->notify('hello');
          });
      });
      """
    When I run the spec
    Then all examples should pass

  Scenario: An unstubbed method returns a usable default for a type no double can stand in for
    Given an interface "src/App/Machine.php":
      """
      <?php
      namespace App;

      interface Machine {
          public function each(): iterable;
          public function hook(): \Closure;
          public function stream(): \Generator;
          public function same(): static;
      }
      """
    And a spec with example:
      """
      $machine = mock(App\Machine::class);
      expect($machine->each())->toBe([]);
      expect($machine->hook())->toBeAnInstanceOf(\Closure::class);
      expect($machine->stream())->toBeAnInstanceOf(\Generator::class);
      expect($machine->same())->toBe($machine);
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubbing a return value
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->toReturn('Alice');
      expect($repo->find(1))->toBe('Alice');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubbing a return value with andReturn()
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->andReturn('Alice');
      expect($repo->find(1))->toBe('Alice');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubbing a method to throw the exception it is given
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(999))->toThrow(new \RuntimeException('Not found'));
      expect(fn () => $repo->find(999))->toThrow(\RuntimeException::class, 'Not found');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubbing with a callback for dynamic returns
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->toReturnUsing(fn($id) => $id === 1 ? 'Alice' : null);
      expect($repo->find(1))->toBe('Alice');
      expect($repo->find(999))->toBeNull();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Argument matchers
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log(any()))->toBeCalled();
      $logger->log('hello world');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Negated mock expectations
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('anything'))->not()->toBeCalled();
      """
    When I run the spec
    Then all examples should pass

  Scenario: instanceOf argument matcher
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $message): void;
      }
      """
    And a class "src/App/Event.php":
      """
      <?php
      namespace App;

      class Event {
          public function __construct(public string $name) {}
      }
      """
    And an interface "src/App/EventBus.php":
      """
      <?php
      namespace App;

      interface EventBus {
          public function dispatch(Event $event): void;
      }
      """
    And a spec with example:
      """
      $bus = mock(App\EventBus::class);
      expect($bus->dispatch(anInstanceOf(App\Event::class)))->toBeCalled();
      $bus->dispatch(new App\Event('created'));
      """
    When I run the spec
    Then all examples should pass

  Scenario: cetera argument matcher matches trailing args
    Given an interface "src/App/Logger.php":
      """
      <?php
      namespace App;

      interface Logger {
          public function log(string $level, string $message, string $context = ''): void;
      }
      """
    And a spec with example:
      """
      $logger = mock(App\Logger::class);
      expect($logger->log('info', cetera()))->toBeCalled();
      $logger->log('info', 'hello world', 'extra');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Argument-based stub dispatch with different args
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->toReturn('Alice');
      allow($repo->find(2))->toReturn('Bob');
      expect($repo->find(1))->toBe('Alice');
      expect($repo->find(2))->toBe('Bob');
      """
    When I run the spec
    Then all examples should pass

  Scenario: A stub set through expect() answers only the call it was written with
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      expect($repo->find(1))->toReturn('Alice');
      expect($repo->find(999))->toThrow(new \RuntimeException('Not found'));
      expect($repo->find(1))->toBe('Alice');
      expect($repo->find(2))->toBeNull();
      expect(fn () => $repo->find(999))->toThrow(\RuntimeException::class, 'Not found');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubs answer in the order they were declared, whatever arguments they overlap on
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a class "src/App/Directory.php":
      """
      <?php
      namespace App;

      class Directory {
          public function __construct(private UserRepository $repo) {}

          public function nameOf(int $id): ?string
          {
              return $this->repo->find($id);
          }

          public function nameOfRetried(int $id): ?string
          {
              try {
                  return $this->repo->find($id);
              } catch (\RuntimeException) {
                  return $this->repo->find($id);
              }
          }
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(42))->toReturn('special');
      allow($repo->find(any()))->toReturn('default');
      $directory = new App\Directory($repo);
      expect($directory->nameOf(42))->toBe('special');
      expect($directory->nameOf(99))->toBe('default');
      expect($directory->nameOf(42))->toBe('default');
      """
    When I run the spec
    Then all examples should pass

  Scenario: Stubs of the same call answer one call after the other, the last one answering on
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a class "src/App/Directory.php":
      """
      <?php
      namespace App;

      class Directory {
          public function __construct(private UserRepository $repo) {}

          public function nameOf(int $id): ?string
          {
              return $this->repo->find($id);
          }

          public function nameOfRetried(int $id): ?string
          {
              try {
                  return $this->repo->find($id);
              } catch (\RuntimeException) {
                  return $this->repo->find($id);
              }
          }
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(1))->toThrow(new \RuntimeException('Busy'));
      allow($repo->find(1))->toReturn('Alice');
      $directory = new App\Directory($repo);
      expect($directory->nameOfRetried(1))->toBe('Alice');
      expect($directory->nameOf(1))->toBe('Alice');
      """
    When I run the spec
    Then all examples should pass

  Scenario: A call written without the arguments the method requires is refused, saying what to write
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;

      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find())->toReturn('Alice');
      """
    When I run the spec
    Then the exit code should be 1
    And the output should contain "App\UserRepository::find() takes 1 argument (int $id), so a call written with none can never be made."
    And the output should contain "any() for one whose value does not matter, or cetera() for the rest"

  Scenario: The code under spec calling a double with too few arguments fails as it would on the real object
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders tea', function (Stock $stock) {
              allow($stock->reserve(cetera()))->toReturn(true);

              expect((new Checkout($stock))->order('tea'))->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain "Too few arguments to function App\Stock::reserve(), 1 passed in src/App/Checkout.php on line 8 and exactly 2 expected"

  Scenario: The code under spec calling a double with an argument of the wrong type fails as it would on the real object
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php

      declare(strict_types=1);

      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, '2');
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders tea', function (Stock $stock) {
              allow($stock->reserve('tea', 2))->toReturn(true);

              expect((new Checkout($stock))->order('tea'))->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain "App\Stock::reserve(): Argument #2 ($quantity) must be of type int, string given, called in src/App/Checkout.php on line 11"

  Scenario: A stub given what the method cannot return is refused, naming the method
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;

      interface Counter {
          public function count(): int;
      }
      """
    And a spec with example:
      """
      $counter = mock(App\Counter::class);
      allow($counter->count())->toReturn('three');
      """
    When I run the spec
    Then the exit code should be 1
    And the output should contain:
      """
      App\Counter::count() returns int, so it cannot return string "three".
      """
  Scenario: Fluent call count with once
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;

      interface Counter {
          public function increment(): void;
      }
      """
    And a spec with example:
      """
      $counter = mock(App\Counter::class);
      expect($counter->increment())->toBeCalled()->once();
      $counter->increment();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Fluent call count with exactly N times
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;

      interface Counter {
          public function increment(): void;
      }
      """
    And a spec with example:
      """
      $counter = mock(App\Counter::class);
      expect($counter->increment())->toBeCalled()->exactly(3)->times();
      $counter->increment();
      $counter->increment();
      $counter->increment();
      """
    When I run the spec
    Then all examples should pass

  Scenario: Using argument matchers in stub setup
    Given an interface "src/App/UserRepository.php":
      """
      <?php
      namespace App;
      interface UserRepository {
          public function find(int $id): ?string;
      }
      """
    And a spec with example:
      """
      $repo = mock(App\UserRepository::class);
      allow($repo->find(42))->toReturn('special');
      allow($repo->find(any()))->toReturn('fallback');
      expect($repo->find(42))->toBe('special');
      expect($repo->find(99))->toBe('fallback');
      """
    When I run the spec
    Then all examples should pass

  Scenario: this() values are fresh per example
    Given an interface "src/App/Counter.php":
      """
      <?php
      namespace App;
      interface Counter {
          public function increment(): void;
      }
      """
    And a spec file "spec/App/LetResetSpec.spec.php":
      """
      <?php
      describe('this() reset', function() {
          this('counter', fn(App\Counter $counter) => $counter);

          it('first example calls increment', function() {
              expect($this->counter->increment())->toBeCalled()->once();
              $this->counter->increment();
          });

          it('second example gets fresh mock', function() {
              expect($this->counter->increment())->not()->toBeCalled();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Type-hinted mock injection in closures
    Given an interface "src/App/Mailer.php":
      """
      <?php
      namespace App;

      interface Mailer {
          public function send(string $to, string $body): void;
      }
      """
    And a spec file "spec/App/MailerSpec.spec.php":
      """
      <?php
      use App\Mailer;

      describe('Mailer injection', function () {
          it('injects mocks by type hint', function (Mailer $mailer) {
              expect($mailer)->toBeAnInstanceOf(Mailer::class);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A call the spec never declared fails at once, naming the call and where it came from
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders two of everything', function (Stock $stock) {
              expect((new Checkout($stock))->order('tea'))->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "App\Stock::reserve("
    And the output should contain ", 2) was called but not expected"
    And the output should contain "src/App/Checkout.php:8"
    And the exit code should be 1

  Scenario: A call declared with allow() is expected, and toHaveBeenCalled() verifies it after the act
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders two of everything', function (Stock $stock) {
              allow($stock->reserve('tea', 2))->toReturn(true);

              expect((new Checkout($stock))->order('tea'))->toBeTrue();

              expect($stock->reserve('tea', 2))->toHaveBeenCalled();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: toHaveBeenCalled() checks a call after the act with nothing declared before it, a call made in beforeEach too
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('reserves what is ordered, and nothing else', function (Stock $stock) {
              (new Checkout($stock))->order('tea');

              expect($stock->reserve('tea', 2))->toHaveBeenCalled();
              expect($stock->reserve('coffee', 2))->not()->toHaveBeenCalled();
          });

          context('when an order was placed while setting up', function () {
              beforeEach(function (Stock $stock) {
                  (new Checkout($stock))->order('cake');
              });

              it('reserved it', function (Stock $stock) {
                  expect($stock->reserve('cake', 2))->toHaveBeenCalled();
              });
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A call a failing toHaveBeenCalled() names is reported there, and refused nowhere else
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders coffee', function (Stock $stock) {
              (new Checkout($stock))->order('tea');

              expect($stock->reserve('coffee', 1))->toHaveBeenCalled();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "to have been called"
    And the output should contain "tea"
    And the output should not contain "was called but not expected"
    And the exit code should be 1

  Scenario: toHaveBeenCalled() fails after the act naming the calls the method did receive
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders coffee', function (Stock $stock) {
              allow($stock->reserve(cetera()));

              (new Checkout($stock))->order('tea');

              expect($stock->reserve('coffee', 1))->toHaveBeenCalled();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "to have been called"
    And the output should contain "tea"
    And the exit code should be 1

  Scenario: An injected double stubbed in beforeEach ignores the other calls once told to
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
          public function audit(string $item): void;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              $this->stock->audit($item);

              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          beforeEach(function (Stock $stock) {
              allow($stock->reserve(cetera()))->toReturn(true)->ignoreOthers();
          });

          it('orders two of everything', function (Stock $stock) {
              expect((new Checkout($stock))->order('tea'))->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A dummy answers every call with a default, declared or not
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a spec file "spec/App/Checkout.spec.php":
      """
      <?php
      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders without anyone minding the stock', function () {
              $stock = dummy(Stock::class);

              expect((new Checkout($stock))->order('tea'))->toBeFalse();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A dummy answers a class with a dummy of it and takes no stubs, a mock that ignores the rest stubs only what matters, and a mock is stubbed link by link
    Given an interface "src/App/Book.php":
      """
      <?php
      namespace App;

      interface Page {
          public function title(): string;
          public function next(): Page;
      }

      interface Book {
          public function first(): Page;
          public function pages(): int;
      }
      """
    And a class "src/App/Reader.php":
      """
      <?php
      namespace App;

      class Reader {
          public function __construct(private Book $book) {}
          public function secondTitle(): string
          {
              return $this->book->first()->next()->title();
          }
          public function length(): int
          {
              return $this->book->pages();
          }
      }
      """
    And a spec file "spec/App/Reader.spec.php":
      """
      <?php
      require_once __DIR__ . '/../../src/App/Book.php';

      use App\Book;
      use App\Page;
      use App\Reader;

      describe('Reader', function () {
          it('reads through a dummy book, whose pages are dummies too', function () {
              expect((new Reader(dummy(Book::class)))->secondTitle())->toBe('');
          });

          it('stubs the pages of a dummy book', function () {
              $book = dummy(Book::class);
              allow($book->pages())->toReturn(3);
          });

          it('counts the pages a mock book was stubbed with, reading the rest as defaults', function (Book $book) {
              allow($book->pages())->toReturn(3)->ignoreOthers();

              expect((new Reader($book))->length())->toBe(3);
              expect((new Reader($book))->secondTitle())->toBe('');
          });

          it('stubs a page a dummy book answered', function () {
              $book = dummy(Book::class);
              allow($book->first()->next()->title())->toReturn('Two');
          });

          it('reads the page each link of a mock book was stubbed with', function (Book $book, Page $first, Page $second) {
              allow($book->first())->toReturn($first);
              allow($first->next())->toReturn($second);
              allow($second->title())->toReturn('Two');

              expect((new Reader($book))->secondTitle())->toBe('Two');
          });

          it('reads through a mock book nobody stubbed', function (Book $book) {
              expect((new Reader($book))->secondTitle())->toBe('');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "3 passed"
    And the output should contain "App\Book::pages() was stubbed on a dummy. Use mock() instead of dummy()."
    And the output should contain "App\Page::title() was stubbed on what dummy App\Book::first()->next() answered. Use mock() instead of dummy() and stub App\Book::first()."
    And the output should contain "App\Book::first() was called but not expected, at src/App/Reader.php:8."
    And the output should not contain "PhpSpecWasCalledWith"
    And the exit code should be 1

  Scenario: A method returning a final class can be stubbed
    Given a class "src/App/Coin.php":
      """
      <?php
      namespace App;

      final class Coin {
          public function __construct(private int $pence) {}
          public function pence(): int
          {
              return $this->pence;
          }
      }
      """
    And a class "src/App/Wallet.php":
      """
      <?php
      namespace App;

      class Wallet {
          public function coin(): Coin
          {
              return new Coin(1);
          }
      }
      """
    And a class "src/App/Till.php":
      """
      <?php
      namespace App;

      class Till {
          public function take(Wallet $wallet): int
          {
              return $wallet->coin()->pence();
          }
      }
      """
    And a spec file "spec/App/Till.spec.php":
      """
      <?php
      use App\Coin;
      use App\Till;
      use App\Wallet;

      describe('Till', function () {
          it('takes the coin from the wallet', function (Wallet $wallet) {
              allow($wallet->coin())->toReturn(new Coin(50));

              expect((new Till())->take($wallet))->toBe(50);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A spec run by its path from outside the spec folder arranges its doubles, and so does a helper it requires from beside it
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a file "docs/probes/support/arrange.php":
      """
      <?php
      function probes_stock_with_tea(App\Stock $stock): void
      {
          allow($stock->reserve('tea', 2))->toReturn(true);
      }
      """
    And a spec file "docs/probes/Checkout.spec.php":
      """
      <?php
      require_once __DIR__ . '/support/arrange.php';

      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders tea', function (Stock $stock) {
              probes_stock_with_tea($stock);

              expect((new Checkout($stock))->order('tea'))->toBeTrue();
              expect($stock->reserve('tea', 2))->toHaveBeenCalled();
          });
      });
      """
    When I run phpspec run "docs/probes/Checkout.spec.php"
    Then all examples should pass

  Scenario: A suite's own paths are spec code
    Given an interface "src/App/Stock.php":
      """
      <?php
      namespace App;

      interface Stock {
          public function reserve(string $item, int $quantity): bool;
      }
      """
    And a class "src/App/Checkout.php":
      """
      <?php
      namespace App;

      class Checkout {
          public function __construct(private Stock $stock) {}
          public function order(string $item): bool
          {
              return $this->stock->reserve($item, 2);
          }
      }
      """
    And a phpspec.yaml config:
      """
      suites:
        default:
          paths: [spec]
        probes:
          paths: [docs/probes]
      """
    And a file "docs/probes/support/arrange.php":
      """
      <?php
      function probes_stock_with_tea(App\Stock $stock): void
      {
          allow($stock->reserve('tea', 2))->toReturn(true);
      }
      """
    And a spec file "docs/probes/Checkout.spec.php":
      """
      <?php
      require_once __DIR__ . '/support/arrange.php';

      use App\Checkout;
      use App\Stock;

      describe('Checkout', function () {
          it('orders tea', function (Stock $stock) {
              probes_stock_with_tea($stock);

              expect((new Checkout($stock))->order('tea'))->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass