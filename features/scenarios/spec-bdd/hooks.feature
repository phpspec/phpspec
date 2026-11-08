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
          this('log', fn() => []);

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
          this('trace', fn() => []);

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
    And the output should contain "2 examples (1 passed, 1 errored)"
    And the exit code should be 1

  Scenario: An afterAll that throws keeps the examples that ran
    Given a spec file "spec/App/Server.spec.php":
      """
      <?php
      describe('Server', function () {
          afterAll(function () {
              throw new RuntimeException('would not stop');
          });

          it('answers', fn () => expect(1)->toBe(1));
          it('pages', fn () => expect(1)->toBe(2));

          context('when busy', function () {
              it('queues', fn () => expect(1)->toBe(1));
          });
      });
      """
    When I run phpspec run
    Then the output should contain "✓ answers"
    And the output should contain "✓ queues"
    And the output should contain "would not stop"
    And the output should contain "4 examples (2 passed, 1 failed, 1 errored)"
    And the exit code should be 1

  Scenario: The detail lists what ran before a context broke, and what the context handed over
    Given a spec file "spec/App/Server.spec.php":
      """
      <?php

      use function PhpSpec\attach;

      describe('Server', function () {
          beforeAll(fn () => attach('server.log', 'booted on :8080'));
          afterAll(function () {
              throw new RuntimeException('would not stop');
          });

          it('pages', fn () => expect(1)->toBe(2));
      });
      """
    When I run phpspec run
    Then the output should contain "• Server > pages"
    And the output should contain "at spec/App/Server.spec.php:11"
    And the output should contain "RuntimeException: would not stop"
    And the output should contain "at spec/App/Server.spec.php:8"
    And the output should contain "booted on :8080"
    And the output should not contain "Server > Server"
    And the exit code should be 1

  Scenario: A function PhpSpec calls with no arguments is refused when it declares one
    Given a spec file "spec/App/Vault.spec.php":
      """
      <?php
      describe('Vault', function () {
          beforeAll(function ($log) {});

          it('opens', fn () => expect(1)->toBe(1));
      });

      describe('Box', function () {
          context('when locked', function ($lock) {});

          it('opens', fn () => expect(1)->toBe(1));
      });
      """
    When I run phpspec run
    Then the output should contain "✘ Vault"
    And the output should contain "✘ when locked"
    And the output should contain "Arguments not allowed in this function, only this(), it(), beforeEach() and afterEach() can take doubles."
    And the output should contain "✓ opens"
    And the exit code should be 1
