Feature: Pair mode roles — /swap changes who holds the keyboard
  As a developer pairing with phpspec
  I want to swap between driving and navigating
  So that either of us can hold the keyboard while the other thinks one step ahead

  Scenario: /swap hands the keyboard to the AI and back
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key
      """
    When I run phpspec pair with input "/swap; /swap"
    Then the output should contain "I'm driving"
    And the output should contain "You're driving"

  Scenario: /help names the current role
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "/swap"

  Scenario: swapping without an AI provider explains it needs one
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec pair with input "/swap"
    Then the output should contain "needs an AI"
