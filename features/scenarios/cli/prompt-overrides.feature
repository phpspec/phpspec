Feature: Project-level prompt overrides
  As a team using phpspec's AI commands
  I want to override the shipped prompts from my own project
  So that tuning the AI's voice survives composer updates

  Scenario: a project prompt file overrides the shipped one and the capture log says so
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key
      """
    And a file ".phpspec/prompts/commands/generate.txt":
      """
      You are OUR generator. House rule: never invent fixture domains.
      """
    When I run phpspec command "generate a spec for App/Basket that holds products"
    Then the file ".phpspec/ai/last-request.json" should contain "commands/generate (project)"
    And the file ".phpspec/ai/last-request.json" should contain "OUR generator"

  Scenario: without an override the capture log names the shipped prompt plainly
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key
      """
    When I run phpspec command "generate a spec for App/Basket that holds products"
    Then the file ".phpspec/ai/last-request.json" should contain "commands/generate"
    And the file ".phpspec/ai/last-request.json" should not contain "(project)"
