Feature: Coverage across parallel workers
  As a developer gating a parallel run on coverage
  I want each line judged by what every worker compiled and ran
  So that a line is missed only when it is code nobody ran

  Scenario Outline: A line one worker compiled away and another never ran is not counted as missed
    Given a class "src/App/Status.php":
      """
      <?php

      namespace App;

      final class Status
      {
          const ENABLED = 'enabled';
      }
      """
    And a class "src/App/Repo.php":
      """
      <?php

      namespace App;

      final class Repo
      {
          public function filter(string $id): array
          {
              return $this->with([
                  'id' => $id,
                  'status' => Status::ENABLED,
              ]);
          }

          public function name(): string
          {
              return 'repo';
          }

          private function with(array $parameters): array
          {
              return $parameters;
          }
      }
      """
    And a spec file "spec/App/FilteringSpec.spec.php":
      """
      <?php

      use App\Repo;
      use App\Status;

      describe('Filtering', function () {
          it('filters by status', function () {
              expect(Status::ENABLED)->toBe('enabled');
              expect((new Repo())->filter('x'))->toBe(['id' => 'x', 'status' => 'enabled']);
          });
      });
      """
    And a spec file "spec/App/NamingSpec.spec.php":
      """
      <?php

      use App\Repo;

      describe('Naming', function () {
          it('is named', function () {
              expect((new Repo())->name())->toBe('repo');
          });
      });
      """
    When I run phpspec run <engine> "spec --parallel=2 --coverage-min=100"
    Then the output should contain "Code coverage 100.0% meets the required 100.0%"
    And the exit code should be 0

    Examples:
      | engine                           |
      | with coverage options            |
      | under PCOV with coverage options |

  Scenario: A line in a property hook only one worker ran stays missed when another worker never touched the hook
    Given PHP is at least "8.4"
    And a class "src/App/Label.php":
      """
      <?php

      namespace App;

      final class Label
      {
          public bool $hidden = false;

          public string $text {
              get {
                  if ($this->hidden) {
                      return '';
                  }

                  return 'label';
              }
          }

          public function other(): string
          {
              return 'other';
          }
      }
      """
    And a spec file "spec/App/TextSpec.spec.php":
      """
      <?php

      describe('Text', function () {
          it('reads the label', fn () => expect((new App\Label())->text)->toBe('label'));
      });
      """
    And a spec file "spec/App/OtherSpec.spec.php":
      """
      <?php

      describe('Other', function () {
          it('answers', fn () => expect((new App\Label())->other())->toBe('other'));
      });
      """
    When I run phpspec run with coverage options "spec --parallel=2 --coverage-min=100"
    Then the output should contain "is below the required 100.0%"
    And the exit code should be 1

  Scenario: A line two units share proves nothing about the one a worker never ran
    Given PHP is at least "8.4"
    And a class "src/App/Label.php":
      """
      <?php

      namespace App;

      final class Label
      {
          public bool $hidden = false;

          public function other(): string { return 'other'; } public string $text { get {
              if ($this->hidden) {
                  return '';
              }

              return 'label';
          } }
      }
      """
    And a spec file "spec/App/TextSpec.spec.php":
      """
      <?php

      describe('Text', function () {
          it('reads the label', fn () => expect((new App\Label())->text)->toBe('label'));
      });
      """
    And a spec file "spec/App/OtherSpec.spec.php":
      """
      <?php

      describe('Other', function () {
          it('answers', fn () => expect((new App\Label())->other())->toBe('other'));
      });
      """
    When I run phpspec run with coverage options "spec --parallel=2 --coverage-min=100"
    Then the output should contain "is below the required 100.0%"
    And the exit code should be 1

  Scenario: A file's own code a worker compiled before collecting stays missed where another worker never ran it
    Given a class "src/App/boot.php":
      """
      <?php

      namespace App;

      function boot_name(): string
      {
          return 'boot';
      }

      if (getenv('PHPSPEC_NEVER_SET') !== false) {
          boot_name();
      }
      """
    And a spec file "spec/App/LoadingSpec.spec.php":
      """
      <?php

      describe('Loading', function () {
          it('loads the file', function () {
              require_once __DIR__ . '/../../src/App/boot.php';

              expect(App\boot_name())->toBe('boot');
          });
      });
      """
    And a spec file "spec/App/NamingSpec.spec.php":
      """
      <?php

      require_once __DIR__ . '/../../src/App/boot.php';

      describe('Naming', function () {
          it('is named', fn () => expect(App\boot_name())->toBe('boot'));
      });
      """
    When I run phpspec run with coverage options "spec --parallel=2 --coverage-min=100"
    Then the output should contain "is below the required 100.0%"
    And the exit code should be 1
