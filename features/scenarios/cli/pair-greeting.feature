Feature: Pair mode greets from the suite state
  As a developer starting a pairing session
  I want pair mode to open with what matters right now
  So that we begin inside the red-green loop instead of reading a menu

  Scenario: A red suite greets by naming the failing subject
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      describe('App\Calculator', function () {
          it('adds numbers', function () {
              expect(1)->toBe(2);
          });
      });
      """
    When I start a pair session
    Then the output should contain "red"
    And the output should contain "Calculator"
    And the output should not contain "PhpSpec Pair Programming Mode"

  Scenario: A green suite with a pending example greets by naming the gap
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Basket.spec.php":
      """
      <?php
      describe('App\Basket', function () {
          it('holds products', function () {
              expect(1)->toBe(1);
          });
          xit('applies a discount code', function () {
          });
      });
      """
    When I start a pair session
    Then the output should contain "applies a discount code"
    And the output should not contain "PhpSpec Pair Programming Mode"

  Scenario: A green, clean suite greets without naming a failure
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php
      describe('App\Greeter', function () {
          it('greets', function () {
              expect(1)->toBe(1);
          });
      });
      """
    When I start a pair session
    Then the output should contain "Green"
    And the output should not contain "PhpSpec Pair Programming Mode"
