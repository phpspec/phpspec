Feature: Coverage engines
  As a developer
  I want coverage collected with PCOV or Xdebug, whichever my PHP has on
  So that a coverage run works without my choosing an engine by hand

  Background:
    Given a PSR-4 project with "spec" and "src" directories
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
      }
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php

      use App\Calculator;

      describe('Calculator', function () {
          it('adds two numbers', function () {
              expect((new Calculator())->add(1, 2))->toBe(3);
          });
      });
      """

  Scenario: PCOV collects when it is the engine that is on
    When I run phpspec run under PCOV with coverage options "--coverage"
    Then the output should contain "Code Coverage Report:"
    And the output should contain "App/Calculator.php"
    And the exit code should be 0

  Scenario: The config names the engine
    Given a file "phpspec.json":
      """
      {"autoload": {"App\\": "src/App"}, "coverage_adapter": "pcov"}
      """
    When I run phpspec run under PCOV with coverage options "--coverage-json=coverage.json"
    Then the file "coverage.json" should contain "src/App/Calculator.php"
    And the file "coverage.json" should contain "spec/App/Calculator.spec.php::Calculator > adds two numbers"

  Scenario: A named engine that is off refuses the run with the command that switches it on
    Given PCOV is loaded
    And a file "phpspec.json":
      """
      {"autoload": {"App\\": "src/App"}, "coverage_adapter": "pcov"}
      """
    When I run phpspec run in a fresh process with option "--coverage"
    Then the output should contain "Code coverage requires PCOV enabled"
    And the output should contain "php -d pcov.enabled=1"
    And the exit code should be 1

  Scenario: With nothing named and no engine on, the run names the engines it takes
    When I run phpspec run in a fresh process with option "--coverage"
    Then the output should contain "Code coverage requires PCOV or Xdebug in coverage mode"
    And the exit code should be 1

  Scenario: An engine the config does not know is refused naming the file
    Given a file "phpspec.json":
      """
      {"autoload": {"App\\": "src/App"}, "coverage_adapter": "pcv"}
      """
    When I run phpspec run in a fresh process
    Then the output should contain:
      """
      phpspec.json: coverage_adapter expects pcov or xdebug, "pcv" given.
      """
    And the exit code should not be 0

  Scenario: PCOV tracing less than the run reports on runs phpspec again, tracing a directory that holds it all
    Given a class "lib/Acme/Multiplier.php":
      """
      <?php

      namespace Acme;

      class Multiplier
      {
          public function multiply(int $a, int $b): int
          {
              return $a * $b;
          }
      }
      """
    And a spec file "spec/Acme/Multiplier.spec.php":
      """
      <?php

      describe('Multiplier', function () {
          it('multiplies', function () {
              expect((new Acme\Multiplier())->multiply(2, 3))->toBe(6);
          });
      });
      """
    When I run phpspec run under PCOV with coverage options "--coverage"
    Then the output should contain "src/App/Calculator.php"
    And the output should contain "lib/Acme/Multiplier.php"
    And the output should not contain "PCOV traces"
    And the output should contain "Once you spec, you never go back!" exactly 1 times
    And the exit code should be 0

  Scenario: A coding agent reads one document from a run that started itself again under PCOV
    Given a class "lib/Acme/Multiplier.php":
      """
      <?php

      namespace Acme;

      class Multiplier
      {
          public function multiply(int $a, int $b): int
          {
              return $a * $b;
          }
      }
      """
    When I run phpspec run under PCOV with coverage options "--coverage --format=agent"
    Then the output should contain "run_started" exactly 1 times
    And the output should contain "summary" exactly 1 times
    And the exit code should be 0

  Scenario: Parallel workers collect with PCOV as the parent does
    Given a class "src/App/Greeter.php":
      """
      <?php

      namespace App;

      class Greeter
      {
          public function greet(string $name): string
          {
              return "Hello, $name!";
          }
      }
      """
    And a spec file "spec/App/Greeter.spec.php":
      """
      <?php

      use App\Greeter;

      describe('Greeter', function () {
          it('greets by name', function () {
              expect((new Greeter())->greet('Marcello'))->toBe('Hello, Marcello!');
          });
      });
      """
    When I run phpspec run under PCOV with coverage options "--coverage-json=coverage.json --parallel=2"
    Then the file "coverage.json" should contain "spec/App/Calculator.spec.php::Calculator > adds two numbers"
    And the file "coverage.json" should contain "spec/App/Greeter.spec.php::Greeter > greets by name"
    And the file "coverage.json" should contain "src/App/Greeter.php"

  Scenario: Each example is attributed the lines it reached and no other's
    Given a class "src/App/Gate.php":
      """
      <?php

      namespace App;

      class Gate
      {
          public function answer(bool $open): string
          {
              if ($open) {
                  return 'open';
              }

              return 'shut';
          }
      }
      """
    And a spec file "spec/App/Gate.spec.php":
      """
      <?php

      use App\Gate;

      describe('Gate', function () {
          it('opens', function () {
              expect((new Gate())->answer(true))->toBe('open');
          });

          it('shuts', function () {
              expect((new Gate())->answer(false))->toBe('shut');
          });
      });
      """
    When I run phpspec run under PCOV with coverage options "--coverage-json=coverage.json"
    Then the file "coverage.json" should attribute line 10 of "src/App/Gate.php" to "spec/App/Gate.spec.php::Gate > opens" only
    And the file "coverage.json" should attribute line 13 of "src/App/Gate.php" to "spec/App/Gate.spec.php::Gate > shuts" only
