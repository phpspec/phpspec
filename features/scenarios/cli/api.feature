Feature: Spec API reference
  As a developer or a coding agent
  I want phpspec to describe its own spec-writing surface
  So that I never guess at a function, a matcher, or when an expectation runs

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: The api command prints the surface as prose, read off the code
    When I run phpspec api
    Then the exit code should be 0
    And the output should contain "Timing"
    And the output should contain "describe(string $context, Closure $examples): void"
    And the output should contain "toThrow(string $exceptionClass = '', ?string $message = null): static"
    And the output should contain "mock(string $class): object"
    And the output should contain "given(string $pattern, Closure $fn): void"
    And the output should contain "visit(string $path): PhpSpec\Browser\Response"

  Scenario: The api command answers a coding agent as one JSON object
    When I run phpspec api with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "timing"
    And the output should contain "runs_subject"
    And the output should contain "docs/matchers.md"
