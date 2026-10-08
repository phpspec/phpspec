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
      expect($logger->log('hello'))->toBeCalledWith('hello');
      $logger->log('hello');
      """
    When I run the spec
    Then all examples should pass

  Scenario: An argument expectation judges calls made before it was written
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
      expect($logger->log('paid'))->toBeCalledWith('paid');
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
      expect($counter->increment())->toBeCalledTimes(3);
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
      expect($ledger->record(new App\Money(5)))->toBeCalled();
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

  Scenario: A mock injected into a let is there as a property, as the docs show
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
          let('notifier', fn (App\Mailer $mailer) => new App\Notifier($mailer));

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
      expect($logger->log('msg'))->toBeCalledWith(any());
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
      expect($bus->dispatch(new App\Event('test')))->toBeCalledWith(anInstanceOf(App\Event::class));
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
      expect($logger->log('info', 'msg'))->toBeCalledWith('info', cetera());
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

  Scenario: Catch-all stub with specific override
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
      allow($repo->find())->toReturn('default');
      allow($repo->find(42))->toReturn('special');
      expect($repo->find(42))->toBe('special');
      expect($repo->find(99))->toBe('default');
      """
    When I run the spec
    Then all examples should pass

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
      allow($repo->find(any()))->toReturn('fallback');
      allow($repo->find(42))->toReturn('special');
      expect($repo->find(42))->toBe('special');
      expect($repo->find(99))->toBe('fallback');
      """
    When I run the spec
    Then all examples should pass

  Scenario: let() values are fresh per example
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
      describe('let reset', function() {
          let('counter', fn(App\Counter $counter) => $counter);

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
              allow($stock->reserve());

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
              allow($stock->reserve())->toReturn(true)->ignoreOthers();
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