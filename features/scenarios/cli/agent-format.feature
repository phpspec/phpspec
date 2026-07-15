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
