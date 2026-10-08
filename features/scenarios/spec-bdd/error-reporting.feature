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
    Then the output should contain "to be: 42"

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
    And the output should contain "2 examples (1 passes, 1 errors)"
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
