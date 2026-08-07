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

  Scenario: A run nobody can answer writes nothing into the source tree
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec run
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "accept-offers"
    And the exit code should be 1

  # An empty answer at a terminal is somebody pressing Enter, which takes the
  # default. Nothing to read at all is nobody there, and must not be read as one.
  Scenario: A question nobody is there to answer writes nothing either
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe('Basket', function () {
          it('totals nothing to start with', function () {
              expect((new Basket())->total())->toBe(0);
          });
      });
      """
    When I run phpspec run with nobody to answer it
    Then no file "src/App/Basket.php" should be generated
    And the output should contain "Nothing was written"

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

  Scenario: Describe refuses a name that is not a class
    When I run phpspec describe "1"
    Then the exit code should be 1
    And the output should contain "not a valid class name"
    And no file "spec/1.spec.php" should be generated

  Scenario: Describe refuses a name that is not an identifier
    When I run phpspec describe "Foo-Bar"
    Then the exit code should be 1
    And the output should contain "not a valid class name"
    And no file "spec/Foo-Bar.spec.php" should be generated

  Scenario: Describe asks for the class it should describe
    When I run phpspec describe ""
    Then the exit code should be 1
    And the output should contain "Describe what?"
    And no file "spec/.spec.php" should be generated

  Scenario: Exemplify refuses a method that is not an identifier
    When I run phpspec exemplify "App\Printer" "2print"
    Then the exit code should be 1
    And the output should contain "not a valid method name"
    And no file "spec/App/Printer.spec.php" should be generated

  Scenario: Exemplify command adds an example to an existing spec
    When I run phpspec describe "App\Converter"
    And I run phpspec exemplify "App\Converter" "convert"
    Then the output should contain "Example for App\Converter::convert added."
    And the spec file should contain an example for "convert"

  Scenario: Exemplify command creates a spec if it does not exist
    When I run phpspec exemplify "App\Printer" "print"
    Then a spec file "spec/App/Printer.spec.php" should be generated
    And the spec file should contain an example for "print"
