Feature: CLI options
  As a developer
  I want control over how specs are run and reported
  So that I can adapt PhpSpec to my workflow

  Background:
    Given a PSR-4 project with "spec" and "src" directories

  Scenario: Stop on first failure
    Given a spec file "spec/App/AAFirstFail.spec.php":
      """
      <?php
      describe('AAFirstFail', function () {
          it('fails', function () {
              expect(1)->toBe(2);
          });
      });
      """
    And a spec file "spec/App/ZZSecond.spec.php":
      """
      <?php
      describe('ZZSecond', function () {
          it('should not run', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--stop-on-failure"
    Then 1 example should fail
    And the output should not contain "ZZSecond"

  Scenario: Filter specs by file path
    Given a spec file "spec/App/Targeted.spec.php":
      """
      <?php
      describe('Targeted', function () {
          it('runs this spec', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/Excluded.spec.php":
      """
      <?php
      describe('Excluded', function () {
          it('should not run', function () {
              expect(true)->toBe(false);
          });
      });
      """
    When I run phpspec run with option "--filter Targeted"
    Then all examples should pass
    And the output should not contain "Excluded"

  Scenario: Dot formatter
    Given a spec file "spec/App/DotFormat.spec.php":
      """
      <?php
      describe('DotFormat', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--format dot"
    Then the output should contain "."
    And all examples should pass

  Scenario: TAP formatter
    Given a spec file "spec/App/TapFormat.spec.php":
      """
      <?php
      describe('TapFormat', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--format tap"
    Then the output should contain "TAP version 13"
    And the output should contain "ok 1"

  Scenario: JUnit XML formatter
    Given a spec file "spec/App/JunitFormat.spec.php":
      """
      <?php
      describe('JunitFormat', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--format junit"
    Then the output should contain "<testsuites>"
    And the output should contain "<testcase"

  Scenario: Random execution order with seed
    Given a spec file "spec/App/Random.spec.php":
      """
      <?php
      describe('Random', function () {
          it('example one', function () { expect(true)->toBeTrue(); });
          it('example two', function () { expect(true)->toBeTrue(); });
          it('example three', function () { expect(true)->toBeTrue(); });
      });
      """
    When I run phpspec run with option "--order random --seed 42"
    Then all examples should pass
    And the output should contain "seed 42"

  Scenario: Profile slowest examples
    Given a spec file "spec/App/Profile.spec.php":
      """
      <?php
      describe('Profile', function () {
          it('runs quickly', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--profile"
    Then the output should contain "slowest examples"

  Scenario: Bootstrap file
    Given a file "bootstrap.php":
      """
      <?php
      define('BOOTSTRAPPED', true);
      """
    And a spec file "spec/App/Bootstrap.spec.php":
      """
      <?php
      describe('Bootstrap', function () {
          it('has access to bootstrapped constants', function () {
              expect(defined('BOOTSTRAPPED'))->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--bootstrap bootstrap.php"
    Then all examples should pass

  Scenario: Skip examples with skip()
    Given a spec file "spec/App/Skippy.spec.php":
      """
      <?php
      describe('Skippy', function () {
          it('is skipped', function () {
              skip('Not relevant on this platform');
          });
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--format dot"
    Then the output should contain "S"
    And the output should contain "1 skipped"
    And the exit code should be 0

  Scenario: Stop on problems stops on any non-pass result
    Given a spec file "spec/App/AAStopProblems.spec.php":
      """
      <?php
      describe('AAStopProblems', function () {
          it('is skipped', function () {
              skip('Irrelevant');
          });
      });
      """
    And a spec file "spec/App/ZZShouldNotRun.spec.php":
      """
      <?php
      describe('ZZShouldNotRun', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--stop-on-problems"
    Then the output should not contain "ZZShouldNotRun"

  Scenario: Stop on first error
    Given a spec file "spec/App/AAStopError.spec.php":
      """
      <?php
      describe('AAStopError', function () {
          it('throws an error', function () {
              throw new \RuntimeException('boom');
          });
      });
      """
    And a spec file "spec/App/ZZAfterError.spec.php":
      """
      <?php
      describe('ZZAfterError', function () {
          it('should not run', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--stop-on-error"
    Then the output should not contain "ZZAfterError"

  Scenario: Stop on first warning
    Given a spec file "spec/App/AAStopWarning.spec.php":
      """
      <?php
      describe('AAStopWarning', function () {
          it('triggers a warning', function () {
              trigger_error('something fishy', E_USER_WARNING);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/ZZAfterWarning.spec.php":
      """
      <?php
      describe('ZZAfterWarning', function () {
          it('should not run', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--stop-on-warning"
    Then the output should not contain "ZZAfterWarning"

  Scenario: Stop on first deprecation
    Given a spec file "spec/App/AAStopDeprecation.spec.php":
      """
      <?php
      describe('AAStopDeprecation', function () {
          it('triggers a deprecation', function () {
              trigger_error('old API', E_USER_DEPRECATED);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/ZZAfterDeprecation.spec.php":
      """
      <?php
      describe('ZZAfterDeprecation', function () {
          it('should not run', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--stop-on-deprecation"
    Then the output should not contain "ZZAfterDeprecation"

  Scenario: Stop on first notice
    Given a spec file "spec/App/AAStopNotice.spec.php":
      """
      <?php
      describe('AAStopNotice', function () {
          it('triggers a notice', function () {
              trigger_error('FYI', E_USER_NOTICE);
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/ZZAfterNotice.spec.php":
      """
      <?php
      describe('ZZAfterNotice', function () {
          it('should not run', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--stop-on-notice"
    Then the output should not contain "ZZAfterNotice"

  Scenario: Stop on first skipped example
    Given a spec file "spec/App/AAStopSkipped.spec.php":
      """
      <?php
      describe('AAStopSkipped', function () {
          it('is skipped', function () {
              skip('Not applicable');
          });
      });
      """
    And a spec file "spec/App/ZZAfterSkipped.spec.php":
      """
      <?php
      describe('ZZAfterSkipped', function () {
          it('should not run', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--stop-on-skipped"
    Then the output should not contain "ZZAfterSkipped"

  Scenario: Quiet mode suppresses output
    Given a spec file "spec/App/Quiet.spec.php":
      """
      <?php
      describe('Quiet', function () {
          it('passes silently', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "-q"
    Then the exit code should be 0

  Scenario: Run all suites with --all flag
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a spec file "spec/App/AllSpec.spec.php":
      """
      <?php
      describe('AllSpec', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a feature file "features/all.feature":
      """
      Feature: All suites
        Scenario: Basic
          Given something true
      """
    And a step file "features/steps/all.steps.php":
      """
      <?php
      given('something true', function () {
          expect(true)->toBeTrue();
      });
      """
    When I run phpspec run with option "--all"
    Then the exit code should be 0
    And the output should contain "1 example"
    And the output should contain "1 scenario"

  Scenario: Run only features with --story flag
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a spec file "spec/App/StoryOnly.spec.php":
      """
      <?php
      describe('StoryOnly', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a feature file "features/story.feature":
      """
      Feature: Story only
        Scenario: Basic
          Given something story
      """
    And a step file "features/steps/story.steps.php":
      """
      <?php
      given('something story', function () {
          expect(true)->toBeTrue();
      });
      """
    When I run phpspec run with option "--story"
    Then the exit code should be 0
    And the output should contain "1 scenario"
    And the output should not contain "example"

  Scenario: Run only the specs listed in a paths file
    Given a spec file "spec/App/Listed.spec.php":
      """
      <?php
      describe('Listed', function () {
          it('runs from the paths file', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/AlsoListed.spec.php":
      """
      <?php
      describe('AlsoListed', function () {
          it('also runs from the paths file', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "spec/App/Unlisted.spec.php":
      """
      <?php
      describe('Unlisted', function () {
          it('must not run', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a file "paths.txt":
      """
      spec/App/Listed.spec.php
      spec/App/AlsoListed.spec.php
      """
    When I run phpspec run with option "--paths-from=paths.txt"
    Then the exit code should be 0
    And the output should contain "Listed"
    And the output should contain "AlsoListed"
    And the output should not contain "Unlisted"

  Scenario: Error when the paths file does not exist
    When I run phpspec run with option "--paths-from=missing.txt"
    Then the exit code should be 1
    And the output should contain "missing.txt"
