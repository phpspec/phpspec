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

  Scenario: A mistyped config key stops the run, naming the file and the nearest key
    Given a phpspec.yaml config:
      """
      formatt: dot
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain "phpspec.yaml: unknown key"
    And the output should contain "Did you mean"
    And the output should contain "format"
    And the output should not contain "In Configuration.php"

  Scenario: A wrong-typed config value stops the run, naming what the key expects
    Given a phpspec.yaml config:
      """
      stop_on_failure: maybe
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain "stop_on_failure expects true or false"

  Scenario: A config file that cannot be parsed is named
    Given a phpspec.yaml config:
      """
      formatt: [
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain "phpspec.yaml could not be read: "
    And the output should not contain "In Parser.php"

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

  Scenario: An autoload map beside composer.json stops the run
    Given a file "composer.json":
      """
      {"autoload": {"psr-4": {"Roastery\\": "src/"}}}
      """
    And a phpspec.yaml config:
      """
      autoload:
        Tools\: tools
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain "phpspec.yaml: autoload cannot be set in a project with a composer.json."

  Scenario: A source path composer.json maps no namespace to stops the run, naming the directories it maps
    Given a file "composer.json":
      """
      {"autoload": {"psr-4": {"Roastery\\": "src/", "Tools\\": ["tools/", "lib/tools/"]}}}
      """
    And a phpspec.yaml config:
      """
      src_path: lib
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain:
      """
      phpspec.yaml: src_path "lib" is not a directory composer.json maps a namespace to: src, tools, lib/tools.
      """

  Scenario: A default namespace whose classes cannot be written under the source path stops describe before it writes
    Given a file "composer.json":
      """
      {"autoload": {"psr-4": {"Roastery\\": "src/"}}}
      """
    And a phpspec.yaml config:
      """
      src_path: src
      default_namespace: Roaster
      """
    When I run phpspec describe "Domain/Cup" with option "--format=agent"
    Then the exit code should be 1
    And the output should contain:
      """
      phpspec.yaml: default_namespace \"Roaster\" is under no namespace composer.json maps, and src/ cannot take its classes: Composer maps src/ to Roastery\\.
      """
    And no file "spec/Roaster/Domain/Cup.spec.php" should be generated

  Scenario: A psr4_prefix left from an earlier release stops the run
    Given a phpspec.yaml config:
      """
      src_path: src
      psr4_prefix: App\
      """
    When I run phpspec run
    Then the exit code should be 1
    And the output should contain "phpspec.yaml: psr4_prefix is not a setting: composer.json maps namespaces to directories, or autoload does in a project without one."

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
