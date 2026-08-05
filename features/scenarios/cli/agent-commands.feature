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

  Scenario: describe accepts --format=agent as the canonical spelling of --agent
    When I run phpspec command "describe App/Cart --format=agent"
    Then the output should be valid JSON
    And the output should contain "describe"
    And a spec file "spec/App/Cart.spec.php" should be generated

  Scenario: exemplify accepts --format=agent too
    When I run phpspec command "describe App/Cart --format=agent"
    And I run phpspec command "exemplify App/Cart total --format=agent"
    Then the output should be valid JSON
    And the output should contain "total"

  Scenario: generate emits the agent document with applied receipts under --format=agent
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key
      """
    And a feature file "features/adding_a_task.feature":
      """
      Feature: Adding a task
        Scenario: Adding a task
          Given I have a todo list
          When I add the task "Buy milk"
          Then I should have 1 task on my list
      """
    When I run phpspec command "generate the steps --format=agent"
    Then the output should be valid JSON
    And the output should contain "applied"
    And the output should contain "features/steps/adding_a_task.steps.php"
    And no file "features/steps/adding_a_task.steps.php" should be generated
    When I accept the offers phpspec made
    Then the file "features/steps/adding_a_task.steps.php" should contain "given("
