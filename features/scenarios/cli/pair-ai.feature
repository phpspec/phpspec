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

  Scenario: Help shows AI as available with Anthropic provider
    Given a phpspec.yaml config:
      """
      ai:
        provider: anthropic
        api_key: test-key-456
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "AI assistant"
    And the output should contain "available"

  Scenario: Help shows AI as available with OpenAI provider
    Given a phpspec.yaml config:
      """
      ai:
        provider: openai
        api_key: test-key-789
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "AI assistant"
    And the output should contain "available"

  Scenario: Help shows AI as not configured when missing
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "/help"
    Then the output should contain "AI assistant"
    And the output should contain "not configured"

  Scenario: Unknown command falls back to error without AI config
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "hello world"
    Then the output should contain "Unknown command"

  Scenario: Next command is available via auto-delegation
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    When I run phpspec pair with input "next"
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
    When I run phpspec pair with input "describe App/Wanted" answering "1,3"
    Then the output should contain "1. Yes"
    And the output should contain "2. Yes, and don't ask again"
    And the output should contain "3. No"
    And a class file "src/App/Wanted.php" should be generated

  Scenario: Answering No declines the offer
    When I run phpspec pair with input "describe App/Declined" answering "3,3"
    Then no file "src/App/Declined.php" should be generated

  Scenario: Answering always suppresses repeat questions of the same kind
    When I run phpspec pair with input "describe App/First; describe App/Second" answering "2,3,3"
    Then a class file "src/App/First.php" should be generated
    And a class file "src/App/Second.php" should be generated
    And the output should not contain "create class App\Second"

  Scenario: Describe with a slash path generates a namespaced class
    When I run phpspec pair with input "describe App/Basket" answering "1,3"
    Then the file "src/App/Basket.php" should contain "namespace App;"
    And the file "src/App/Basket.php" should contain "class Basket"
