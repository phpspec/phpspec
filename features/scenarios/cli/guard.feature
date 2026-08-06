Feature: Guard
  As a developer driving out code with tests
  I want PhpSpec to refuse logic I never specified
  So that the cycle holds even when I am moving fast

  Scenario: Turning guard on says so in the config and remembers where the session started
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec guard
    Then the output should contain "Guard is on"
    And the output should contain "Baseline recorded"
    And a file ".phpspec/guard/baseline.json" should be generated

  Scenario: Turning guard on leaves the rest of the config as it was written
    Given a PSR-4 project with "spec" and "src" directories
    And a file "phpspec.yml":
      """
      # the paths this project uses
      spec_path: spec
      src_path: src
      """
    When I run phpspec guard
    Then the file "phpspec.yml" should contain "# the paths this project uses"
    And the file "phpspec.yml" should contain "spec_path: spec"
    And the file "phpspec.yml" should contain "status: active"

  Scenario: Turning guard on again moves the baseline to where the session is now
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec guard
    And I run phpspec guard
    Then the output should contain "Guard is on"
    And the file ".phpspec/guard/baseline.json" should contain "recorded"
