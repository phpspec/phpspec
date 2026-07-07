Feature: Configuration
  As a developer
  I want to configure PhpSpec via a config file
  So that my team shares the same settings

  Scenario: Configure spec and source paths
    Given a phpspec.json config:
      """
      {
          "spec_path": "tests",
          "src_path": "lib"
      }
      """
    And a spec file "tests/App/Example.spec.php":
      """
      <?php
      describe('Example', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Configure default formatter
    Given a phpspec.json config:
      """
      {
          "format": "dot"
      }
      """
    And a spec file "spec/App/DotDefault.spec.php":
      """
      <?php
      describe('DotDefault', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "."
    And all examples should pass

  Scenario: Configure stop on failure
    Given a phpspec.json config:
      """
      {
          "stop_on_failure": true
      }
      """
    And a spec file "spec/App/ConfigStop.spec.php":
      """
      <?php
      describe('ConfigStop', function () {
          it('fails', function () {
              expect(1)->toBe(2);
          });

          it('is skipped', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then 1 example should fail

  Scenario: Configure bootstrap file
    Given a phpspec.json config:
      """
      {
          "bootstrap": "bootstrap.php"
      }
      """
    And a file "bootstrap.php":
      """
      <?php
      define('CONFIG_BOOTSTRAPPED', true);
      """
    And a spec file "spec/App/ConfigBoot.spec.php":
      """
      <?php
      describe('ConfigBoot', function () {
          it('loads bootstrap', function () {
              expect(defined('CONFIG_BOOTSTRAPPED'))->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Configure PSR-4 autoloading
    Given a phpspec.json config:
      """
      {
          "autoload": {
              "Acme\\": "lib/Acme"
          }
      }
      """
    And a class "lib/Acme/Widget.php":
      """
      <?php
      namespace Acme;

      class Widget {
          public function name(): string { return 'widget'; }
      }
      """
    And a spec file "spec/Acme/Widget.spec.php":
      """
      <?php
      use Acme\Widget;

      describe(Widget::class, function () {
          it('has a name', function () {
              expect((new Widget())->name())->toBe('widget');
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Auto-detects vendor/autoload.php
    Given a PSR-4 project with Composer autoloading
    And a spec file "spec/App/AutoDetect.spec.php":
      """
      <?php
      describe('AutoDetect', function () {
          it('loads Composer autoloader automatically', function () {
              expect(class_exists('Symfony\Component\Console\Application'))->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Configure via phpspec.yaml
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: tests
      """
    And a spec file "tests/App/YamlConfig.spec.php":
      """
      <?php
      describe('YamlConfig', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: YAML config takes priority over JSON
    Given a phpspec.json config:
      """
      {
          "spec_path": "json_specs"
      }
      """
    And a phpspec.yaml config:
      """
      spec_path: yaml_specs
      """
    And a spec file "yaml_specs/App/Priority.spec.php":
      """
      <?php
      describe('Priority', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Named suites combine spec paths
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      suites:
        unit:
          paths:
            - unit_specs
        integration:
          paths:
            - integration_specs
      """
    And a spec file "unit_specs/App/Unit.spec.php":
      """
      <?php
      describe('Unit', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    And a spec file "integration_specs/App/Integration.spec.php":
      """
      <?php
      describe('Integration', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
    And the output should contain "2 examples"

  Scenario: Configure stop_on_error in config
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      stop_on_error: true
      """
    And a spec file "spec/App/ConfigStopError.spec.php":
      """
      <?php
      describe('ConfigStopError', function () {
          it('errors', function () {
              throw new \RuntimeException('boom');
          });
      });
      """
    And a spec file "spec/App/ConfigStopWouldPass.spec.php":
      """
      <?php
      describe('ConfigStopWouldPass', function () {
          it('would pass', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then the output should contain "1 example"

  Scenario: Flat spec_path still works in YAML
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: my_specs
      src_path: my_src
      """
    And a spec file "my_specs/App/FlatYaml.spec.php":
      """
      <?php
      describe('FlatYaml', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Configure spec file suffix
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      spec_suffix: Spec.php
      """
    And a spec file "spec/App/GreeterSpec.php":
      """
      <?php
      describe('Greeter', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass

  Scenario: Load configuration from an explicit path
    Given a file "custom/my-config.json":
      """
      {
          "format": "dot"
      }
      """
    And a spec file "spec/App/ExplicitConfig.spec.php":
      """
      <?php
      describe('ExplicitConfig', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--config=custom/my-config.json"
    Then the output should contain "."
    And all examples should pass

  Scenario: Error when the explicit config file does not exist
    When I run phpspec run with option "--config=nope.yaml"
    Then the exit code should not be 0
    And the output should contain "nope.yaml"

  Scenario: Configure a steps directory outside the features folder
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a phpspec.json config:
      """
      {
          "steps_path": "acceptance_steps"
      }
      """
    And a feature file "features/checkout.feature":
      """
      Feature: Checkout
        Scenario: Buys an item
          Given a configured step
      """
    And a file "acceptance_steps/checkout.steps.php":
      """
      <?php
      given("a configured step", function () {
          expect(true)->toBeTrue();
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass
    And the output should not contain "undefined"
