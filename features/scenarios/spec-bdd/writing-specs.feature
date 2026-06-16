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

  Scenario: Sharing state with let
    Given a spec file "spec/App/SharedState.spec.php":
      """
      <?php
      describe('SharedState', function () {
          let('items', fn() => [1, 2, 3]);

          it('has access to let values', function () {
              expect($this->items)->toHaveCount(3);
          });

          it('can use let values in assertions', function () {
              expect($this->items)->toContain(2);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
