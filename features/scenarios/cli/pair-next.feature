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
    When I run phpspec pair with input "/next"
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
    When I run phpspec pair with input "/next"
    Then the output should contain "reconciles entries"
    And the output should not contain "Specification for"

  Scenario: next favours the feature and points at the steps to write when they are undefined
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/adding.feature":
      """
      Feature: Adding
        Scenario: Add two numbers
          Given a calculator
          When I add 2 and 3
          Then the total is 5
      """
    When I run phpspec pair with input "/next"
    Then the output should contain "adding.feature"
    And the output should contain "steps"

  Scenario: next offers a baby step over the last-touched files when the features are green
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a class "src/App/Greeter.php":
      """
      <?php
      namespace App;

      class Greeter
      {
          public function greet(): string
          {
              return 'hi';
          }
      }
      """
    And a feature file "features/greeting.feature":
      """
      Feature: Greeting
        Scenario: Greet
          Given a greeter
          Then it greets
      """
    And a step file "features/steps/greeting.steps.php":
      """
      <?php
      given('a greeter', function () {
          $this->greeter = new \App\Greeter();
      });

      then('it greets', function () {
          expect($this->greeter->greet())->toBe('hi');
      });
      """
    When I run phpspec pair with input "/next"
    Then the output should contain "Features green"
    And the output should contain "new feature"
    And the output should contain "Greeter.php"
