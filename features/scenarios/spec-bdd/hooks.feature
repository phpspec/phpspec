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
