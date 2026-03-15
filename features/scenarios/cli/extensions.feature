Feature: Extensions
  As a developer
  I want to extend PhpSpec with custom formatters, matchers, commands, and listeners
  So that I can customise the framework for my team's needs

  Scenario: Custom matcher extension
    Given a phpspec.yaml config:
      """
      extensions:
        matchers:
          - App\ValidJsonMatcher
      autoload:
        App\: src/App
      """
    And a class "src/App/ValidJsonMatcher.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\MatcherExtension;

      class ValidJsonMatcher extends MatcherExtension
      {
          public function getName(): string
          {
              return 'toBeValidJson';
          }

          public function match(mixed $actual, mixed ...$args): bool
          {
              if (!is_string($actual)) {
                  return false;
              }
              json_decode($actual);
              return json_last_error() === JSON_ERROR_NONE;
          }

          public function failureMessage(mixed $actual): string
          {
              return 'Expected valid JSON';
          }
      }
      """
    And a spec file "spec/App/JsonTest.spec.php":
      """
      <?php
      describe('JsonTest', function () {
          it('validates JSON with custom matcher', function () {
              expect('{"name": "phpspec"}')->toBeValidJson();
          });

          it('rejects invalid JSON', function () {
              expect('not json')->not()->toBeValidJson();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
    And the output should contain "2 examples"

  Scenario: Custom formatter extension
    Given a phpspec.yaml config:
      """
      extensions:
        formatters:
          - App\MinimalFormatter
      autoload:
        App\: src/App
      """
    And a class "src/App/MinimalFormatter.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\FormatterExtension;

      class MinimalFormatter extends FormatterExtension
      {
          public function getName(): string
          {
              return 'minimal';
          }

          public function formatPass(string $title): string
          {
              return "OK: $title";
          }

          public function formatFail(string $title, string $message): string
          {
              return "FAIL: $title - $message";
          }
      }
      """
    And a spec file "spec/App/FmtTest.spec.php":
      """
      <?php
      describe('FmtTest', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run with option "--format minimal"
    Then the output should contain "OK: passes"
    And the exit code should be 0

  Scenario: Custom listener extension
    Given a phpspec.yaml config:
      """
      extensions:
        listeners:
          - App\FileWriterListener
      autoload:
        App\: src/App
      """
    And a class "src/App/FileWriterListener.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\ListenerExtension;

      class FileWriterListener extends ListenerExtension
      {
          public function afterSuite(): void
          {
              file_put_contents('listener_output.txt', 'suite finished');
          }
      }
      """
    And a spec file "spec/App/ListenerTest.spec.php":
      """
      <?php
      describe('ListenerTest', function () {
          it('passes', function () {
              expect(true)->toBeTrue();
          });
      });
      """
    When I run phpspec run
    Then all examples should pass
    And the file "listener_output.txt" should contain "suite finished"

  Scenario: Custom command extension
    Given a phpspec.yaml config:
      """
      extensions:
        commands:
          - App\GreetCommand
      autoload:
        App\: src/App
      """
    And a class "src/App/GreetCommand.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\CommandExtension;
      use Symfony\Component\Console\Input\InputInterface;
      use Symfony\Component\Console\Output\OutputInterface;

      class GreetCommand extends CommandExtension
      {
          public function getName(): string
          {
              return 'greet';
          }

          public function getDescription(): string
          {
              return 'Say hello';
          }

          public function execute(InputInterface $input, OutputInterface $output): int
          {
              $output->writeln('Hello from extension!');
              return 0;
          }
      }
      """
    When I run phpspec command "greet"
    Then the output should contain "Hello from extension!"
    And the exit code should be 0
