Feature: AI-assisted pair programming
  As a developer
  I want to use natural language in pair mode
  So that I can generate specs and features conversationally

  Scenario: Help shows AI assistant as available when configured
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        model: gemini-2.5-flash
        api_key: test-key-123
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "AI assistant"
    And the output should contain "available"

  Scenario: Help shows AI as unavailable when the configured provider cannot start
    Given a phpspec.yaml config:
      """
      ai:
        provider: anthropic
        api_key: test-key-456
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "AI assistant"
    And the output should contain "unavailable"
    And the output should not contain "(available)"

  Scenario: A configured provider that cannot start explains why on a prompt
    Given a phpspec.yaml config:
      """
      ai:
        provider: anthropic
        api_key: test-key-789
      """
    When I run phpspec pair with input "write a spec for a Calculator"
    Then the output should contain "could not start"
    And the output should not contain "Unknown command"

  Scenario: Help shows AI as not configured when missing
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "AI assistant"
    And the output should contain "not configured"

  Scenario: Natural language without any AI config explains AI is off
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "hello world"
    Then the output should contain "natural language"
    And the output should not contain "Unknown command"

  Scenario: Next command is available via auto-delegation
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "/next"
    Then the output should not contain "Unknown command"

  Scenario: Help lists additional delegated commands
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "Additional commands"
    And the output should contain "next"
    And the output should contain "refactor"

  Scenario: Interactive questions offer numbered choices
    When I run phpspec pair with input "/describe App/Wanted" answering "1,3"
    Then the output should contain "1. Yes"
    And the output should contain "2. Yes, and don't ask again"
    And the output should contain "3. No"
    And a class file "src/App/Wanted.php" should be generated

  Scenario: Answering No declines the offer
    When I run phpspec pair with input "/describe App/Declined" answering "3,3"
    Then no file "src/App/Declined.php" should be generated

  Scenario: Answering always suppresses repeat questions of the same kind
    When I run phpspec pair with input "/describe App/First; /describe App/Second" answering "2,3,3"
    Then a class file "src/App/First.php" should be generated
    And a class file "src/App/Second.php" should be generated
    And the output should not contain "create class App\Second"

  Scenario: Describe with a slash path generates a namespaced class
    When I run phpspec pair with input "/describe App/Basket" answering "1,3"
    Then the file "src/App/Basket.php" should contain "namespace App;"
    And the file "src/App/Basket.php" should contain "class Basket"

  Scenario: Running specs in pair mode offers method generation as a numbered chooser
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
    When I run phpspec pair with input "/run" answering "1"
    Then the output should contain "1. Yes"
    And the output should contain "2. Yes, and don't ask again"
    And the output should contain "3. No"
    And the class "src/App/Calculator.php" should contain "function add"

  Scenario: Running specs twice in the same pair session shows results both times
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
      }
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
    When I run phpspec pair with input "/run; /run"
    Then the output should contain "1 example (1 passes)" exactly 2 times

  Scenario: Running a spec that declares a top-level type twice does not crash
    Given a spec file "spec/App/Widget.spec.php":
      """
      <?php
      interface WidgetCollaborator
      {
          public function help(): string;
      }

      describe('Widget', function () {
          it('uses a collaborator', function (WidgetCollaborator $collaborator) {
              expect($collaborator)->toBeAnInstanceOf(WidgetCollaborator::class);
          });
      });
      """
    When I run phpspec pair with input "/run; /run"
    Then the output should contain "1 example (1 passes)" exactly 2 times
    And the output should not contain "Cannot declare interface"

  Scenario: A method generated mid-session is picked up by the next run
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
          it('adds numbers', fn() => expect((new Calculator())->add())->toBe(null));
      });
      """
    When I run phpspec pair with input "/run; /run" answering "1"
    Then the file "src/App/Calculator.php" should contain "function add"
    And the output should contain "1 example (1 passes)"

  Scenario: Exemplify shows a diff, marking only the added example as new
    Given a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('adds numbers', fn() => expect((new Calculator())->add())->toBe(null));
      });
      """
    When I run phpspec pair with input "/exemplify App/Calculator subtract" answering "3"
    Then the output should contain "[MODIFIED]"
    And the output should contain "subtract"
    And the output should not contain "+ <?php"
    And the output should not contain "+ describe"

  Scenario: Exemplifying the same method twice does not duplicate the example
    Given a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('adds numbers', fn() => expect((new Calculator())->add())->toBe(null));
      });
      """
    When I run phpspec pair with input "/exemplify App/Calculator subtract; /exemplify App/Calculator subtract" answering "3,3"
    Then the output should contain "already exists"
    And the output should contain "should subtract" exactly 1 times

  Scenario: Generating a method marks only the new lines as added, not the shifted brace
    Given a class "src/App/Calculator.php":
      """
      <?php
      namespace App;

      class Calculator
      {
          public function add()
          {
          }
      }
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('subtracts numbers', fn() => expect((new Calculator())->subtract())->toBe(null));
      });
      """
    When I run phpspec pair with input "/run" answering "1"
    Then the output should contain "function subtract"
    And the output should not contain "+ }"
