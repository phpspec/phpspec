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