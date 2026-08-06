Feature: Guard
  As a developer driving out code with tests
  I want PhpSpec to refuse logic I never specified
  So that the cycle holds even when I am moving fast

  Scenario: Turning guard on says so in the config and remembers where the session started
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec guard
    Then the output should contain "Guard is on"
    And the output should contain "Baseline recorded"
    And a file ".phpspec/guard/baseline.json" should be generated

  Scenario: Turning guard on leaves the rest of the config as it was written
    Given a PSR-4 project with "spec" and "src" directories
    And a file "phpspec.yml":
      """
      # the paths this project uses
      spec_path: spec
      src_path: src
      """
    When I run phpspec guard
    Then the file "phpspec.yml" should contain "# the paths this project uses"
    And the file "phpspec.yml" should contain "spec_path: spec"
    And the file "phpspec.yml" should contain "status: active"

  Scenario: New logic no example reaches fails the run and names the member
    Given a PSR-4 project with "spec" and "src" directories
    And a class "src/App/Basket.php":
      """
      <?php

      namespace App;

      class Basket
      {
          public function total(): int
          {
              return 0;
          }
      }
      """
    And a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec guard
    And a class "src/App/Basket.php":
      """
      <?php

      namespace App;

      class Basket
      {
          public function total(): int
          {
              return 0;
          }

          public function applyCoupon(int $value): int
          {
              if ($value > 100) {
                  return 100;
              }

              return $value;
          }
      }
      """
    And I run phpspec run with coverage options ""
    Then the output should contain "Guard Violation"
    And the output should contain "App\Basket::applyCoupon"
    And the output should contain "Write an example for"
    And the exit code should be 1

  Scenario: The same change passes once an example reaches it
    Given a PSR-4 project with "spec" and "src" directories
    And a class "src/App/Basket.php":
      """
      <?php

      namespace App;

      class Basket
      {
          public function total(): int
          {
              return 0;
          }
      }
      """
    And a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec guard
    And a class "src/App/Basket.php":
      """
      <?php

      namespace App;

      class Basket
      {
          public function total(): int
          {
              return 0;
          }

          public function applyCoupon(int $value): int
          {
              return $value;
          }
      }
      """
    And a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });

          it('applies a coupon', function () {
              expect((new Basket())->applyCoupon(10))->toBe(10);
          });
      });
      """
    And I run phpspec run with coverage options ""
    Then the output should not contain "Guard Violation"
    And the exit code should be 0

  Scenario: Turning guard on again moves the baseline to where the session is now
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec guard
    And I run phpspec guard
    Then the output should contain "Guard is on"
    And the file ".phpspec/guard/baseline.json" should contain "recorded"
