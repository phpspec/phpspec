Feature: AI-powered refactoring
  As a developer
  I want phpspec to refactor my code using AI
  So that I can improve code quality without changing behaviour

  Scenario: Refuse when specs are failing
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    And a spec file "spec/App/Broken.spec.php":
      """
      <?php
      use App\Broken;

      describe(Broken::class, function () {
          it('fails', function () {
              expect(true)->toBeFalse();
          });
      });
      """
    And a class "src/App/Broken.php":
      """
      <?php
      namespace App;

      class Broken {}
      """
    When I run phpspec refactor "App\Broken"
    Then the exit code should not be 0
    And the output should contain "Specs must pass before refactoring"

  Scenario: The baseline runs the installed phpspec, wherever it lives
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    And a spec file "spec/App/Solid.spec.php":
      """
      <?php
      use App\Solid;

      describe(Solid::class, function () {
          it('works', function () {
              expect(new Solid())->toBeAnInstanceOf(Solid::class);
          });
      });
      """
    And a class "src/App/Solid.php":
      """
      <?php
      namespace App;

      class Solid {}
      """
    When I run phpspec refactor "App\Solid"
    Then the output should not contain "Could not open input file"
    And the output should not contain "Specs must pass"

  Scenario: Require AI configuration
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    And a spec file "spec/App/Simple.spec.php":
      """
      <?php
      use App\Simple;

      describe(Simple::class, function () {
          it('works', function () {
              expect(new Simple())->toBeAnInstanceOf(Simple::class);
          });
      });
      """
    And a class "src/App/Simple.php":
      """
      <?php
      namespace App;

      class Simple {}
      """
    When I run phpspec refactor "App\Simple"
    Then the exit code should not be 0
    And the output should contain "AI configuration required"

  Scenario: Resolve target from spec file
    Given no phpspec.json config
    And a phpspec.yaml config:
      """
      spec_path: spec
      """
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php
      use App\Calc;

      describe(Calc::class, function () {
          it('works', function () {
              expect(new Calc())->toBeAnInstanceOf(Calc::class);
          });
      });
      """
    And a class "src/App/Calc.php":
      """
      <?php
      namespace App;

      class Calc {}
      """
    When I run phpspec refactor "spec/App/Calc.spec.php"
    Then the exit code should not be 0
    And the output should contain "AI configuration required"

  Scenario: With no argument, refactor takes the source modified last, and refuses one with no spec
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    And a spec file "spec/App/Basket.spec.php":
      """
      <?php
      use App\Basket;

      describe(Basket::class, function () {
          it('works', function () {
              expect(new Basket())->toBeAnInstanceOf(Basket::class);
          });
      });
      """
    And a class "src/App/Basket.php":
      """
      <?php
      namespace App;

      class Basket {}
      """
    And a class "src/App/Till.php":
      """
      <?php
      namespace App;

      class Till {}
      """
    And "src/App/Till.php" was modified last
    When I run phpspec command "refactor"
    Then the exit code should be 1
    And the output should contain "App\Till has no spec, so nothing would catch a refactoring that broke it. Describe it first: phpspec describe App\Till"
    And the output should not contain "Basket"

  Scenario: With no argument, refactor checks the spec of the source modified last
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    And a spec file "spec/App/Basket.spec.php":
      """
      <?php
      use App\Basket;

      describe(Basket::class, function () {
          it('works', function () {
              expect(new Basket())->toBeAnInstanceOf(Basket::class);
          });
      });
      """
    And a class "src/App/Basket.php":
      """
      <?php
      namespace App;

      class Basket {}
      """
    And a spec file "spec/App/Till.spec.php":
      """
      <?php
      use App\Till;

      describe(Till::class, function () {
          it('rings up', function () {
              expect(true)->toBeFalse();
          });
      });
      """
    And a class "src/App/Till.php":
      """
      <?php
      namespace App;

      class Till {}
      """
    And "src/App/Till.php" was modified last
    When I run phpspec command "refactor"
    Then the exit code should be 1
    And the output should contain "Checking the specs..."
    And the output should contain "Specs must pass before refactoring"
    And the output should contain "rings up"
    And the output should not contain "Refactoring App\Till"

  Scenario: With no argument and no source, refactor says there is none
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    When I run phpspec command "refactor"
    Then the exit code should be 1
    And the output should contain "No source to refactor under src."

  Scenario: A short name takes the class by that name modified last under src_path
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    And a spec file "spec/App/Till.spec.php":
      """
      <?php
      use App\Till;

      describe(Till::class, function () {
          it('works', function () {
              expect(new Till())->toBeAnInstanceOf(Till::class);
          });
      });
      """
    And a class "src/App/Till.php":
      """
      <?php
      namespace App;

      class Till {}
      """
    And a class "src/App/Legacy/Till.php":
      """
      <?php
      namespace App\Legacy;

      class Till {}
      """
    And "src/App/Legacy/Till.php" was modified last
    When I run phpspec command "refactor Till"
    Then the exit code should be 1
    And the output should contain "App\Legacy\Till has no spec, so nothing would catch a refactoring that broke it. Describe it first: phpspec describe App\Legacy\Till"

  Scenario: A short name that names no class under src_path is refused
    Given a phpspec.yaml config:
      """
      ai:
        provider: google
        api_key: test-key-123
      """
    When I run phpspec command "refactor Nope"
    Then the exit code should be 1
    And the output should contain "No class named Nope under src. Describe it first: phpspec describe Nope"
