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

  Scenario: A parallel run judges once, on what every worker together covered
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
    And I run phpspec run with coverage options "--parallel=2"
    Then the output should not contain "Guard Violation"
    And the exit code should be 0

  Scenario: Snapshot detection judges the session, not the whole project
    Given a PSR-4 project with "spec" and "src" directories
    And a file "phpspec.yml":
      """
      guard:
        status: active
        detection: mtime
      """
    And a class "src/App/Legacy.php":
      """
      <?php

      namespace App;

      class Legacy
      {
          public function neverTested(int $value): int
          {
              return $value * 2;
          }
      }
      """
    And a spec file "spec/App/Nothing.spec.php":
      """
      <?php

      describe('Nothing', function () {
          it('is specified', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec guard
    And I run phpspec run with coverage options ""
    Then the output should not contain "Guard Violation"
    And the exit code should be 0

  Scenario: A misspelt guard setting stops the run rather than quietly ungating it
    Given a PSR-4 project with "spec" and "src" directories
    And a file "phpspec.yml":
      """
      guard:
        stauts: active
      """
    And a spec file "spec/App/Nothing.spec.php":
      """
      <?php

      describe('Nothing', function () {
          it('is specified', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with coverage options ""
    Then the output should contain "Unknown guard key"
    And the output should contain "Did you mean"
    And the exit code should be 1

  Scenario: A setting YAML turned into a boolean is quoted back as a boolean
    Given a PSR-4 project with "spec" and "src" directories
    And a file "phpspec.yml":
      """
      guard:
        status: true
      """
    And a spec file "spec/App/Nothing.spec.php":
      """
      <?php

      describe('Nothing', function () {
          it('is specified', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with coverage options ""
    Then the output should contain "not true"
    And the exit code should be 1

  Scenario: A checkout that never recorded a baseline is told, and is not failed
    Given a PSR-4 project with "spec" and "src" directories
    And a file "phpspec.yml":
      """
      guard:
        status: active
      """
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

      describe('Nothing', function () {
          it('is specified', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with coverage options ""
    Then the output should contain "no baseline is recorded in this checkout"
    And the output should not contain "Guard Violation"
    And the exit code should be 0

  Scenario: The check refuses when it has no baseline to judge against
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Nothing.spec.php":
      """
      <?php

      describe('Nothing', function () {
          it('is specified', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with coverage options "--coverage-json=cov.json"
    And I run phpspec guard with option "--check --coverage=cov.json"
    Then the output should contain "no baseline is recorded in this checkout"
    And the exit code should be 1

  Scenario: The check refuses a coverage report the code has moved on from
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
    And I run phpspec run with coverage options "--coverage-json=cov.json"
    And a class "src/App/Basket.php":
      """
      <?php

      namespace App;

      class Basket
      {
          public function total(): int
          {
              return 1;
          }
      }
      """
    And I run phpspec guard with option "--check --coverage=cov.json"
    Then the output should contain "has changed since"
    And the exit code should be 1

  Scenario: The check refuses a report that covered nothing at all
    Given a PSR-4 project with "spec" and "src" directories
    And a file "cov.json":
      """
      {"version": 1, "tests": {}, "sources": {}}
      """
    When I run phpspec guard
    And I run phpspec guard with option "--check --coverage=cov.json"
    Then the output should contain "no coverage was collected"
    And the exit code should be 1

  Scenario: The check asks for the coverage it is meant to judge with
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec guard with option "--check"
    Then the output should contain "needs a coverage report"
    And the exit code should be 1

  Scenario: The check says so when the report it was pointed at is not there
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec guard with option "--check --coverage=nope.json"
    Then the output should contain "Coverage file not found"
    And the exit code should be 1

  Scenario: Turning guard on again moves the baseline to where the session is now
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec guard
    And I run phpspec guard
    Then the output should contain "Guard is on"
    And the file ".phpspec/guard/baseline.json" should contain "recorded"
