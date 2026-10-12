Feature: An expectation judges its subject on the line it is written
  As a developer
  I want each expectation to judge the value as it stands where I wrote it
  So that changing an object after an expectation never changes what that expectation said

  Background:
    Given a PSR-4 project with "spec", "src", and "features" directories

  Scenario: An object is judged as it stands at each expectation, in a loop too
    Given a spec file "spec/App/Items.spec.php":
      """
      <?php
      describe('Items', function () {
          it('counts before and after adding', function () {
              $items = new ArrayObject();
              expect($items)->toHaveCount(0);
              $items->append('one');
              expect($items)->toHaveCount(1);
          });

          it('is like what it was at each point', function () {
              $point = (object) ['x' => 1];
              expect($point)->toBeLike((object) ['x' => 1]);
              $point->x = 2;
              expect($point)->toBeLike((object) ['x' => 2]);
          });

          it('counts at each turn of a loop', function () {
              $items = new ArrayObject();
              foreach (['a', 'b', 'c'] as $n => $item) {
                  $items->append($item);
                  expect($items)->toHaveCount($n + 1);
              }
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
    And the output should contain "3 examples (3 passed)"

  Scenario: A predicate matcher and a custom matcher judge the object at their line
    Given a class "src/App/Door.php":
      """
      <?php
      namespace App;

      class Door
      {
          private bool $open = false;
          private int $openings = 0;

          public function open(): void
          {
              $this->open = true;
              $this->openings++;
          }

          public function close(): void
          {
              $this->open = false;
          }

          public function isOpen(): bool
          {
              return $this->open;
          }

          public function openings(): int
          {
              return $this->openings;
          }
      }
      """
    And a spec file "spec/App/Door.spec.php":
      """
      <?php
      addMatcher('toHaveBeenOpened', fn (App\Door $door, int $times) => $door->openings() === $times, 'Expected %s to have been opened %s times');

      describe(App\Door::class, function () {
          it('opens, shuts, and opens again', function () {
              $door = new App\Door();
              $door->open();
              expect($door)->toBeOpen();
              expect($door)->toHaveBeenOpened(1);
              $door->close();
              expect($door)->not()->toBeOpen();
              $door->open();
              expect($door)->toHaveBeenOpened(2);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: An expectation in a step judges the object at its line
    Given a feature file "features/stock.feature":
      """
      Feature: Stock
        Scenario: Receiving goods
          When goods arrive on an empty shelf
      """
    And a step file "features/steps/stock.steps.php":
      """
      <?php
      when('goods arrive on an empty shelf', function () {
          $shelf = new ArrayObject();
          expect($shelf)->toHaveCount(0);
          $shelf->append('tea');
          expect($shelf)->toHaveCount(1);
      });
      """
    When I run phpspec run with option "--story"
    Then all steps should pass

  Scenario: A failure reports the first expectation that did not hold, with the object as it stood there
    Given a spec file "spec/App/Point.spec.php":
      """
      <?php
      describe('Point', function () {
          it('moves', function () {
              $point = (object) ['x' => 1];
              expect($point)->toBeLike((object) ['x' => 5]);
              $point->x = 7;
              expect($point)->toBeLike((object) ['x' => 7]);
              $point->x = 9;
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the reported entry should be "failing" at "spec/App/Point.spec.php:5"
    When I run phpspec run with option "--no-ansi"
    Then the output should contain "x: 1"
    And the output should not contain "x: 9"

  Scenario: toBeCalled counts the calls the example makes after it, toHaveBeenCalled the calls made before it
    Given an interface "src/App/Notifier.php":
      """
      <?php
      namespace App;

      interface Notifier
      {
          public function notify(string $who): void;
      }
      """
    And a class "src/App/Greeter.php":
      """
      <?php
      namespace App;

      class Greeter
      {
          public function __construct(private Notifier $notifier) {}

          public function greet(string $who): void
          {
              $this->notifier->notify($who);
          }
      }
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php
      describe(App\Greeter::class, function () {
          it('expects the call to come', function (App\Notifier $notifier) {
              expect($notifier->notify('Ann'))->toBeCalled();
              (new App\Greeter($notifier))->greet('Ann');
          });

          it('finds the call that came', function (App\Notifier $notifier) {
              (new App\Greeter($notifier))->greet('Ann');
              expect($notifier->notify('Ann'))->toHaveBeenCalled();
          });

          it('does not count a call that comes after asking whether it came', function (App\Notifier $notifier) {
              expect($notifier->notify('Ann'))->toHaveBeenCalled();
              (new App\Greeter($notifier))->greet('Ann');
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the exit code should be 1
    And the report should have 1 entry
    And the reported entry should be "failing" at "spec/App/Greeter.spec.php:14"
    And the output should contain "to have been called"

  Scenario: toBeCalled() written after the call was made is refused, and points at toHaveBeenCalled()
    Given an interface "src/App/Notifier.php":
      """
      <?php
      namespace App;

      interface Notifier
      {
          public function notify(string $who): void;
      }
      """
    And a class "src/App/Greeter.php":
      """
      <?php
      namespace App;

      class Greeter
      {
          public function __construct(private Notifier $notifier) {}

          public function greet(string $who): void
          {
              $this->notifier->notify($who);
          }
      }
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php
      describe(App\Greeter::class, function () {
          it('mocks after the act', function (App\Notifier $notifier) {
              (new App\Greeter($notifier))->greet('Ann');
              expect($notifier->notify('Ann'))->toBeCalled();
          });

          it('mocks a call that must not come, after it came', function (App\Notifier $notifier) {
              allow($notifier->notify(any()));
              (new App\Greeter($notifier))->greet('Ann');
              expect($notifier->notify('Ann'))->not()->toBeCalled();
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the exit code should be 1
    And the report should have 2 entries
    And every reported entry should carry a message
    And the output should contain "::notify() was already called: toBeCalled() goes before the act, toHaveBeenCalled() after."
    And the output should contain "not()->toBeCalled() goes before the act, not()->toHaveBeenCalled() after."
