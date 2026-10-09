Feature: Pending and focused examples
  As a developer
  I want to skip or focus on specific examples
  So that I can work incrementally and isolate tests

  Scenario: Pending example with xit
    Given a spec file "spec/App/Pending.spec.php":
      """
      <?php
      describe('Pending', function () {
          xit('is not yet implemented', function () {
              expect(true)->toBe(false);
          });

          it('still runs other examples', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "1 pending"
    And the output should contain "1 pass"

  Scenario: Pending example with pending() call
    Given a spec file "spec/App/PendingCall.spec.php":
      """
      <?php
      describe('PendingCall', function () {
          it('marks itself pending at runtime', function () {
              pending('Work in progress');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "○ marks itself pending at runtime (Work in progress)"
    And the output should contain "Pending:"
    And the output should contain "1 pending"

  Scenario: Pending describe block with xdescribe
    Given a spec file "spec/App/PendingGroup.spec.php":
      """
      <?php
      xdescribe('PendingGroup', function () {
          it('skips this', function () {
              expect(true)->toBe(false);
          });

          it('and this too', function () {
              expect(true)->toBe(false);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "2 pending"

  Scenario: Focused example with fit runs only that example
    Given a spec file "spec/App/Focused.spec.php":
      """
      <?php
      describe('Focused', function () {
          fit('runs this one', function () {
              expect(1)->toBe(1);
          });

          it('skips this one', function () {
              expect(true)->toBe(false);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
    And the output should contain "1 pass"

  Scenario: Focused describe block with fdescribe
    Given a spec file "spec/App/FocusedGroup.spec.php":
      """
      <?php
      describe('FocusedGroup', function () {
          fdescribe('Focused', function () {
              it('runs inside focused describe', function () {
                  expect(1)->toBe(1);
              });
          });

          it('does not run', function () {
              expect(true)->toBe(false);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: A skipped example shows the reason it gave
    Given a spec file "spec/App/Offline.spec.php":
      """
      <?php
      describe('Offline', function () {
          it('fetches the rates', function () {
              skip('No network on this machine');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "- fetches the rates (No network on this machine)"
    And the output should contain "Skipped:"
    And the output should contain "1 skipped"
    And the exit code should be 0

  Scenario: An example with no body yet is pending
    Given a spec file "spec/App/Unwritten.spec.php":
      """
      <?php
      describe('Unwritten', function () {
          it('totals the prices');

          it('starts empty', function () {
              expect([])->toBe([]);
          });
      });
      """
    When I run phpspec run
    Then the output should contain "○ totals the prices"
    And the output should contain "1 pending"
    And the output should contain "1 pass"
    And the output should not contain "ArgumentCountError"
    And the exit code should be 0

  Scenario: An example with no body is the one a line target reaches
    Given a spec file "spec/App/UnwrittenTarget.spec.php":
      """
      <?php
      describe('UnwrittenTarget', function () {
          it('totals the prices');

          it('starts empty', function () {
              expect([])->toBe([]);
          });
      });
      """
    When I run phpspec run "spec/App/UnwrittenTarget.spec.php:3"
    Then the output should contain "1 pending"
    And the output should not contain "starts empty"
