Feature: Parallel execution
  As a developer
  I want to run my specs in parallel
  So that my test suite finishes faster

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: Running specs in parallel with default worker count
    Given a spec file "spec/App/First.spec.php":
      """
      <?php
      describe('First', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/Second.spec.php":
      """
      <?php
      describe('Second', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--parallel"
    Then all examples should pass

  Scenario: Explicit worker count
    Given a spec file "spec/App/WorkerCount.spec.php":
      """
      <?php
      describe('WorkerCount', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--parallel=2"
    Then all examples should pass

  Scenario: A path given after --parallel is still the path to run
    Given a spec file "spec/App/Swallowed.spec.php":
      """
      <?php
      describe('Swallowed', function () {
          it('runs', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--parallel spec/App/Swallowed.spec.php"
    Then all examples should pass
    And the output should contain "1 example"

  Scenario: Stop on failure with parallel execution
    Given a spec file "spec/App/Failing.spec.php":
      """
      <?php
      describe('Failing', function () {
          it('fails', function () {
              expect(1)->toBe(2);
          });
      });
      """
    And a spec file "spec/App/Passing.spec.php":
      """
      <?php
      describe('Passing', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--parallel=1 --stop-on-failure"
    Then the exit code should not be 0

  Scenario: Parallel with profile does not crash
    Given a spec file "spec/App/Slow.spec.php":
      """
      <?php
      describe('Slow', function () {
          it('takes time', function () {
              usleep(10000);
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--parallel --profile"
    Then all examples should pass
