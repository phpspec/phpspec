Feature: Generate code from a natural-language instruction
  As a developer
  I want to describe a change in plain English and have phpspec generate it
  So that I can drive out examples and implementation without hand-writing them

  Scenario: generate requires AI configuration
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec command "generate an example for App/Calculator that adds"
    Then the output should contain "AI configuration required"

  Scenario: generate analyses the instruction when AI is configured
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key
      """
    When I run phpspec command "generate a Calculator that adds two numbers"
    Then the output should contain "Generating"

  Scenario: a bare feature filename lands under the features directory, not the project root
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key
      """
    When I run phpspec command "generate a feature for completing_a_task.feature"
    Then a file "features/completing_a_task.feature" should be generated
    And the file "features/completing_a_task.feature" should contain "Feature:"
    And the output should contain "features/completing_a_task.feature"

  Scenario: generate writes step definitions for the last-touched feature deterministically
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
    When I run phpspec command "generate the steps"
    Then the output should contain "features/steps/adding_a_task.steps.php"
    And the file "features/steps/adding_a_task.steps.php" should contain "given("
