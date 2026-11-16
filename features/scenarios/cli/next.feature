Feature: AI-powered next suggestion
  As a developer
  I want phpspec to suggest what to describe next
  So that I can maintain momentum in my BDD workflow

  Scenario: Require AI configuration
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec next
    Then the exit code should not be 0
    And the output should contain "AI configuration required"

  Scenario: A hyphenated api-key spelling is accepted
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      ai:
        provider: google
        api-key: bogus-key
      """
    And a fake AI provider
    When I run phpspec next
    Then the output should not contain "AI configuration required"

  Scenario: An ai section naming a provider no package provides is told so
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: bogus-key
      """
    When I run phpspec next
    Then the exit code should not be 0
    And the output should contain "is not installed, and no papi provider package is."
    And the output should not contain "AI configuration required"

  Scenario: Forgetting the provider, with no papi provider package installed
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      ai:
        api_key: bogus-key
      """
    When I run phpspec next
    Then the exit code should not be 0
    And the output should contain "The ai section is missing provider"
    And the output should contain "The ai section is missing provider, and no papi provider package is installed."

  Scenario: Suggest next step for a project with specs but no features
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    And a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          it('adds two numbers', function () {
              expect((new Calculator())->add(2, 3))->toBe(5);
          });
      });
      """
    And a class "src/App/Calculator.php":
      """
      <?php
      namespace App;

      class Calculator {
          public function add(int $a, int $b): int { return $a + $b; }
      }
      """
    And a fake AI provider
    When I run phpspec next
    Then the output should contain "Analysing"
