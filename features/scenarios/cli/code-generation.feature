Feature: Code generation
  As a developer
  I want PhpSpec to generate code from my specs
  So that I can follow a spec-first workflow

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: Generate a method stub when spec calls missing method
    Given a class "src/App/Calculator.php":
      """
      <?php
      namespace App;

      class Calculator {}
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('adds numbers', function () {
              $calc = new Calculator();
              expect($calc->add(2, 3))->toBe(5);
          });
      });
      """
    When I run phpspec run and answer "y" to generation prompts
    Then the class "src/App/Calculator.php" should contain "function add"

  Scenario: Generate an interface from mock error
    Given a spec file "spec/App/Service.spec.php":
      """
      <?php
      describe('Service', function () {
          it('uses a logger', function () {
              $logger = mock(App\Logger::class);
              expect($logger)->toBeAnInstanceOf(App\Logger::class);
          });
      });
      """
    When I run phpspec run and answer "y" to generation prompts
    Then a file "src/App/Logger.php" should be generated
    And it should contain "interface Logger"

  Scenario: Describe command generates a spec
    When I run phpspec describe "App\Formatter"
    Then a spec file "spec/App/Formatter.spec.php" should be generated
    And it should contain a describe block for "App\Formatter"

  Scenario: Describe command with exemplified method
    When I run phpspec describe "App\Formatter" with option "-e render"
    Then a spec file "spec/App/Formatter.spec.php" should be generated
    And the spec file should contain an example for "render"

  Scenario: Exemplify command adds an example to an existing spec
    When I run phpspec describe "App\Converter"
    And I run phpspec exemplify "App\Converter" "convert"
    Then the output should contain "Example for App\Converter::convert added."
    And the spec file should contain an example for "convert"

  Scenario: Exemplify command creates a spec if it does not exist
    When I run phpspec exemplify "App\Printer" "print"
    Then a spec file "spec/App/Printer.spec.php" should be generated
    And the spec file should contain an example for "print"
