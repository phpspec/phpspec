Feature: Story BDD lifecycle hooks
  As a developer writing features
  I want setup and teardown hooks around features, scenarios and steps
  So that I can manage test state for my stories cleanly

  Background:
    Given a PSR-4 project with "spec", "src", and "features" directories

  Scenario: afterScenario runs after each scenario
    Given a feature file "features/teardown.feature":
      """
      Feature: Teardown
        Scenario: First
          Given a passing step

        Scenario: Second
          Given a passing step
      """
    And a step file "features/steps/teardown.steps.php":
      """
      <?php
      given("a passing step", function () {
          expect(true)->toBeTrue();
      });

      afterScenario(function () {
          file_put_contents('teardown.log', "scenario done\n", FILE_APPEND);
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass
    And the file "teardown.log" should contain:
      """
      scenario done
      scenario done
      """

  Scenario: afterFeature runs once after all scenarios in a feature
    Given a feature file "features/closing.feature":
      """
      Feature: Closing
        Scenario: First
          Given a passing step

        Scenario: Second
          Given a passing step
      """
    And a step file "features/steps/closing.steps.php":
      """
      <?php
      given("a passing step", function () {
          expect(true)->toBeTrue();
      });

      afterFeature(function () {
          file_put_contents('closing.log', "feature done\n", FILE_APPEND);
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass
    And the file "closing.log" should contain "feature done"
    And the file "closing.log" should not contain:
      """
      feature done
      feature done
      """

  Scenario: afterStep runs after every step
    Given a feature file "features/stepwise.feature":
      """
      Feature: Stepwise
        Scenario: Two steps
          Given a passing step
          And another passing step
      """
    And a step file "features/steps/stepwise.steps.php":
      """
      <?php
      given("a passing step", function () {
          expect(true)->toBeTrue();
      });

      given("another passing step", function () {
          expect(true)->toBeTrue();
      });

      afterStep(function () {
          file_put_contents('steps.log', "step done\n", FILE_APPEND);
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass
    And the file "steps.log" should contain:
      """
      step done
      step done
      """

  Scenario: afterScenario still runs when a step fails
    Given a feature file "features/cleanup.feature":
      """
      Feature: Cleanup
        Scenario: Fails midway
          Given a failing step
      """
    And a step file "features/steps/cleanup.steps.php":
      """
      <?php
      given("a failing step", function () {
          expect(true)->toBeFalse();
      });

      afterScenario(function () {
          file_put_contents('cleanup.log', "cleaned up\n", FILE_APPEND);
      });
      """
    When I run phpspec run "features/"
    Then the exit code should not be 0
    And the file "cleanup.log" should contain "cleaned up"
