Feature: Code generation
  As a developer
  I want PhpSpec to generate code from my specs
  So that I can follow a spec-first workflow

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: Generate a method stub when spec calls missing method
    Given a class "src/App/Calculator.php":
      """
      <?php
      namespace App;

      class Calculator {}
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('adds numbers', function () {
              $calc = new Calculator();
              expect($calc->add(2, 3))->toBe(5);
          });
      });
      """
    When I run phpspec run and answer "y" to generation prompts
    Then the class "src/App/Calculator.php" should contain "function add"
    And the output should contain "Method add() generated in src/App/Calculator.php"

  Scenario: A method called statically is generated static, with its arguments
    Given a class "src/App/TaskList.php":
      """
      <?php
      namespace App;

      class TaskList {}
      """
    And a spec file "spec/App/TaskList.spec.php":
      """
      <?php
      use App\TaskList;

      describe(TaskList::class, function () {
          it('is built from titles', function () {
              expect(TaskList::of('write the spec', 'make it pass'))->toBeAnInstanceOf(TaskList::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then the class "src/App/TaskList.php" should contain "public static function of($argument1, $argument2)"
    When I run phpspec run with option "--format=agent"
    Then the output should not contain "cannot be called statically"

  Scenario: --fake writes no return it cannot express and fakes no method whose result was called on
    Given a class "src/App/Money.php":
      """
      <?php
      namespace App;

      class Money {
          public function __construct(public readonly int $pence) {}
          public function plus(Money $other)
          {
          }
      }
      """
    And a class "src/App/Oops.php":
      """
      <?php
      namespace App;

      class Oops {}
      """
    And a spec file "spec/App/Money.spec.php":
      """
      <?php
      describe('App\Money', function () {
          it('adds', function () {
              expect((new App\Money(1))->plus(new App\Money(2)))->toBeLike(new App\Money(3));
          });

          it('names what is missing', function () {
              expect(App\Oops::for('unicorn latte')->getMessage())->toBe('unicorn latte is not on the menu');
          });
      });
      """
    When I run phpspec run with option "--accept-offers --fake"
    Then the file "src/App/Money.php" should not contain "__set_state"
    And the class "src/App/Oops.php" should contain "public static function for($argument1)"
    And the file "src/App/Oops.php" should not contain "return 'unicorn latte is not on the menu'"

  Scenario: --fake writes the value an arrow-function example expects, and nothing of the it() around it
    Given a class "src/App/Voucher.php":
      """
      <?php
      namespace App;

      class Voucher {}
      """
    And a spec file "spec/App/Voucher.spec.php":
      """
      <?php
      describe('App\Voucher', function () {
          it('has a short code', fn () => expect((new App\Voucher())->shortCode())->toBe('B5'));
      });
      """
    When I run phpspec run with option "--accept-offers --fake"
    Then the class "src/App/Voucher.php" should contain "return 'B5';"
    When I run phpspec run in a fresh process
    Then the output should contain "1 example (1 passed)"

  Scenario: --fake writes each value so it comes back as the spec wrote it
    Given a class "src/App/Quote.php":
      """
      <?php
      namespace App;

      class Quote {
          public function quote() {}
          public function price() {}
          public function third() {}
          public function smallest() {}
          public function zero() {}
          public function nul() {}
      }
      """
    And a spec file "spec/App/Quote.spec.php":
      """
      <?php
      describe('App\Quote', function () {
          it('quotes', function () { $quote = new App\Quote(); expect($quote->quote())->toBe('He said "hello"'); });
          it('prices', function () { $quote = new App\Quote(); expect($quote->price())->toBe(90.0); });
          it('thirds', function () { $quote = new App\Quote(); expect($quote->third())->toBe(0.1 + 0.2); });
          it('floors', function () { $quote = new App\Quote(); expect($quote->smallest())->toBe(PHP_INT_MIN); });
          it('signs zero', function () { $quote = new App\Quote(); expect($quote->zero())->toBe(-0.0); });
          it('keeps a NUL', function () { $quote = new App\Quote(); expect($quote->nul())->toBe("a\0b"); });
      });
      """
    When I run phpspec run with option "--accept-offers --fake"
    Then the file "src/App/Quote.php" should contain:
      """
      return 'He said "hello"';
      """
    When I run phpspec run in a fresh process
    Then all examples should pass

  Scenario: --fake fills a method called on a subject built inline
    Given a class "src/App/Greeter.php":
      """
      <?php
      namespace App;

      class Greeter {
          public function __construct(private string $name = 'you') {}
          public function greet() {}
          public function shout() {}
      }
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php
      use App\Greeter;

      describe('App\Greeter', function () {
          it('greets', fn () => expect((new Greeter('Ann'))->greet())->toBe('Hello'));
          it('shouts', fn () => expect((new App\Greeter())->shout())->toBe('HELLO'));
      });
      """
    When I run phpspec run with option "--accept-offers --fake"
    Then the file "src/App/Greeter.php" should contain "return 'Hello';"
    And the file "src/App/Greeter.php" should contain "return 'HELLO';"

  Scenario: A run nobody can answer writes nothing into the source tree
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec run
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "accept-offers"
    And the exit code should be 1

  Scenario: --no-interaction writes nothing into the source tree
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--no-interaction"
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "Nothing was written"
    And the output should contain "Looks like you are trying to spec App\Basket"

  # An empty answer at a terminal is somebody pressing Enter, which takes the
  # default. Nothing to read at all is nobody there, and must not be read as one.
  Scenario: A question nobody is there to answer writes nothing either
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec run with nobody to answer it
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "Nothing was written"

  # An input that stays open and never says anything, as docker compose exec -T
  # or a CI step leaves one: a question that waited on it would wait for ever.
  Scenario: A run whose input is no terminal asks nobody, and finishes
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec run with an input that never answers
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "Nothing was written: there is nobody to answer."
    And the exit code should be 1

  Scenario: --accept-offers writes without a terminal
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec run with option "--accept-offers" and an input that never answers
    Then a file "src/App/Basket.php" should be generated

  Scenario: Answers piped without SHELL_INTERACTIVE=1 are not read
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I pipe "y" into phpspec run
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "Nothing was written: there is nobody to answer."

  Scenario: --no-interaction wins over SHELL_INTERACTIVE=1
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I pipe "y" into phpspec run with option "--no-interaction" and SHELL_INTERACTIVE=1
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "Nothing was written"

  Scenario: Generate an interface from mock error
    Given a spec file "spec/App/Service.spec.php":
      """
      <?php
      describe('Service', function () {
          it('uses a logger', function () {
              $logger = mock(App\Logger::class);
              expect($logger)->toBeAnInstanceOf(App\Logger::class);
          });
      });
      """
    When I run phpspec run and answer "y" to generation prompts
    Then the output should contain "Do you want me to create interface App\Logger in src/App/Logger.php?"
    And a file "src/App/Logger.php" should be generated
    And it should contain "interface Logger"

  Scenario: A method generated on a mocked interface takes the arguments the spec called it with
    Given a spec file "spec/App/Pricing.spec.php":
      """
      <?php
      describe('App\Pricing', function () {
          it('prices tea', function () {
              $catalogue = mock(App\Catalogue::class);
              allow($catalogue->priceOf('tea'))->toReturn(2);
              expect($catalogue->priceOf('tea'))->toBe(2);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    And I run phpspec run with option "--accept-offers"
    Then the file "src/App/Catalogue.php" should contain "public function priceOf($argument1);"

  Scenario: Nothing is offered on a type outside the source path
    Given a file "third_party/Shelf/Store.php":
      """
      <?php
      namespace Shelf;

      interface Store
      {
          public function flush(): void;
      }
      """
    And a file "third_party/Shelf/Ledger.php":
      """
      <?php
      namespace Shelf;

      class Ledger
      {
          public function total(): int
          {
              return 0;
          }
      }
      """
    And a spec file "spec/App/Accounts.spec.php":
      """
      <?php
      require_once __DIR__ . '/../../third_party/Shelf/Store.php';
      require_once __DIR__ . '/../../third_party/Shelf/Ledger.php';

      describe('App\Accounts', function () {
          it('flushes in a transaction', function (Shelf\Store $store) {
              allow($store->wrapInTransaction(any()))->toReturn(1);
          });

          it('sums', function () {
              expect((new Shelf\Ledger())->subtotal())->toBe(0);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then the output should contain "Call to undefined method Shelf\Store::wrapInTransaction()"
    And the output should contain "Call to undefined method Shelf\Ledger::subtotal()"
    And the output should not contain "to interface Shelf\Store"
    And the output should not contain "Shelf\Ledger::subtotal() for you"
    And the file "third_party/Shelf/Store.php" should not contain "wrapInTransaction"
    And the file "third_party/Shelf/Ledger.php" should not contain "subtotal"

  Scenario: Generate an interface from a type-hinted injection
    Given a spec file "spec/App/Service.spec.php":
      """
      <?php
      describe('Service', function () {
          it('uses a notifier', function (App\Notifier $notifier) {
              expect($notifier)->toBeAnInstanceOf(App\Notifier::class);
          });
      });
      """
    When I run phpspec run and answer "y" to generation prompts
    Then a file "src/App/Notifier.php" should be generated
    And it should contain "interface Notifier"

  Scenario: A class a spec needs is offered spec first, the way a class a step needs is
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php
      describe('App\Basket', function () {
          it('applies a coupon', function () {
              expect(new App\Coupon())->toBeAnInstanceOf(App\Coupon::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then the output should contain "Looks like App\Basket needs App\Coupon,"
    And the output should contain "Spec for App\Coupon created in spec/App/Coupon.spec.php"
    And a spec file "spec/App/Coupon.spec.php" should be generated
    And a class file "src/App/Coupon.php" should be generated

  Scenario: A generated class named like an exception extends Exception, so it can be thrown
    Given a spec file "spec/App/PaymentFailedException.spec.php":
      """
      <?php
      describe('App\PaymentFailedException', function () {
          it('can be thrown', function () {
              $declined = new App\PaymentFailedException('declined');
              expect(fn () => throw $declined)->toThrow(App\PaymentFailedException::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then the class "src/App/PaymentFailedException.php" should contain "use Exception;"
    And the class "src/App/PaymentFailedException.php" should contain "class PaymentFailedException extends Exception"
    When I run phpspec run in a fresh process
    Then the output should contain "1 example (1 passed)"
  Scenario: A generated class lands where composer.json says its namespace lives
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Tasker\\": "src/"}}}
      """
    And a spec file "spec/Tasker/TaskList.spec.php":
      """
      <?php
      describe('Tasker\TaskList', function () {
          it('starts empty', function () {
              expect(new Tasker\TaskList())->toBeAnInstanceOf(Tasker\TaskList::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then a class file "src/TaskList.php" should be generated
    And no file "src/Tasker/TaskList.php" should be generated

  Scenario: A class generated where composer.json autoloads no classes is mapped there for Composer, which is told to dump
    Given no phpspec.json config
    And a file "composer.json":
      """
      {
          "name": "acme/kata",
          "require": {}
      }
      """
    And a spec file "spec/StringCalculator.spec.php":
      """
      <?php
      describe('StringCalculator', function () {
          it('exists', function () {
              expect(new StringCalculator())->toBeAnInstanceOf(StringCalculator::class);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--accept-offers"
    Then a class file "src/StringCalculator.php" should be generated
    And the file "composer.json" should contain:
      """
      {
          "name": "acme/kata",
          "require": {},
          "autoload": {
              "psr-4": {
                  "": "src/"
              }
          }
      }
      """
    And the output should contain:
      """
      Composer autoload was updated to include "": "src/". Run composer dumpautoload next.
      """

  Scenario: A class generated at the project root leaves composer.json as it is
    Given a file "composer.json":
      """
      {"name": "acme/kata"}
      """
    And a phpspec.yaml config:
      """
      src_path: .
      """
    And a spec file "spec/BowlingGame.spec.php":
      """
      <?php
      describe('BowlingGame', function () {
          it('exists', function () {
              expect(new BowlingGame())->toBeAnInstanceOf(BowlingGame::class);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--accept-offers"
    Then a class file "BowlingGame.php" should be generated
    And the file "composer.json" should not contain "autoload"
    And the output should not contain "Composer autoload"

  Scenario: The agent summary names composer.json among the files written when generating a class maps it for Composer
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"name": "acme/kata"}
      """
    And a spec file "spec/Frame.spec.php":
      """
      <?php
      describe('Frame', function () {
          it('exists', function () {
              expect(new Frame())->toBeAnInstanceOf(Frame::class);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--accept-offers --format=agent"
    Then the output should contain:
      """
      "files":["src/Frame.php","composer.json"]
      """

  Scenario: A source path composer.json maps leaves each class where composer maps its namespace
    Given a file "composer.json":
      """
      {"autoload": {"psr-4": {"Roastery\\": "src/"}}}
      """
    And a phpspec.yaml config:
      """
      src_path: src
      default_namespace: Roastery
      """
    And a spec file "spec/Roastery/Repository/InMemoryRoastRepository.spec.php":
      """
      <?php
      describe('Roastery\Repository\InMemoryRoastRepository', function () {
          it('starts empty', function () {
              expect(new Roastery\Repository\InMemoryRoastRepository())->toBeAnInstanceOf(Roastery\Repository\InMemoryRoastRepository::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then a class file "src/Repository/InMemoryRoastRepository.php" should be generated
    And no file "src/Roastery/Repository/InMemoryRoastRepository.php" should be generated

  Scenario: A class lands in the directory composer maps its own namespace to, not the first mapping's
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Brew\\": "src/Brew/", "Another\\": "src/Another/"}}}
      """
    And a spec file "spec/Another/Thing.spec.php":
      """
      <?php
      describe('Another\Thing', function () {
          it('exists', function () {
              expect(new Another\Thing())->toBeAnInstanceOf(Another\Thing::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then a class file "src/Another/Thing.php" should be generated
    And no file "src/Brew/Another/Thing.php" should be generated

  Scenario: A class under none of the mapped namespaces keeps its whole name under the source path, mapped there for Composer, and the next run finds it
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Brew\\": "src/Brew/"}}}
      """
    And a spec file "spec/Acme/Thing.spec.php":
      """
      <?php
      describe('Acme\Thing', function () {
          it('exists', function () {
              expect(new Acme\Thing())->toBeAnInstanceOf(Acme\Thing::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then a class file "src/Acme/Thing.php" should be generated
    And no file "src/Brew/Acme/Thing.php" should be generated
    And the file "composer.json" should contain:
      """
      "Brew\\": "src/Brew/",
      """
    And the file "composer.json" should contain:
      """
      "": "src/"
      """
    And the output should contain:
      """
      Composer autoload was updated to include "": "src/". Run composer dumpautoload next.
      """
    When I run phpspec run
    Then the output should contain "1 example (1 passed)"

  Scenario: A class filed under its whole name below a composer mapping is not loaded, as Composer would not load it
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Tasker\\": "src/"}}}
      """
    And a class "src/Tasker/Misfiled.php":
      """
      <?php
      namespace Tasker;

      class Misfiled {}
      """
    And a spec file "spec/Tasker/Misfiled.spec.php":
      """
      <?php
      describe('Tasker\Misfiled', function () {
          it('exists', function () {
              expect(new Tasker\Misfiled())->toBeAnInstanceOf(Tasker\Misfiled::class);
          });
      });
      """
    When I run phpspec run in a fresh process
    Then the exit code should be 1
    And the output should contain "Tasker\Misfiled,"
    And the output should contain "a class that doesn't exist yet"

  Scenario: A method lands in the file its class was loaded from, wherever in the source that is
    Given a file "composer.json":
      """
      {"autoload": {"psr-4": {"Tasker\\": "src/"}}}
      """
    And a phpspec.yaml config:
      """
      bootstrap: bootstrap.php
      """
    And a file "bootstrap.php":
      """
      <?php
      require __DIR__ . '/src/legacy/Ledger.php';
      """
    And a class "src/legacy/Ledger.php":
      """
      <?php
      namespace Tasker;

      class Ledger {}
      """
    And a spec file "spec/Tasker/Ledger.spec.php":
      """
      <?php
      describe('Tasker\Ledger', function () {
          it('records an entry', function () {
              (new Tasker\Ledger())->record('write the spec');
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run in a fresh process with option "--accept-offers"
    Then the class "src/legacy/Ledger.php" should contain "function record"
    And no file "src/Ledger.php" should be generated

  Scenario: A class generated under a composer mapping is found by the next run
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Tasker\\": "src/Tasker/"}}}
      """
    And a spec file "spec/Tasker/Board.spec.php":
      """
      <?php
      describe('Tasker\Board', function () {
          it('starts empty', function () {
              expect(new Tasker\Board())->toBeAnInstanceOf(Tasker\Board::class);
          });
      });
      """
    When I run phpspec run with option "--accept-offers"
    Then a class file "src/Tasker/Board.php" should be generated
    When I run phpspec run with option "--format=agent"
    Then the output should not contain "create_class"
    And the exit code should be 0

  Scenario: Describe command generates a spec
    When I run phpspec describe "App\Formatter"
    Then a spec file "spec/App/Formatter.spec.php" should be generated
    And it should contain a describe block for "App\Formatter"

  Scenario: Describe with run runs the spec it created, not the whole suite
    Given a spec file "spec/App/Other.spec.php":
      """
      <?php
      describe('App\Other', function () {
          it('lives elsewhere', fn () => expect(true)->toBeTrue());
      });
      """
    When I run phpspec describe "App\Formatter" with option "-r -n"
    Then the output should contain "1 spec"
    And the output should not contain "2 specs"
    And the output should not contain "lives elsewhere"

  Scenario: Describe with run under the agent format runs the new spec and reports it
    When I run phpspec describe "App\Formatter" with option "-r --format=agent -n"
    Then the output should contain "run_started"
    And the output should contain "summary"
    And the exit code should be 1

  Scenario: Describe command with exemplified method
    When I run phpspec describe "App\Formatter" with option "-e render"
    Then a spec file "spec/App/Formatter.spec.php" should be generated
    And the spec file should contain an example for "render"

  Scenario: Describe refuses a name that is not a class
    When I run phpspec describe "1"
    Then the exit code should be 1
    And the output should contain "not a valid class name"
    And no file "spec/1.spec.php" should be generated

  Scenario: Describe refuses a name that is not an identifier
    When I run phpspec describe "Foo-Bar"
    Then the exit code should be 1
    And the output should contain "not a valid class name"
    And no file "spec/Foo-Bar.spec.php" should be generated

  Scenario: Describe asks for the class it should describe
    When I run phpspec describe ""
    Then the exit code should be 1
    And the output should contain "Describe what?"
    And no file "spec/.spec.php" should be generated

  Scenario: Describing a class under none of the mapped namespaces asks which to put it under
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Brew\\": "src/Brew/", "Another\\": "src/Another/"}}}
      """
    When I run phpspec describe "Acme/Thing" answering "2"
    Then the output should contain "[1] Brew\Acme\Thing"
    And the output should contain "[2] Another\Acme\Thing"
    And the output should contain "[0] Acme\Thing, as written"
    And a spec file "spec/Another/Acme/Thing.spec.php" should be generated
    And it should contain a describe block for "Another\Acme\Thing"
    And no file "spec/Acme/Thing.spec.php" should be generated

  Scenario: Keeping a name as written describes it under its own path, and maps it there for Composer
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Brew\\": "src/Brew/"}}}
      """
    When I run phpspec describe "Acme/Thing" answering "0"
    Then a spec file "spec/Acme/Thing.spec.php" should be generated
    And it should contain a describe block for "Acme\Thing"
    And the file "composer.json" should contain:
      """
      "": "src/"
      """
    And the output should contain:
      """
      Composer autoload was updated to include "": "src/". Run composer dumpautoload next.
      """

  Scenario: A name is not offered as written when composer.json maps the source path to another namespace
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Other\\": "src/"}}}
      """
    When I run phpspec describe "Brew/Acme/Sombrero" answering "1"
    Then the output should contain "[1] Other\Brew\Acme\Sombrero"
    And the output should not contain "as written"
    And a spec file "spec/Other/Brew/Acme/Sombrero.spec.php" should be generated
    And it should contain a describe block for "Other\Brew\Acme\Sombrero"

  Scenario: A name composer.json maps to its fallback directory is described as written, without asking
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Brew\\": "src/Brew/", "": "src/"}}}
      """
    When I run phpspec describe "Acme/Thing" with option "-n"
    Then the exit code should be 0
    And a spec file "spec/Acme/Thing.spec.php" should be generated
    And the output should not contain "under none of the mapped namespaces"

  Scenario: A class under no namespace composer.json maps is not written where composer maps the source path to another namespace
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Other\\": "src/"}}}
      """
    And a spec file "spec/Brew/Acme/Sombrero.spec.php":
      """
      <?php
      describe('Brew\Acme\Sombrero', function () {
          it('exists', function () {
              expect(new Brew\Acme\Sombrero())->toBeAnInstanceOf(Brew\Acme\Sombrero::class);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--accept-offers"
    Then the output should contain:
      """
      Brew\Acme\Sombrero cannot be written under src/: Composer maps src/ to Other\.
      """
    And the exit code should be 1
    And no file "src/Brew/Acme/Sombrero.php" should be generated
    And the file "composer.json" should not contain:
      """
      "":
      """

  Scenario: A default namespace takes in every name under no mapping, and leaves a mapped one alone
    Given a phpspec.yaml config:
      """
      default_namespace: Brew\Acme
      autoload:
        Brew\Acme\: src/Brew/Acme
        Another\Acme\: src/Another
      """
    When I run phpspec describe "Thing"
    Then a spec file "spec/Brew/Acme/Thing.spec.php" should be generated
    And it should contain a describe block for "Brew\Acme\Thing"
    When I run phpspec describe "Another/Acme/Thing"
    Then a spec file "spec/Another/Acme/Thing.spec.php" should be generated
    And it should contain a describe block for "Another\Acme\Thing"

  Scenario: Describing a class under none of the mapped namespaces with nobody to answer writes nothing and says why
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Brew\\": "src/Brew/", "Another\\": "src/Another/"}}}
      """
    When I run phpspec describe "Acme/Thing" with option "-n"
    Then the exit code should be 1
    And the output should contain "Acme\Thing is under none of the mapped namespaces"
    And the output should contain "default_namespace"
    And no file "spec/Acme/Thing.spec.php" should be generated
    And no file "spec/Brew/Acme/Thing.spec.php" should be generated

  Scenario: The agent receipt for a class under none of the mapped namespaces carries the error and a remedy
    Given no phpspec.json config
    And a file "composer.json":
      """
      {"autoload": {"psr-4": {"Brew\\": "src/Brew/"}}}
      """
    When I run phpspec describe "Acme/Thing" with option "--format=agent -n"
    Then the exit code should be 1
    And the output should contain "under none of the mapped namespaces"
    And the output should contain "remedy"
    And no file "spec/Acme/Thing.spec.php" should be generated

  Scenario: Exemplify refuses a method that is not an identifier
    When I run phpspec exemplify "App\Printer" "2print"
    Then the exit code should be 1
    And the output should contain "not a valid method name"
    And no file "spec/App/Printer.spec.php" should be generated

  Scenario: Exemplify command adds an example to an existing spec
    When I run phpspec describe "App\Converter"
    And I run phpspec exemplify "App\Converter" "convert"
    Then the output should contain "Example for App\Converter::convert added."
    And the spec file should contain an example for "convert"

  Scenario: Describing a spec that already exists says so, and exemplifying the same method twice says so too
    When I run phpspec describe "App\Converter"
    And I run phpspec describe "App\Converter"
    Then the output should contain "Specification for App\Converter already exists in spec/App/Converter.spec.php"
    When I run phpspec exemplify "App\Converter" "convert"
    And I run phpspec exemplify "App\Converter" "convert"
    Then the output should contain "Example for App\Converter::convert already exists."
    And the output should not contain "added."
  Scenario: Exemplify command creates a spec if it does not exist
    When I run phpspec exemplify "App\Printer" "print"
    Then a spec file "spec/App/Printer.spec.php" should be generated
    And the spec file should contain an example for "print"
