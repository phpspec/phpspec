Feature: Coverage exclusions
  As a developer
  I want to leave code out of coverage, in the code or in the config
  So that the percentage measures the code I mean to specify

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: An interface never counts
    Given a class "src/App/Pricing.php":
      """
      <?php

      namespace App;

      interface Pricing
      {
          public function add(int $a, int $b): int;
      }
      """
    And a class "src/App/Calculator.php":
      """
      <?php

      namespace App;

      class Calculator implements Pricing
      {
          public function add(int $a, int $b): int
          {
              return $a + $b;
          }
      }
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php

      use App\Calculator;

      describe('Calculator', function () {
          it('adds', fn() => expect((new Calculator())->add(1, 2))->toBe(3));
      });
      """
    When I run phpspec run with coverage options "--coverage"
    Then the output should contain "App/Calculator.php"
    And the output should not contain "App/Pricing.php"
    And the output should contain "Total: 100.0%"

  Scenario: An interface never counts under PCOV either
    Given a class "src/App/Pricing.php":
      """
      <?php

      namespace App;

      interface Pricing
      {
          public function add(int $a, int $b): int;
      }
      """
    And a class "src/App/Calculator.php":
      """
      <?php

      namespace App;

      class Calculator implements Pricing
      {
          public function add(int $a, int $b): int
          {
              return $a + $b;
          }
      }
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php

      use App\Calculator;

      describe('Calculator', function () {
          it('adds', fn() => expect((new Calculator())->add(1, 2))->toBe(3));
      });
      """
    When I run phpspec run under PCOV with coverage options "--coverage"
    Then the output should not contain "App/Pricing.php"
    And the output should contain "Total: 100.0%"

  Scenario: A class marked ExcludeFromCoverage does not count
    Given a class "src/App/Legacy.php":
      """
      <?php

      namespace App;

      use PhpSpec\ExcludeFromCoverage;

      #[ExcludeFromCoverage]
      class Legacy
      {
          public function convert(string $value): string
          {
              return strtoupper($value);
          }
      }
      """
    And a spec file "spec/App/Legacy.spec.php":
      """
      <?php

      use App\Legacy;

      describe('Legacy', function () {
          it('exists', fn() => expect(new Legacy())->toBeAnInstanceOf(Legacy::class));
      });
      """
    When I run phpspec run with coverage options "--coverage"
    Then the output should not contain "App/Legacy.php"

  Scenario: A method marked with @codeCoverageIgnore does not count
    Given a class "src/App/Calculator.php":
      """
      <?php

      namespace App;

      class Calculator
      {
          public function add(int $a, int $b): int
          {
              return $a + $b;
          }

          /** @codeCoverageIgnore */
          public function describe(): string
          {
              return 'a calculator';
          }
      }
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php

      use App\Calculator;

      describe('Calculator', function () {
          it('adds', fn() => expect((new Calculator())->add(1, 2))->toBe(3));
      });
      """
    When I run phpspec run with coverage options "--coverage"
    Then the output should contain "Total: 100.0%"

  Scenario: The config leaves out a directory, a namespace, a method and a function
    Given a file "phpspec.json":
      """
      {
          "autoload": {"App\\": "src/App"},
          "coverage_exclude": ["src/App/Generated/", "App\\Legacy\\*", "App\\Calculator::describe", "App\\shout()"]
      }
      """
    And a class "src/App/Generated/Mapper.php":
      """
      <?php

      namespace App\Generated;

      class Mapper
      {
          public function map(array $row): array
          {
              return array_values($row);
          }
      }
      """
    And a class "src/App/Legacy/Converter.php":
      """
      <?php

      namespace App\Legacy;

      class Converter
      {
          public function convert(string $value): string
          {
              return strtoupper($value);
          }
      }
      """
    And a file "src/App/functions.php":
      """
      <?php

      namespace App;

      function shout(string $value): string
      {
          return strtoupper($value) . '!';
      }
      """
    And a class "src/App/Calculator.php":
      """
      <?php

      namespace App;

      class Calculator
      {
          public function add(int $a, int $b): int
          {
              return $a + $b;
          }

          public function describe(): string
          {
              return 'a calculator';
          }
      }
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/functions.php';

      use App\Calculator;
      use App\Generated\Mapper;
      use App\Legacy\Converter;

      describe('Calculator', function () {
          it('adds', fn() => expect((new Calculator())->add(1, 2))->toBe(3));
          it('loads what the config leaves out', function () {
              expect(new Mapper())->toBeAnInstanceOf(Mapper::class);
              expect(new Converter())->toBeAnInstanceOf(Converter::class);
          });
      });
      """
    When I run phpspec run with coverage options "--coverage"
    Then the output should not contain "App/Generated/Mapper.php"
    And the output should not contain "App/Legacy/Converter.php"
    And the output should contain "Total: 100.0%"

  Scenario: A comment tag leaves out a branch, the line below it, or a stretch
    Given a class "src/App/Calculator.php":
      """
      <?php

      namespace App;

      class Calculator
      {
          public function divide(int $a, int $b): int
          {
              if ($b === 0) { // @codeCoverageIgnore
                  throw new \DivisionByZeroError('Division by zero');
              }

              if ($a < 0) {
                  // @codeCoverageIgnore
                  return -1;
              }

              // @codeCoverageIgnoreStart
              if ($a > 1000) {
                  return 1000;
              }
              // @codeCoverageIgnoreEnd

              return intdiv($a, $b);
          }
      }
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php

      use App\Calculator;

      describe('Calculator', function () {
          it('divides', fn() => expect((new Calculator())->divide(4, 2))->toBe(2));
      });
      """
    When I run phpspec run with coverage options "--coverage"
    Then the output should contain "Total: 100.0%"

  Scenario: --coverage-min cannot be enforced while a source file has no coverage recorded during an example
    Given a file "phpspec.json":
      """
      {
          "autoload": {"App\\": "src/App"},
          "coverage_exclude": ["src/App/Generated/"]
      }
      """
    And a class "src/App/Greeter.php":
      """
      <?php

      namespace App;

      class Greeter
      {
          public function greet(string $name): string
          {
              return "Hello $name";
          }
      }
      """
    And a class "src/App/Untested.php":
      """
      <?php

      namespace App;

      class Untested
      {
          public function run(): string
          {
              return 'never called';
          }
      }
      """
    And a class "src/App/Reports/Monthly.php":
      """
      <?php

      namespace App\Reports;

      class Monthly
      {
          public function total(): int
          {
              return 0;
          }
      }
      """
    And a class "src/App/Pricing.php":
      """
      <?php

      namespace App;

      interface Pricing
      {
          public function price(): int;
      }
      """
    And a class "src/App/Named.php":
      """
      <?php

      namespace App;

      class Named
      {
          public string $name {
              get => 'Ann';
          }
      }
      """
    And a class "src/App/Generated/Mapper.php":
      """
      <?php

      namespace App\Generated;

      class Mapper
      {
          public function map(array $row): array
          {
              return array_values($row);
          }
      }
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php

      describe(App\Greeter::class, function () {
          it('greets', fn () => expect((new App\Greeter())->greet('Ann'))->toBe('Hello Ann'));
      });
      """
    When I run phpspec run with coverage options "--coverage-min=100"
    Then the output should contain "Cannot enforce --coverage-min=100: coverage is incomplete."
    And the output should contain "No coverage was recorded for src/App/Reports/Monthly.php during an example or scenario."
    And the output should contain "No coverage was recorded for src/App/Named.php during an example or scenario."
    And the output should contain "No coverage was recorded for src/App/Untested.php during an example or scenario."
    And the output should not contain "Pricing.php"
    And the output should not contain "Mapper.php"
    And the output should not contain "meets the required"
    And the exit code should be 1

  Scenario: --coverage-min is enforced when every source file was loaded
    Given a class "src/App/Greeter.php":
      """
      <?php

      namespace App;

      class Greeter
      {
          public function greet(string $name): string
          {
              return "Hello $name";
          }
      }
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php

      describe(App\Greeter::class, function () {
          it('greets', fn () => expect((new App\Greeter())->greet('Ann'))->toBe('Hello Ann'));
      });
      """
    When I run phpspec run with coverage options "--coverage-min=100"
    Then the output should contain "Code coverage 100.0% meets the required 100.0%"
    And the exit code should be 0

  Scenario: The agent document names the source files with no coverage recorded
    Given a class "src/App/Greeter.php":
      """
      <?php

      namespace App;

      class Greeter
      {
          public function greet(string $name): string
          {
              return "Hello $name";
          }
      }
      """
    And a class "src/App/Untested.php":
      """
      <?php

      namespace App;

      class Untested
      {
          public function run(): string
          {
              return 'never called';
          }
      }
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php

      describe(App\Greeter::class, function () {
          it('greets', fn () => expect((new App\Greeter())->greet('Ann'))->toBe('Hello Ann'));
      });
      """
    When I run phpspec run with coverage options "--format=agent --coverage-min=100"
    Then the output should contain:
      """
      "met":false,"unrecorded":["src/App/Untested.php"]
      """
    And the exit code should be 1

  Scenario: A percentage is cut, never rounded up to the threshold it falls short of
    Given a class "src/App/Meter.php":
      """
      <?php

      namespace App;

      class Meter
      {
          public function read(): int
          {
              $reading = 2;

              return $reading;
          }

          public function reset(): int
          {
              return 0;
          }
      }
      """
    And a spec file "spec/App/Meter.spec.php":
      """
      <?php

      describe(App\Meter::class, function () {
          it('reads', fn () => expect((new App\Meter())->read())->toBe(2));
      });
      """
    When I run phpspec run with coverage options "--coverage --coverage-min=66.7"
    Then the output should contain "Total: 66.6% (2/3)"
    And the output should contain "Code coverage 66.6% is below the required 66.7%"
    And the exit code should be 1

  Scenario: Guard leaves excluded code alone
    Given a class "src/App/Basket.php":
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

      use PhpSpec\ExcludeFromCoverage;

      class Basket
      {
          public function total(): int
          {
              return 0;
          }

          #[ExcludeFromCoverage]
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
    Then the output should not contain "Guard Violation"
    And the exit code should be 0

  Scenario: An exclusion list that is not a list is refused, naming the file
    Given a file "phpspec.json":
      """
      {"autoload": {"App\\": "src/App"}, "coverage_exclude": "src/"}
      """
    When I run phpspec run in a fresh process
    Then the output should contain:
      """
      phpspec.json: coverage_exclude expects a list of paths and names, "src/" given.
      """
    And the exit code should not be 0
