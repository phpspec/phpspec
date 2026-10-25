Feature: Error reporting
  As a developer
  I want clear and helpful error messages
  So that I can quickly understand and fix failures

  Scenario: Failed expectation shows expected vs actual
    Given a spec file "spec/App/ErrorMessages.spec.php":
      """
      <?php
      describe('ErrorMessages', function () {
          it('shows expected and actual', function () {
              expect('actual')->toBe('expected');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "expected"
    And the output should contain "actual"

  Scenario: A failure tells a string from a number of the same digits
    Given a spec file "spec/App/Typed.spec.php":
      """
      <?php
      describe('Typed', function () {
          it('compares', function () {
              expect('42')->toBe(42);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "expected: 42"
    And the output should contain "got:"

  Scenario: A count failure says it is about the count
    Given a spec file "spec/App/Counted.spec.php":
      """
      <?php
      describe('Counted', function () {
          it('counts', function () {
              expect([1])->toHaveCount(2);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Expected [1] to have count 2"
    And the output should contain "expected: 2"
    And the output should contain "got: [1]"

  Scenario: A spec file with a syntax error is reported and its neighbours still run
    Given a spec file "spec/App/Healthy.spec.php":
      """
      <?php
      describe('Healthy', function () {
          it('still runs', function () { expect(true)->toBeTrue(); });
      });
      """
    And a spec file "spec/App/Broken.spec.php":
      """
      <?php
      describe('Broken', function () {
          it('never runs', function () {}
      });
      """
    When I run phpspec run in a fresh process with option "--no-interaction"
    Then the output should contain "still runs"
    And the output should contain "Broken > Broken"
    And the output should contain "2 examples (1 passed, 1 errored)"
    And the exit code should be 1

  Scenario: Error shows file and line number
    Given a spec file "spec/App/Location.spec.php":
      """
      <?php
      describe('Location', function () {
          it('points to the failing line', function () {
              expect(1)->toBe(2);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Location.spec.php"

  Scenario: An error raised inside PhpSpec itself points at the spec line
    Given a spec file "spec/App/Service.spec.php":
      """
      <?php
      describe('Service', function () {
          it('uses a notifier', function (App\Nowhere $notifier) {
              expect($notifier)->toBeAnInstanceOf(App\Nowhere::class);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Service.spec.php:3" exactly 1 times
    And the output should not contain "Mock/Double.php"

  Scenario: An error raised inside a double made in the example names the spec line once
    Given a spec file "spec/App/Service.spec.php":
      """
      <?php
      describe('Service', function () {
          it('uses a notifier', function () {
              $notifier = mock(App\Nowhere::class);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Service.spec.php:4" exactly 1 times

  Scenario: An error raised beyond the spec, through the code under spec, points at the spec line that led there
    Given an interface "src/App/Menu.php":
      """
      <?php
      namespace App;

      interface Menu {
          public function priceOf(string $item): int;
      }
      """
    And a class "src/App/Till.php":
      """
      <?php
      namespace App;

      class Till {
          public function __construct(private Menu $menu) {}
          public function order(string $item): int
          {
              return $this->menu->priceOf($item);
          }
      }
      """
    And a spec file "spec/App/Till.spec.php":
      """
      <?php
      describe('Till', function () {
          it('orders', function (App\Menu $menu) {
              $till = new App\Till($menu);
              $till->order('mocha');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Till.spec.php:5" exactly 1 times
    And the output should contain "src/App/Till.php:8"
    And the output should not contain "Till.spec.php:3"

  Scenario: Shows surrounding code context
    Given a spec file "spec/App/Context.spec.php":
      """
      <?php
      describe('Context', function () {
          it('shows surrounding code', function () {
              $value = 42;
              expect($value)->toBe(99);
          });
      });
      """
    When I run phpspec run with option "-v"
    Then the output should contain "$value = 42"

  Scenario: Suggests similar matcher names on typo
    Given a spec file "spec/App/Typo.spec.php":
      """
      <?php
      describe('Typo', function () {
          it('suggests the right matcher', function () {
              expect(true)->toBeTru();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Did you mean"
    And the output should contain "toBeTrue"

  Scenario: Uncaught exception in example is reported as error
    Given a spec file "spec/App/UncaughtError.spec.php":
      """
      <?php
      describe('UncaughtError', function () {
          it('throws unexpectedly', function () {
              throw new \RuntimeException('unexpected error');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "unexpected error"
    And the exit code should be 1

  Scenario: PHP warnings are captured and displayed
    Given a spec file "spec/App/Warnings.spec.php":
      """
      <?php
      describe('Warnings', function () {
          it('triggers a warning', function () {
              @trigger_error('test warning', E_USER_WARNING);
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "warning"

  Scenario: Every note an example raised is counted, and its notices are listed with the rest
    Given a spec file "spec/App/Notes.spec.php":
      """
      <?php
      describe('Notes', function () {
          it('raises some', function () {
              trigger_error('first warning', E_USER_WARNING);
              trigger_error('second warning', E_USER_WARNING);
              trigger_error('the clock is local', E_USER_NOTICE);
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "1 spec, 1 example (1 passed, 2 warnings, 1 notice)"
    And the output should contain "Notices:"

  Scenario: A deprecation a library raises for its own code is left out, one the project's call raises is kept
    Given a library file "acme/Old.php" outside the project, loaded by "bootstrap.php":
      """
      <?php
      namespace Acme;

      function deprecated(): void
      {
          trigger_error('deprecated() is deprecated.', E_USER_DEPRECATED);
      }

      function internally(): void
      {
          deprecated();
      }
      """
    And a spec file "spec/App/Old.spec.php":
      """
      <?php
      describe('Old', function () {
          it('calls the deprecated function', function () {
              Acme\deprecated();
              expect(true)->toBeTrue();
          });
          it('calls what calls it', function () {
              Acme\internally();
              expect(true)->toBeTrue();
          });
      });
      """
    And a feature file "features/old.feature":
      """
      Feature: Old
        Scenario: Calling it
          Given the deprecated function is called
          And what calls it is called
      """
    And a step file "features/steps/steps.php":
      """
      <?php
      given("the deprecated function is called", fn () => Acme\deprecated());
      given("what calls it is called", fn () => Acme\internally());
      """
    When I run phpspec run with option "--all --bootstrap=bootstrap.php"
    Then the output should contain "1 spec, 2 examples (2 passed, 1 deprecation)"
    And the output should contain "2 steps (2 passed, 1 deprecation)"
    And the exit code should be 0

  Scenario: A note raised before pending() or skip() is kept, under its kind
    Given a spec file "spec/App/Later.spec.php":
      """
      <?php
      describe('Later', function () {
          it('waits', function () {
              trigger_error('wait() is deprecated', E_USER_DEPRECATED);
              pending('later');
          });
          it('skips', function () {
              trigger_error('the printer is offline', E_USER_WARNING);
              skip('no printer');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "1 spec, 2 examples (1 pending, 1 skipped, 1 warning, 1 deprecation)"

  Scenario: A failure names a double by its type and the number it was made with
    Given an interface "src/App/Context.php":
      """
      <?php
      namespace App;

      interface Context {
          public function name(): string;
      }
      """
    And an interface "src/App/Handler.php":
      """
      <?php
      namespace App;

      interface Handler {
          public function handle(mixed $context): string;
      }
      """
    And an interface "src/App/Page.php":
      """
      <?php
      namespace App;

      interface Page {
          public function next(): Page;
      }
      """
    And an interface "src/App/Book.php":
      """
      <?php
      namespace App;

      interface Book {
          public function first(): Page;

          public function opened(): \DateTimeInterface;
      }
      """
    And a class "src/App/Dispatcher.php":
      """
      <?php
      namespace App;

      final class Dispatcher {
          public function __construct(private Handler $handler) {}

          public function dispatch(mixed $context): string {
              return $this->handler->handle($context);
          }
      }
      """
    And a spec file "spec/App/Naming.spec.php":
      """
      <?php

      use App\Book;
      use App\Context;
      use App\Dispatcher;
      use App\Handler;

      describe('Naming', function () {
          it('compares two parameters', function (Context $first, Context $second) {
              expect($second)->toBe($first);
          });

          it('verifies a call', function (Handler $handler, Context $first, Context $second) {
              allow($handler->handle(any()))->toReturn('handled');

              (new Dispatcher($handler))->dispatch($second);

              expect($handler->handle($first))->toHaveBeenCalled();
          });

          it('compares a double made by mock()', function (Context $first) {
              expect(mock(Context::class))->toBe($first);
          });

          it('compares what a mock answered', function (Book $book, Context $first) {
              allow($book->first());

              expect($first)->toBe($book->first());
          });

          it('compares what a mock answered on a base PHP requires', function (Book $book, Context $first) {
              allow($book->opened());

              expect($first)->toBe($book->opened());
          });

          it('compares what a dummy answered down a chain', function (Context $first) {
              $book = dummy(Book::class);

              expect($first)->toBe($book->first()->next());
          });

          it('refuses a call nothing declared', function (Handler $handler, Context $first) {
              (new Dispatcher($handler))->dispatch($first);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Expected App\Context double #2 ($second) to be App\Context double #1 ($first)"
    And the output should contain "expected: App\Context double #1 ($first)"
    And the output should contain "got: App\Context double #2 ($second)"
    And the output should contain "expected: at least 1 call to App\Handler::handle(App\Context double #2 ($first))"
    And the output should contain "got: App\Handler::handle(App\Context double #3 ($second))"
    And the output should contain "Expected App\Context double #2 to be App\Context double #1 ($first)"
    And the output should contain "Expected App\Context double #2 ($first) to be App\Page double #3 (from App\Book double #1->first())"
    And the output should contain "Expected App\Context double #2 ($first) to be DateTimeInterface double #3 (from App\Book double #1->opened())"
    And the output should contain "Expected App\Context double #1 ($first) to be App\Page double #3 (from App\Book double #2->first()->next())"
    And the output should contain "App\Handler::handle(App\Context double #2 ($first)) was called but not expected"
    And the output should not contain "PhpspecDouble"
    And the output should not contain "______phpspec"

  Scenario: Every report, and every worker, names a double alike
    Given an interface "src/App/Context.php":
      """
      <?php
      namespace App;

      interface Context {
          public function name(): string;
      }
      """
    And an interface "src/App/Page.php":
      """
      <?php
      namespace App;

      interface Page {}
      """
    And an interface "src/App/Book.php":
      """
      <?php
      namespace App;

      interface Book {
          public function first(): Page;
      }
      """
    And a spec file "spec/App/Naming.spec.php":
      """
      <?php

      use App\Context;

      describe('Naming', function () {
          it('compares two parameters', function (Context $first, Context $second) {
              expect($second)->toBe($first);
          });
      });
      """
    And a spec file "spec/App/Answering.spec.php":
      """
      <?php

      use App\Book;
      use App\Context;

      describe('Answering', function () {
          it('compares what a mock answered', function (Book $book, Context $first) {
              allow($book->first());

              expect($first)->toBe($book->first());
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should contain "Context double #2 ($second) to be "
    And the output should contain "Context double #1 ($first)"
    And the output should contain "Page double #3 (from "
    And the output should not contain "PhpspecDouble"
    When I run phpspec run with option "--format=junit"
    Then the output should contain "Context double #2 ($second) to be "
    And the output should contain "Page double #3 (from "
    When I run phpspec run with option "--format=tap"
    Then the output should contain "Context double #2 ($second) to be "
    And the output should contain "Page double #3 (from "
    When I run phpspec run with option "--format=html"
    Then the output should contain "Context double #2 ($second) to be "
    And the output should contain "Context double #1 ($first)"
    And the output should contain "Page double #3 (from "
    And the output should not contain "PhpspecDouble"
    When I run phpspec run with option "--parallel=2"
    Then the output should contain "Expected App\Context double #2 ($second) to be App\Context double #1 ($first)"
    And the output should contain "Expected App\Context double #2 ($first) to be App\Page double #3 (from App\Book double #1->first())"
