Feature: Agent scaffolding commands
  As a coding agent
  I want describe and exemplify to emit machine-readable JSON receipts
  So that I can scaffold specs without parsing prose

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: describe --agent scaffolds a spec and emits a JSON receipt
    When I run phpspec command "describe App/Basket --agent"
    Then the output should be valid JSON
    And the output should contain "action"
    And the output should contain "created"
    And a spec file "spec/App/Basket.spec.php" should be generated

  Scenario: describe --agent is idempotent and reports it in the receipt
    When I run phpspec command "describe App/Basket --agent"
    And I run phpspec command "describe App/Basket --agent"
    Then the output should be valid JSON
    And the output should contain "false"

  Scenario: exemplify --agent adds an example and emits a JSON receipt
    When I run phpspec command "exemplify App/Basket checkout --agent"
    Then the output should be valid JSON
    And the output should contain "exemplify"
    And the output should contain "added"
    And the output should contain "checkout"
    And a spec file "spec/App/Basket.spec.php" should be generated
    And the spec file should contain an example for "checkout"
