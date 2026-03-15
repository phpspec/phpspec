Feature: Error reporting
  As a developer
  I want clear and helpful error messages
  So that I can quickly understand and fix failures

  Scenario: Failed expectation shows expected vs actual
    Given a spec file "spec/App/ErrorMessages.spec.php":
      """
      <?php
      describe('ErrorMessages', function () {
          it('shows expected and actual', function () {
              expect('actual')->toBe('expected');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "expected"
    And the output should contain "actual"

  Scenario: Error shows file and line number
    Given a spec file "spec/App/Location.spec.php":
      """
      <?php
      describe('Location', function () {
          it('points to the failing line', function () {
              expect(1)->toBe(2);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Location.spec.php"

  Scenario: Shows surrounding code context
    Given a spec file "spec/App/Context.spec.php":
      """
      <?php
      describe('Context', function () {
          it('shows surrounding code', function () {
              $value = 42;
              expect($value)->toBe(99);
          });
      });
      """
    When I run phpspec run with option "-v"
    Then the output should contain "$value = 42"

  Scenario: Suggests similar matcher names on typo
    Given a spec file "spec/App/Typo.spec.php":
      """
      <?php
      describe('Typo', function () {
          it('suggests the right matcher', function () {
              expect(true)->toBeTru();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "Did you mean"
    And the output should contain "toBeTrue"

  Scenario: Uncaught exception in example is reported as error
    Given a spec file "spec/App/UncaughtError.spec.php":
      """
      <?php
      describe('UncaughtError', function () {
          it('throws unexpectedly', function () {
              throw new \RuntimeException('unexpected error');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "unexpected error"
    And the exit code should be 1

  Scenario: PHP warnings are captured and displayed
    Given a spec file "spec/App/Warnings.spec.php":
      """
      <?php
      describe('Warnings', function () {
          it('triggers a warning', function () {
              @trigger_error('test warning', E_USER_WARNING);
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "warning"
