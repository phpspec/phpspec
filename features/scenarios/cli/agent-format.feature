Feature: Agent output format
  As a coding agent
  I want phpspec results as a single machine-readable JSON document
  So that I can decide my next action without parsing prose

  Scenario: A passing run emits a valid JSON document with run_started and summary
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "run_started"
    And the output should contain "summary"
    And the output should contain "passing"

  Scenario: A failing example carries its expected, actual and state
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(3500)->toBe(4000); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "failing"
    And the output should contain "expected"
    And the output should contain "4000"
    And the output should contain "3500"

  Scenario: A failing example carries a line-targeted rerun command
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(3500)->toBe(4000); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "rerun"
    And the output should contain "run spec/App/Calc.spec.php:"

  Scenario: A randomised run keeps the document the only thing on stdout
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent --order=random"
    Then the output should be valid JSON
    And the output should contain "seed"

  Scenario: A profiled run keeps the document the only thing on stdout
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent --profile=3"
    Then the output should be valid JSON

  Scenario: A failed coverage gate rides inside the document instead of printing after it
    Given a PSR-4 project with "spec" and "src" directories
    And a class "src/App/Calc.php":
      """
      <?php

      namespace App;

      class Calc
      {
          public function add(int $a, int $b): int
          {
              return $a + $b;
          }

          public function unused(): int
          {
              return 0;
          }
      }
      """
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php

      use App\Calc;

      describe('Calc', function () {
          it('adds two numbers', function () {
              expect((new Calc())->add(1, 2))->toBe(3);
          });
      });
      """
    When I run phpspec run with coverage options "--format=agent --coverage-min=90"
    Then the output should be valid JSON
    And the output should contain "coverage"
    And the output should contain "required"
    And the exit code should be 1

  Scenario: Two scenarios failing the same step are told apart and re-run one by one
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/counting.feature":
      """
      Feature: Counting
        Scenario: Counting up
          Given a broken step

        Scenario: Counting down
          Given a broken step
      """
    And a step file "features/steps/counting.steps.php":
      """
      <?php

      given('a broken step', function () {
          throw new RuntimeException('this step is broken');
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the failing entries should have distinct ids
    And the output should contain "run features/counting.feature:2 features/counting.feature:5"

  Scenario: A broken scenario is one entry, naming the step that broke it
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/checkout.feature":
      """
      Feature: Checkout
        Scenario: Paying for a basket
          Given a basket with 2 items
          When I pay
          Then I get a receipt
      """
    And a step file "features/steps/checkout.steps.php":
      """
      <?php

      given('a basket with {int} items', function (int $count) {
          throw new RuntimeException('no basket yet');
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the report should have 1 entry
    And the output should contain "Checkout > Paying for a basket"
    And the output should contain "no basket yet"
    And the output should contain "run features/checkout.feature:2"

  Scenario: The same step twice in one scenario is still one entry
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/twice.feature":
      """
      Feature: Twice
        Scenario: The same step twice
          Given a broken step
          Given a broken step
      """
    And a step file "features/steps/twice.steps.php":
      """
      <?php

      given('a broken step', function () {
          throw new RuntimeException('this step is broken');
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the report should have 1 entry

  Scenario: Each row of a scenario outline is told apart by its values
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/adding.feature":
      """
      Feature: Adding
        Scenario Outline: Adding numbers
          Given I add <a> and <b>

          Examples:
            | a | b |
            | 1 | 2 |
            | 3 | 4 |
      """
    And a step file "features/steps/adding.steps.php":
      """
      <?php

      given('I add {int} and {int}', function (int $a, int $b) {
          throw new RuntimeException("cannot add $a and $b");
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the report should have 2 entries
    And the failing entries should have distinct ids
    And the output should contain "Adding numbers (1, 2)"
    And the output should contain "Adding numbers (3, 4)"

  Scenario: An errored example says what went wrong in the same field a failure does
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { throw new RuntimeException('the adder exploded'); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And every reported entry should carry a message
    And the output should contain "the adder exploded"

  Scenario: A whole float is not reported as an integer
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(90.0)->toBe(90); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "Expected 90.0 to be 90"

  Scenario: An object in a failure is named, not numbered
    Given a spec file "spec/App/Bag.spec.php":
      """
      <?php

      enum Status
      {
          case Active;
      }

      class Money
      {
          public function __construct(private string $amount) {}

          public function __toString(): string
          {
              return $this->amount;
          }
      }

      describe('App\Bag', function () {
          it('holds a status', function () { expect(Status::Active)->toBeNull(); });
          it('holds an amount', function () { expect(new Money('12.00 GBP'))->toBeNull(); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "Status::Active"
    And the output should contain "12.00 GBP"
    And the output should not contain "Status#"

  Scenario: A length failure reports the length the subject actually has
    Given a spec file "spec/App/Bag.spec.php":
      """
      <?php
      describe('App\Bag', function () {
          it('holds two', function () { expect([1])->toHaveLength(2); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "Expected [1] to have length 2, has 1"

  Scenario: The summary carries one command that re-runs everything that failed
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds', function () { expect(1)->toBe(2); });
          it('subtracts', function () { expect(3)->toBe(4); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "spec/App/Calc.spec.php:3 spec/App/Calc.spec.php:4"

  Scenario: A run that cannot start still answers with a document
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent --bootstrap=nope.php"
    Then the output should be valid JSON
    And the output should contain "fatal"
    And the output should contain "Bootstrap file not found"
    And the exit code should be 1

  Scenario: A fatal still leaves a document, naming what stopped the run
    Given a spec file "spec/App/Broken.spec.php":
      """
      <?php

      interface Speaks
      {
          public function speak(): string;
      }

      class Mute implements Speaks {}

      describe('App\Broken', function () {
          it('never runs', function () {});
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the standard output should be valid JSON
    And the output should contain "fatal"
    And the output should contain "abstract method"

  Scenario: A missing class surfaces as an offer, and --accept-offers generates it
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php
      describe('App\Basket', function () {
          it('applies a coupon', function () {
              expect(new App\Coupon())->toBeAnInstanceOf(App\Coupon::class);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "create_class"
    And the output should contain "Coupon"
    When I run phpspec run with option "--accept-offers"
    Then the exit code should be 0
    And a class file "src/App/Coupon.php" should be generated

  Scenario: An empty method surfaces as a fake_method offer, filled by --accept-offers --fake
    Given a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          let("calc", fn() => new Calculator());
          it("adds", function () {
              expect($this->calc->add(1, 2))->toBe(3);
          });
      });
      """
    And a class "src/App/Calculator.php":
      """
      <?php
      namespace App;

      class Calculator {
          public function add($a, $b) {}
      }
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "fake_method"
    When I run phpspec run with option "--accept-offers --fake"
    Then the exit code should be 0
    And a class file "src/App/Calculator.php" should be generated
    And it should contain "return 3"
