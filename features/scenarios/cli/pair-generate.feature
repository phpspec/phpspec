Feature: Pair mode /generate turns words into a diff to confirm
  As a developer pairing with phpspec
  I want to describe a change in plain English and get a diff to approve
  So that I can grow specs and code without hand-typing them

  Scenario: /generate needs an AI provider
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "/generate a Calculator that adds"
    Then the output should contain "AI configuration required"

  Scenario: /generate without an instruction shows usage
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "/generate"
    Then the output should contain "Usage: /generate"
