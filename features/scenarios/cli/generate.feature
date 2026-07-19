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
