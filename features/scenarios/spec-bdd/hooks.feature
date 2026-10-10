Feature: Lifecycle hooks
  As a developer
  I want setup and teardown hooks around my examples
  So that I can manage test state cleanly

  Scenario: beforeEach runs before every example
    Given a spec file "spec/App/Hooks.spec.php":
      """
      <?php
      describe('Hooks', function () {
          beforeEach(function () {
              $this->counter = ($this->counter ?? 0) + 1;
          });

          it('runs beforeEach once', function () {
              expect($this->counter)->toBe(1);
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: afterEach runs after every example
    Given a spec file "spec/App/AfterHooks.spec.php":
      """
      <?php
      describe('AfterHooks', function () {
          let('log', fn() => []);

          afterEach(function () {
              $this->log[] = 'cleaned';
          });

          it('first example', function () {
              expect(true)->toBeTrue();
          });

          it('second example', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: beforeAll runs once for the context
    Given a spec file "spec/App/BeforeAll.spec.php":
      """
      <?php
      describe('BeforeAll', function () {
          beforeAll(function () {
              $this->setup = 'done';
          });

          it('has access to beforeAll state', function () {
              expect($this->setup)->toBe('done');
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Nested hooks follow context hierarchy
    Given a spec file "spec/App/NestedHooks.spec.php":
      """
      <?php
      describe('NestedHooks', function () {
          let('trace', fn() => []);

          beforeEach(function () {
              $this->trace[] = 'outer';
          });

          context('inner', function () {
              beforeEach(function () {
                  $this->trace[] = 'inner';
              });

              it('runs outer then inner hooks', function () {
                  expect($this->trace)->toContain('outer');
                  expect($this->trace)->toContain('inner');
              });
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: skip() in beforeEach skips each example with its reason, and afterEach still cleans up
    Given a spec file "spec/App/Service.spec.php":
      """
      <?php
      describe('Service', function () {
          beforeEach(fn () => skip('no service'));
          afterEach(fn () => file_put_contents('cleaned.txt', 'yes'));

          it('answers', fn () => expect(1)->toBe(2));
          it('answers again', fn () => expect(1)->toBe(2));
      });
      """
    When I run phpspec run
    Then the output should contain "- answers (no service)"
    And the output should contain "2 examples (2 skipped)"
    And a file "cleaned.txt" should be generated
    And the exit code should be 0

  Scenario: pending() in beforeEach leaves each example pending with its reason
    Given a spec file "spec/App/Later.spec.php":
      """
      <?php
      describe('Later', function () {
          beforeEach(fn () => pending('needs the gateway'));

          it('pays', fn () => expect(1)->toBe(2));
      });
      """
    When I run phpspec run
    Then the output should contain "○ pays (needs the gateway)"
    And the output should contain "1 example (1 pending)"
    And the exit code should be 0

  Scenario: skip() in beforeAll skips every example of the context, nested ones too, and afterAll still runs
    Given a spec file "spec/App/Remote.spec.php":
      """
      <?php
      describe('Remote', function () {
          beforeAll(fn () => skip('no network'));
          afterAll(fn () => file_put_contents('closed.txt', 'yes'));

          it('fetches', fn () => expect(1)->toBe(2));

          context('when cached', function () {
              it('reads the cache', fn () => expect(1)->toBe(2));
          });
      });
      """
    When I run phpspec run
    Then the output should contain "- fetches (no network)"
    And the output should contain "- reads the cache (no network)"
    And the output should contain "2 examples (2 skipped)"
    And a file "closed.txt" should be generated
    And the exit code should be 0

  Scenario: skip() in afterEach comes too late, and says so
    Given a spec file "spec/App/Late.spec.php":
      """
      <?php
      describe('Late', function () {
          afterEach(fn () => skip('no service'));

          it('ran already', fn () => expect(1)->toBe(1));
      });
      """
    When I run phpspec run
    Then the output should contain "skip() in afterEach comes after the example ran; call it in beforeEach or in the example."
    And the exit code should be 1

  Scenario: pending() in afterAll comes too late, and says so
    Given a spec file "spec/App/LateAll.spec.php":
      """
      <?php
      describe('LateAll', function () {
          afterAll(fn () => pending('later'));

          it('ran already', fn () => expect(1)->toBe(1));
      });
      """
    When I run phpspec run
    Then the output should contain "pending() in afterAll comes after the examples ran; call it in beforeAll or in an example."
    And the exit code should be 1
