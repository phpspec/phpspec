Feature: JSON coverage report
  As a mutation testing tool author
  I want a machine-readable coverage report with per-example detail
  So that I can exercise only the tests that cover a mutated line

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

  Scenario: Generate a JSON coverage report file
    When I run phpspec run with coverage options "--coverage-json=coverage.json"
    Then a file "coverage.json" should be generated
    And the file "coverage.json" should contain:
      """
      "version": 1
      """
    And the file "coverage.json" should contain "spec/App/Calculator.spec.php::Calculator > adds two numbers"

  Scenario: The report maps covered source lines to the examples that cover them
    When I run phpspec run with coverage options "--coverage-json=coverage.json"
    Then the file "coverage.json" should contain "src/App/Calculator.php"
    And the file "coverage.json" should contain:
      """
      "lines"
      """

  Scenario: The report includes per-test timing and memory usage
    When I run phpspec run with coverage options "--coverage-json=coverage.json"
    Then the file "coverage.json" should contain:
      """
      "time"
      """
    And the file "coverage.json" should contain:
      """
      "memory"
      """

  Scenario: The report includes source and spec file checksums
    When I run phpspec run with coverage options "--coverage-json=coverage.json"
    Then the file "coverage.json" should contain:
      """
      "checksum"
      """
    And the file "coverage.json" should contain:
      """
      "spec_checksum"
      """

  Scenario: Combining the JSON report with a Clover report produces both
    When I run phpspec run with coverage options "--coverage-json=coverage.json --coverage-clover=clover.xml"
    Then a file "coverage.json" should be generated
    And a file "clover.xml" should be generated
    And the file "clover.xml" should contain "App/Calculator.php"

  Scenario: Scope the coverage report to another source directory from the CLI
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

      use Acme\Multiplier;

      describe('Multiplier', function () {
          it('multiplies two numbers', function () {
              expect((new Multiplier())->multiply(2, 3))->toBe(6);
          });
      });
      """
    When I run phpspec run with coverage options "--coverage-json=coverage.json --coverage-src=lib"
    Then the file "coverage.json" should contain "lib/Acme/Multiplier.php"
    And the file "coverage.json" should contain "spec/Acme/Multiplier.spec.php::Multiplier > multiplies two numbers"

  Scenario: Parallel runs produce the same JSON coverage report
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
    When I run phpspec run with coverage options "--coverage-json=coverage.json --parallel=2"
    Then a file "coverage.json" should be generated
    And the file "coverage.json" should contain "spec/App/Calculator.spec.php::Calculator > adds two numbers"
    And the file "coverage.json" should contain "spec/App/Greeter.spec.php::Greeter > greets by name"
    And the file "coverage.json" should contain "src/App/Calculator.php"
    And the file "coverage.json" should contain "src/App/Greeter.php"
