Feature: Pair mode understands commands typed as plain English
  As a developer pairing with phpspec
  I want command words to work with or without a leading slash
  So that the conversation flows without command syntax in the way

  Scenario: run without a slash runs the specs
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a spec file "spec/App/Alpha.spec.php":
      """
      <?php
      describe('App\Alpha', function () {
          it('flags the spec marker', fn() => expect('spec-marker')->toBe('spec-marker-pass'));
      });
      """
    And a feature file "features/marker.feature":
      """
      Feature: Marker
        Scenario: Feature marker scenario
          Then the feature marker holds
      """
    And a step file "features/steps/marker.steps.php":
      """
      <?php
      then('the feature marker holds', fn() => expect('feature-marker')->toBe('feature-marker-pass'));
      """
    When I run phpspec pair with input "run"
    Then the output should contain "spec-marker"
    And the output should not contain "feature-marker"
    And the output should not contain "No AI provider"

  Scenario: run features without a slash runs the story suite
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a spec file "spec/App/Alpha.spec.php":
      """
      <?php
      describe('App\Alpha', function () {
          it('flags the spec marker', fn() => expect('spec-marker')->toBe('spec-marker-pass'));
      });
      """
    And a feature file "features/marker.feature":
      """
      Feature: Marker
        Scenario: Feature marker scenario
          Then the feature marker holds
      """
    And a step file "features/steps/marker.steps.php":
      """
      <?php
      then('the feature marker holds', fn() => expect('feature-marker')->toBe('feature-marker-pass'));
      """
    When I run phpspec pair with input "run features"
    Then the output should contain "feature-marker"
    And the output should not contain "spec-marker"
    And the output should not contain "No AI provider"

  Scenario: run --all without a slash runs both suites
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a spec file "spec/App/Alpha.spec.php":
      """
      <?php
      describe('App\Alpha', function () {
          it('flags the spec marker', fn() => expect('spec-marker')->toBe('spec-marker-pass'));
      });
      """
    And a feature file "features/marker.feature":
      """
      Feature: Marker
        Scenario: Feature marker scenario
          Then the feature marker holds
      """
    And a step file "features/steps/marker.steps.php":
      """
      <?php
      then('the feature marker holds', fn() => expect('feature-marker')->toBe('feature-marker-pass'));
      """
    When I run phpspec pair with input "run --all"
    Then the output should contain "spec-marker"
    And the output should contain "feature-marker"

  Scenario: options on a slash command reach the runner
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a spec file "spec/App/Alpha.spec.php":
      """
      <?php
      describe('App\Alpha', function () {
          it('flags the spec marker', fn() => expect('spec-marker')->toBe('spec-marker-pass'));
      });
      """
    And a feature file "features/marker.feature":
      """
      Feature: Marker
        Scenario: Feature marker scenario
          Then the feature marker holds
      """
    And a step file "features/steps/marker.steps.php":
      """
      <?php
      then('the feature marker holds', fn() => expect('feature-marker')->toBe('feature-marker-pass'));
      """
    When I run phpspec pair with input "/run --all"
    Then the output should contain "spec-marker"
    And the output should contain "feature-marker"

  Scenario: a path and an option travel together to the runner
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Alpha.spec.php":
      """
      <?php
      describe('App\Alpha', function () {
          it('fails first', fn() => expect('alpha-marker')->toBe('alpha-marker-pass'));
      });
      """
    And a spec file "spec/App/Beta.spec.php":
      """
      <?php
      describe('App\Beta', function () {
          it('fails second', fn() => expect('beta-marker')->toBe('beta-marker-pass'));
      });
      """
    When I run phpspec pair with input "/run spec --stop-on-failure"
    Then the output should contain "alpha-marker"
    And the output should not contain "beta-marker"

  Scenario: plain English that merely starts with a command word stays conversation
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Alpha.spec.php":
      """
      <?php
      describe('App\Alpha', function () {
          it('flags the spec marker', fn() => expect('spec-marker')->toBe('spec-marker-pass'));
      });
      """
    When I run phpspec pair with input "run me through the design; help me understand this project; next time we should refactor"
    Then the output should contain "No AI provider is configured"
    And the output should not contain "Available commands"
    And the output should not contain "spec-marker"

  Scenario: help as a bare word shows the help screen
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec pair with input "help"
    Then the output should contain "Available commands"

  Scenario: describe with a class routes to the describe command
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec pair with input "describe App/Basket"
    Then the output should contain "Specification for"
    And a spec file "spec/App/Basket.spec.php" should be generated

  Scenario: generate routes to the generate command even in plain English
    Given a PSR-4 project with "spec" and "src" directories
    When I run phpspec pair with input "generate a spec for addTask"
    Then the output should contain "AI configuration required"
    And the output should not contain "No AI provider is configured"
