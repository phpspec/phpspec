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
    Then the output should contain "1 pending"

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
