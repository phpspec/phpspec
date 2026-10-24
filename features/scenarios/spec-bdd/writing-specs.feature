Feature: Writing specs
  As a developer
  I want to describe object behaviour with specs
  So that I can verify my code works as intended

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: Describing a new class generates a spec file
    When I run phpspec describe "App\Calculator"
    Then a spec file "spec/App/Calculator.spec.php" should be generated
    And it should contain a describe block for "App\Calculator"
    And it should contain an "instantiates" example

  Scenario: Describing with an exemplified method
    When I run phpspec describe "App\Calculator" with option "-e add"
    Then the spec file should contain an example for "add"

  Scenario: Running a passing spec
    Given a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('adds two numbers', function () {
              $calc = new Calculator();
              expect($calc->add(2, 3))->toBe(5);
          });
      });
      """
    And a class "src/App/Calculator.php":
      """
      <?php
      namespace App;

      class Calculator {
          public function add(int $a, int $b): int {
              return $a + $b;
          }
      }
      """
    When I run phpspec run
    Then all examples should pass
    And the exit code should be 0

  Scenario: Running a failing spec
    Given a spec file "spec/App/Greeter.spec.php":
      """
      <?php
      describe('Greeter', function () {
          it('greets by name', function () {
              expect("Hello, World!")->toBe("Hello, Alice!");
          });
      });
      """
    When I run phpspec run
    Then 1 example should fail
    And the output should contain "Hello, Alice!"
    And the exit code should be 1

  Scenario: Nested describe and context blocks
    Given a spec file "spec/App/Stack.spec.php":
      """
      <?php
      describe('Stack', function () {
          context('when empty', function () {
              it('has count zero', function () {
                  expect([])->toHaveCount(0);
              });
          });

          context('when items are pushed', function () {
              it('has count equal to pushed items', function () {
                  expect([1, 2, 3])->toHaveCount(3);
              });
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Sharing state with this()
    Given a spec file "spec/App/SharedState.spec.php":
      """
      <?php
      describe('SharedState', function () {
          this('items', fn() => [1, 2, 3]);

          it('has access to this() values', function () {
              expect($this->items)->toHaveCount(3);
          });

          it('can use this() values in assertions', function () {
              expect($this->items)->toContain(2);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A this() read in a beforeEach is built before the example, still once for it and afresh for the next
    Given a spec file "spec/App/Ledger.spec.php":
      """
      <?php
      describe('Ledger', function () {
          this('entries', fn () => new ArrayObject(['opened']));

          beforeEach(function () {
              $this->entries->append('set up');
          });

          it('finds what the hook did to the one value built for it', function () {
              expect($this->entries->getArrayCopy())->toBe(['opened', 'set up']);
          });

          it('starts from a value of its own', function () {
              expect($this->entries->getArrayCopy())->toBe(['opened', 'set up']);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
    And the output should contain "2 examples (2 passed)"

  Scenario: A this() is built when an example first reads it, after the hooks, so a hook can arrange what it consumes
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
          private int $price;
          public function __construct(Menu $menu)
          {
              $this->price = $menu->priceOf('tea');
          }
          public function price(): int
          {
              return $this->price;
          }
      }
      """
    And a spec file "spec/App/Till.spec.php":
      """
      <?php
      describe('Till', function () {
          this('till', fn (App\Menu $menu) => new App\Till($menu));

          beforeEach(function (App\Menu $menu) {
              allow($menu->priceOf('tea'))->toReturn(3);
          });

          it('knows the price', function () {
              expect($this->till->price())->toBe(3);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A this() nobody reads is never built, and a this() may read one declared after it
    Given a spec file "spec/App/Lazy.spec.php":
      """
      <?php
      describe('Lazy', function () {
          this('noisy', function () {
              file_put_contents(getcwd() . '/noisy-was-built.txt', 'built');

              return 'noise';
          });
          this('service', fn () => ['repo' => $this->repo]);
          this('repo', fn () => 'the repo');

          it('reads the later this() through the earlier one', function () {
              expect($this->service['repo'])->toBe('the repo');
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
    And no file "noisy-was-built.txt" should be generated

  Scenario: A parameter is a double, the same for every closure of the example naming it alike and never a this() value, and only a this() puts it on $this
    Given an interface "src/App/Lock.php":
      """
      <?php
      namespace App;

      interface Lock {
          public function acquire(): bool;
      }
      """
    And a class "src/App/Logged.php":
      """
      <?php
      namespace App;

      final class Logged implements Lock {
          public function __construct(public readonly Lock $inner) {}
          public function acquire(): bool { return $this->inner->acquire(); }
      }
      """
    And a class "src/App/Guard.php":
      """
      <?php
      namespace App;

      class Guard {
          public function __construct(public Lock $lock) {}
      }
      """
    And a class "src/App/Bolt.php":
      """
      <?php
      namespace App;

      final class Bolt implements Lock {
          public function acquire(): bool { return true; }
      }
      """
    And a spec file "spec/App/Locks.spec.php":
      """
      <?php
      describe('Locks', function () {
          beforeEach(function (App\Lock $lock, App\Lock $spare) {
              allow($lock->acquire())->toReturn(true);
          });

          this('lock', fn (App\Lock $lock) => new App\Logged($lock));
          this('guard', fn (App\Lock $bolt) => new App\Guard($bolt));
          this('bolt', fn () => new App\Bolt());
          this('vault', fn (App\Lock $key) => new App\Guard($key));

          it('keeps a this() on $this when its parameter has its name', function () {
              expect($this->lock)->toBeAnInstanceOf(App\Logged::class);
          });

          it('hands every parameter of a name the same double', function (App\Lock $lock) {
              expect($this->lock->inner)->toBe($lock);
              expect($this->lock->acquire())->toBe(true);
          });

          it('hands a parameter a double, never the this() of its name', function (App\Guard $guard) {
              expect($guard)->toBeAnInstanceOf(App\Guard::class);
              expect($guard)->not()->toBe($this->guard);
              expect($this->guard->lock)->not()->toBeAnInstanceOf(App\Bolt::class);
          });

          it('puts on $this the doubles a this() takes, and no hook or example parameter', function (App\Lock $other) {
              expect($this->key)->toBe($this->vault->lock);
              expect(isset($this->spare))->toBe(false);
              expect(isset($this->other))->toBe(false);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: An example that makes no expectation is reported as risky, and the run stays green
    Given a spec file "spec/App/Risky.spec.php":
      """
      <?php
      describe('Risky', function () {
          it('calls the code and checks nothing', function () {
              strtoupper('tea');
          });

          it('checks something', function () {
              expect(strtoupper('tea'))->toBe('TEA');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "! calls the code and checks nothing (no expectation)"
    And the output should contain "Risky:"
    And the output should contain "1 risky"
    And the output should contain "1 passed"
    And the exit code should be 0
