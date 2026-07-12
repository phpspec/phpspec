Feature: Pair mode next coaches the next step from real suite state
  As a developer pairing with phpspec
  I want "next" to react to what the suite actually shows
  So that I am never sent in a loop describing a spec that already exists

  Scenario: next coaches to run when a spec is red for a missing class, never re-describing
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('adds numbers', fn() => expect((new Calculator())->add(2, 3))->toBe(5));
      });
      """
    When I run phpspec pair with input "next"
    Then the output should contain "run"
    And the output should contain "Calculator"
    And the output should not contain "Specification for"

  Scenario: next points at the nearest pending gap on a green suite
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Ledger.spec.php":
      """
      <?php
      describe('App\Ledger', function () {
          it('starts empty', fn() => expect(true)->toBe(true));
          xit('reconciles entries', function () {
          });
      });
      """
    When I run phpspec pair with input "next"
    Then the output should contain "reconciles entries"
    And the output should not contain "Specification for"
