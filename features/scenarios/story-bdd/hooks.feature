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

  Scenario: skip() in beforeScenario skips the scenario with its reason, and afterScenario still runs
    Given a feature file "features/remote.feature":
      """
      Feature: Remote
        Scenario: Fetching
          Given a step
          When another step
      """
    And a step file "features/steps/remote.steps.php":
      """
      <?php
      given("a step", function () {});
      when("another step", function () {});
      beforeScenario(fn () => skip('no service'));
      afterScenario(fn () => file_put_contents('closed.txt', 'yes'));
      """
    When I run phpspec run "features/"
    Then the output should contain "- Given a step (no service)"
    And the output should contain "1 scenario, 2 steps (2 skipped)"
    And a file "closed.txt" should be generated
    And the exit code should be 0

  Scenario: pending() in beforeFeature leaves every scenario of the feature pending with its reason
    Given a feature file "features/gateway.feature":
      """
      Feature: Gateway
        Scenario: Paying
          Given a step
        Scenario: Refunding
          Given a step
      """
    And a step file "features/steps/gateway.steps.php":
      """
      <?php
      given("a step", function () {});
      beforeFeature(fn () => pending('needs the gateway'));
      """
    When I run phpspec run "features/"
    Then the output should contain "○ Given a step (needs the gateway)" exactly 2 times
    And the output should contain "2 scenarios, 2 steps (2 pending)"
    And the exit code should be 0

  Scenario: skip() in beforeStep skips that step with its reason
    Given a feature file "features/flaky.feature":
      """
      Feature: Flaky
        Scenario: Probing
          Given a step
      """
    And a step file "features/steps/flaky.steps.php":
      """
      <?php
      given("a step", function () {});
      beforeStep(fn () => skip('probe offline'));
      """
    When I run phpspec run "features/"
    Then the output should contain "- Given a step (probe offline)"
    And the exit code should be 0

  Scenario: An error in beforeScenario errors that scenario, naming the hook, and the next scenario still runs
    Given a feature file "features/storage.feature":
      """
      Feature: Storage
        Scenario: Saving
          Given a step
        Scenario: Loading
          Given a step
      """
    And a step file "features/steps/storage.steps.php":
      """
      <?php
      given("a step", function () {});
      beforeScenario(function () {
          if (!file_exists('first.done')) {
              touch('first.done');
              throw new RuntimeException('database down');
          }
      });
      """
    When I run phpspec run "features/"
    Then the output should contain "database down (in beforeScenario)"
    And the output should contain "2 scenarios, 2 steps (1 passed, 1 errored)"
    And the exit code should be 1

  Scenario: An error in afterStep errors the step it followed, naming the hook
    Given a feature file "features/audit.feature":
      """
      Feature: Audit
        Scenario: Logging
          Given a step
      """
    And a step file "features/steps/audit.steps.php":
      """
      <?php
      given("a step", function () {});
      afterStep(fn () => throw new RuntimeException('log full'));
      """
    When I run phpspec run "features/"
    Then the output should contain "log full (in afterStep)"
    And the output should contain "1 scenario, 1 step (1 errored)"
    And the exit code should be 1

  Scenario: An error in afterStep after a failing step keeps the failure and shows the hook's error as a warning
    Given a feature file "features/audit.feature":
      """
      Feature: Audit
        Scenario: Logging
          Given a failing step
      """
    And a step file "features/steps/audit.steps.php":
      """
      <?php
      given("a failing step", fn () => expect(1)->toBe(2));
      afterStep(fn () => throw new RuntimeException('log full'));
      """
    When I run phpspec run "features/"
    Then the output should contain "Expected 1 to be 2"
    And the output should contain "log full (in afterStep)"
    And the output should contain "1 scenario, 1 step (1 failed)"
    And the exit code should be 1

  Scenario: skip() in afterScenario comes too late, and says so
    Given a feature file "features/late.feature":
      """
      Feature: Late
        Scenario: Ran already
          Given a step
      """
    And a step file "features/steps/late.steps.php":
      """
      <?php
      given("a step", function () {});
      afterScenario(fn () => skip('no service'));
      """
    When I run phpspec run "features/"
    Then the output should contain "skip() in afterScenario comes after the scenario ran; call it in beforeScenario or in a step."
    And the exit code should be 1

  Scenario: An error in afterFeature errors the feature's last step, and the results still print
    Given a feature file "features/shutdown.feature":
      """
      Feature: Shutdown
        Scenario: First
          Given a step
        Scenario: Last
          Given a step
      """
    And a step file "features/steps/shutdown.steps.php":
      """
      <?php
      given("a step", function () {});
      afterFeature(fn () => throw new RuntimeException('server would not stop'));
      """
    When I run phpspec run "features/"
    Then the output should contain "server would not stop (in afterFeature)"
    And the output should contain "2 scenarios, 2 steps (1 passed, 1 errored)"
    And the exit code should be 1
