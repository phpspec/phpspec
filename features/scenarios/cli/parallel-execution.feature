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

  Scenario: A parallel run leaves feature support code to the workers that run the features
    Given a feature file "features/a_first.feature":
      """
      Feature: First
        Scenario: Runs
          Given a step
      """
    And a feature file "features/b_second.feature":
      """
      Feature: Second
        Scenario: Runs
          Given a step
      """
    And a file "features/support/env.php":
      """
      <?php
      file_put_contents(__DIR__ . '/../../support.log', "loaded\n", FILE_APPEND);
      """
    And a step file "features/steps/steps.php":
      """
      <?php
      given('a step', fn () => expect(true)->toBeTrue());
      """
    When I run phpspec run with option "features/ --parallel=2"
    Then all steps should pass
    And the file "support.log" should contain "loaded" exactly 2 times

  Scenario: Each worker knows which one it is, as PHPSPEC_WORKER and TEST_TOKEN
    Given a spec file "spec/App/First.spec.php":
      """
      <?php
      describe('First', function () {
          it('says which worker ran it', function () {
              file_put_contents('workers.log', 'worker ' . getenv('PHPSPEC_WORKER') . ' token ' . getenv('TEST_TOKEN') . "\n", FILE_APPEND);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/Second.spec.php":
      """
      <?php
      describe('Second', function () {
          it('says which worker ran it', function () {
              file_put_contents('workers.log', 'worker ' . getenv('PHPSPEC_WORKER') . ' token ' . getenv('TEST_TOKEN') . "\n", FILE_APPEND);
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--parallel=2"
    Then all examples should pass
    And the file "workers.log" should contain "worker 1 token 1"
    And the file "workers.log" should contain "worker 2 token 2"

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

  Scenario: Workers load the bootstrap the run was given
    Given a file "bootstrap/probe.php":
      """
      <?php
      define('PARALLEL_PROBE_BOOTSTRAPPED', 'yes');
      """
    And a spec file "spec/App/First.spec.php":
      """
      <?php
      describe('First', function () {
          it('sees the bootstrap', fn () => expect(PARALLEL_PROBE_BOOTSTRAPPED)->toBe('yes'));
      });
      """
    And a spec file "spec/App/Second.spec.php":
      """
      <?php
      describe('Second', function () {
          it('sees the bootstrap', fn () => expect(PARALLEL_PROBE_BOOTSTRAPPED)->toBe('yes'));
      });
      """
    When I run phpspec run in a fresh process with option "--parallel=2 -b bootstrap/probe.php"
    Then all examples should pass
    And the output should contain "2 examples (2 passed)"

  Scenario: Workers read the config the run was named
    Given a file "configured/custom.json":
      """
      {"bootstrap": "bootstrap/probe.php"}
      """
    And a file "bootstrap/probe.php":
      """
      <?php
      define('PARALLEL_PROBE_BOOTSTRAPPED', 'yes');
      """
    And a spec file "spec/App/First.spec.php":
      """
      <?php
      describe('First', function () {
          it('sees the bootstrap', fn () => expect(PARALLEL_PROBE_BOOTSTRAPPED)->toBe('yes'));
      });
      """
    And a spec file "spec/App/Second.spec.php":
      """
      <?php
      describe('Second', function () {
          it('sees the bootstrap', fn () => expect(PARALLEL_PROBE_BOOTSTRAPPED)->toBe('yes'));
      });
      """
    When I run phpspec run in a fresh process with option "spec --parallel=2 --config configured/custom.json"
    Then all examples should pass
    And the output should contain "2 examples (2 passed)"

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

  Scenario: Stop on the first error inside a file with parallel execution
    Given a spec file "spec/App/Halting.spec.php":
      """
      <?php
      describe('Halting', function () {
          it('errors', function () {
              throw new RuntimeException('boom');
          });
          it('later example', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--parallel=1 --stop-on-error"
    Then the output should contain "1 example ("
    And the output should not contain "✓"

  Scenario: An error under --parallel is addressed at its spec line, as it is in one process
    Given a spec file "spec/App/Halting.spec.php":
      """
      <?php
      describe('Halting', function () {
          it('errors', function () {
              throw new RuntimeException('boom');
          });
      });
      """
    When I run phpspec run with option "--parallel=1 --format=agent"
    Then the output should be valid JSON
    And the output should contain "RuntimeException"
    And the output should not contain "WorkerProcess"
    And the output should contain "spec/App/Halting.spec.php:4"
    And the output should contain "rerun"

  Scenario: A failure under --parallel carries its expectation and the way to re-run it
    Given a spec file "spec/App/Failing.spec.php":
      """
      <?php
      describe('Failing', function () {
          it('fails', function () {
              expect(1)->toBe(2);
          });
      });
      """
    When I run phpspec run with option "--parallel=1 --format=agent"
    Then the output should be valid JSON
    And the output should contain "toBe"
    And the output should contain "expected"
    And the output should contain "actual"
    And the output should contain "spec/App/Failing.spec.php:4"
    And the output should contain "rerun"

  Scenario: A failure under --parallel shows its values and the code around it
    Given a spec file "spec/App/Failing.spec.php":
      """
      <?php
      describe('Failing', function () {
          it('fails', function () {
              echo 'printed by the example';
              expect(1)->toBe(2);
          });
      });
      """
    When I run phpspec run with option "--parallel=1"
    Then the output should contain "Expected 1 to be 2"
    And the output should contain "expected: 2"
    And the output should contain "got: 1"
    And the output should contain "expect(1)->toBe(2)"
    And the output should contain "Failing.spec.php:5"
    And the output should contain "printed by the example"

  Scenario: Pending and skipped examples stay apart under --parallel, each with its reason
    Given a spec file "spec/App/Waiting.spec.php":
      """
      <?php
      describe('Waiting', function () {
          it('is pending', function () {
              pending('Later');
          });
          it('is skipped', function () {
              skip('No network');
          });
      });
      """
    When I run phpspec run with option "--parallel=1"
    Then the output should contain "1 pending"
    And the output should contain "1 skipped"
    And the output should contain "(Later)"
    And the output should contain "(No network)"

  Scenario: A worker that dies is reported on the file it was running
    Given a spec file "spec/App/Dying.spec.php":
      """
      <?php
      describe('Dying', function () {
          it('takes the worker down', function () {
              exit(3);
          });
      });
      """
    When I run phpspec run with option "--parallel=1"
    Then the output should contain "exited with code 3"
    And the output should contain "Dying.spec.php"
    And the exit code should not be 0

  Scenario: A worker that dies takes its coverage with it, and the run says so rather than report less
    Given a class "src/App/Basket.php":
      """
      <?php

      namespace App;

      class Basket
      {
          public function name(): string
          {
              return 'Basket';
          }
      }
      """
    And a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe(Basket::class, function () {
          it('is named', function () {
              expect((new Basket())->name())->toBe('Basket');
          });
      });
      """
    And a spec file "spec/App/Dying.spec.php":
      """
      <?php
      describe('Dying', function () {
          it('takes the worker down', function () {
              exit(3);
          });
      });
      """
    When I run phpspec run with coverage options "--coverage --parallel=2"
    Then the output should contain "Coverage is incomplete: a worker exited with code 3 before handing over its coverage."
    And the output should not contain "Total:"
    And the exit code should be 1

  Scenario: Workers run under the php settings the run was given
    Given a spec file "spec/App/First.spec.php":
      """
      <?php
      describe('First', function () {
          it('runs under the settings given', function () {
              expect(ini_get('memory_limit'))->toBe('96M');
              expect(ini_get('precision'))->toBe('10');
          });
      });
      """
    And a spec file "spec/App/Second.spec.php":
      """
      <?php
      describe('Second', function () {
          it('runs under the settings given', function () {
              expect(ini_get('memory_limit'))->toBe('96M');
              expect(ini_get('precision'))->toBe('10');
          });
      });
      """
    When I run phpspec run under php "-d memory_limit=96M -d precision=10" with option "--parallel=2"
    Then all examples should pass

  Scenario: Workers load the extensions the run's command line loaded
    Given php loads the Zend extension "opcache" only when its command line asks
    And a spec file "spec/App/First.spec.php":
      """
      <?php
      describe('First', function () {
          it('runs with the extension loaded', function () {
              expect(extension_loaded('Zend OPcache'))->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/Second.spec.php":
      """
      <?php
      describe('Second', function () {
          it('runs with the extension loaded', function () {
              expect(extension_loaded('Zend OPcache'))->toBeTrue();
          });
      });
      """
    When I run phpspec run under php "-d zend_extension=opcache" with option "--parallel=2"
    Then all examples should pass

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

  Scenario: A parallel run keeps the lines its paths select, in a spec and in a feature
    Given a spec file "spec/App/Money.spec.php":
      """
      <?php
      describe('App\Money', function () {
          it('adds', fn() => expect(1 + 1)->toBe(2));
          it('subtracts', fn() => expect(2 - 1)->toBe(1));
          it('multiplies', fn() => expect(2 * 2)->toBe(4));
      });
      """
    And a feature file "features/billing.feature":
      """
      Feature: Billing
        Scenario: First
          Given a billing step

        Scenario: Second
          Given a billing step

        Scenario: Third
          Given a billing step
      """
    And a step file "features/steps/billing.steps.php":
      """
      <?php
      given('a billing step', function () {});
      """
    When I run phpspec run in a fresh process with option "--parallel=2 spec/App/Money.spec.php:4 features/billing.feature:5 --format=agent"
    Then the output should contain:
      """
      "examples":1,"scenarios":1,"steps":1,"passing":2,"failing":0,"errors":0,
      """

  Scenario: A parallel run keeps the example an id selects, and runs a block two selectors name once
    Given a spec file "spec/App/Money.spec.php":
      """
      <?php
      describe('App\Money', function () {
          it('adds', fn() => expect(1 + 1)->toBe(2));
          it('subtracts', fn() => expect(2 - 1)->toBe(1));
          it('multiplies', fn() => expect(2 * 2)->toBe(4));
      });
      """
    When I run phpspec run in a fresh process with option "--parallel=2 spec/App/Money.spec.php@e0953166a73b spec/App/Money.spec.php:4 --format=agent"
    Then the output should contain:
      """
      "examples":1,"scenarios":0,"steps":0,"passing":1,"failing":0,"errors":0,
      """

  Scenario: A parallel run keeps every line it selects in one file, in a spec and in a feature
    Given a spec file "spec/App/Money.spec.php":
      """
      <?php
      describe('App\Money', function () {
          it('adds', fn() => expect(1 + 1)->toBe(2));
          it('subtracts', fn() => expect(2 - 1)->toBe(1));
          it('multiplies', fn() => expect(2 * 2)->toBe(4));
      });
      """
    And a feature file "features/billing.feature":
      """
      Feature: Billing
        Scenario: First
          Given a billing step

        Scenario: Second
          Given a billing step

        Scenario: Third
          Given a billing step
      """
    And a step file "features/steps/billing.steps.php":
      """
      <?php
      given('a billing step', function () {});
      """
    When I run phpspec run in a fresh process with option "--parallel=2 spec/App/Money.spec.php:3 spec/App/Money.spec.php:5 features/billing.feature:2 features/billing.feature:8 --format=agent"
    Then the output should contain:
      """
      "examples":2,"scenarios":2,"steps":2,"passing":4,"failing":0,"errors":0,
      """

  Scenario: A worker that dies after reporting a file run in part is said to have died before the files it never reported, only
    Given a spec file "spec/App/Money.spec.php":
      """
      <?php
      describe('App\Money', function () {
          it('adds', fn() => expect(1 + 1)->toBe(2));
          it('subtracts', fn() => expect(2 - 1)->toBe(1));
          it('multiplies', fn() => expect(2 * 2)->toBe(4));
      });
      """
    And a spec file "spec/App/Crash.spec.php":
      """
      <?php
      describe('App\Crash', function () {
          it('dies', function () {
              exit(1);
          });
      });
      """
    When I run phpspec run in a fresh process with option "--parallel=1 spec/App/Money.spec.php:4 spec/App/Crash.spec.php --format=agent"
    Then the output should contain:
      """
      "passing":1,"failing":0,"errors":1,
      """
    And the output should contain "before reporting spec/App/Crash.spec.php."
    And the output should not contain "before reporting spec/App/Money.spec.php"

  Scenario: A parallel run deals its files in the order its seed gives, as a run in one process orders them
    Given a spec file "spec/App/A.spec.php":
      """
      <?php
      describe('A', function () {
          it('runs', function () {
              file_put_contents(__DIR__ . '/../../order.log', 'A', FILE_APPEND);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/B.spec.php":
      """
      <?php
      describe('B', function () {
          it('runs', function () {
              file_put_contents(__DIR__ . '/../../order.log', 'B', FILE_APPEND);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/C.spec.php":
      """
      <?php
      describe('C', function () {
          it('runs', function () {
              file_put_contents(__DIR__ . '/../../order.log', 'C', FILE_APPEND);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/D.spec.php":
      """
      <?php
      describe('D', function () {
          it('runs', function () {
              file_put_contents(__DIR__ . '/../../order.log', 'D', FILE_APPEND);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/E.spec.php":
      """
      <?php
      describe('E', function () {
          it('runs', function () {
              file_put_contents(__DIR__ . '/../../order.log', 'E', FILE_APPEND);
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run in a fresh process with option "--parallel=1 --order=random --seed=42"
    Then all examples should pass
    And the output should contain "Randomised with seed 42"
    And the file "order.log" should contain "EABDC"
