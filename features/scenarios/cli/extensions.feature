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
      use PhpSpec\Result\Outcome;

      class MinimalFormatter extends FormatterExtension
      {
          public function getName(): string
          {
              return 'minimal';
          }

          public function formatPass(Outcome $outcome): string
          {
              return "OK: {$outcome->name()}";
          }

          public function formatFail(Outcome $outcome): string
          {
              return "FAIL: {$outcome->name()} - {$outcome->message}";
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
    Then the output should contain "OK: FmtTest > passes"
    And the output should contain "1 example (1 passed)"
    And the exit code should be 0

  Scenario: A formatter extension hears each scenario of a story run, by its feature
    Given a phpspec.yaml config:
      """
      extensions:
        formatters:
          - App\StoryFormatter
      autoload:
        App\: src/App
      """
    And a class "src/App/StoryFormatter.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\FormatterExtension;
      use PhpSpec\Result\Outcome;

      class StoryFormatter extends FormatterExtension
      {
          public function getName(): string
          {
              return 'stories';
          }

          public function formatPass(Outcome $outcome): string
          {
              return "OK: {$outcome->name()}";
          }

          public function formatFail(Outcome $outcome): string
          {
              return "FAIL: {$outcome->name()} - {$outcome->message}";
          }
      }
      """
    And a feature file "features/calc.feature":
      """
      Feature: Calc
        Scenario: Adds
          Given a passing step
        Scenario: Subtracts
          Given a failing step
      """
    And a step file "features/steps/steps.php":
      """
      <?php
      given('a passing step', fn () => expect(1)->toBe(1));
      given('a failing step', fn () => expect(1)->toBe(2));
      """
    When I run phpspec run with option "--story --format stories"
    Then the output should contain "OK: Calc > Adds"
    And the output should contain "FAIL: Calc > Subtracts - Expected 1 to be 2"
    And the output should contain "2 scenarios (1 passed, 1 failed)"
    And the exit code should be 1

  Scenario: A formatter extension knows where an example or a scenario went wrong
    Given a phpspec.yaml config:
      """
      extensions:
        formatters:
          - App\GithubFormatter
      autoload:
        App\: src/App
      """
    And a class "src/App/GithubFormatter.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\FormatterExtension;
      use PhpSpec\Result\Outcome;

      class GithubFormatter extends FormatterExtension
      {
          public function getName(): string
          {
              return 'github';
          }

          public function formatPass(Outcome $outcome): string
          {
              return "::debug::{$outcome->name()}";
          }

          public function formatFail(Outcome $outcome): string
          {
              return sprintf('::error file=%s,line=%d::%s', $outcome->failedAt?->file, $outcome->failedAt?->line, $outcome->name());
          }
      }
      """
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('Calc', function () {
          it('subtracts', function () {
              expect(2 - 1)->toBe(2);
          });
      });
      """
    And a feature file "features/calc.feature":
      """
      Feature: Calc
        Scenario: Subtracts
          Given a failing step
      """
    And a step file "features/steps/steps.php":
      """
      <?php
      given('a failing step', fn () => expect(1)->toBe(2));
      """
    When I run phpspec run with option "--format github"
    Then the output should contain "::error file=spec/App/Calc.spec.php,line=4::Calc > subtracts"
    When I run phpspec run with option "--story --format github"
    Then the output should contain "::error file=features/steps/steps.php,line=2::Calc > Subtracts"

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

  Scenario: A listener extension hears each example and each scenario as an Outcome
    Given a phpspec.yaml config:
      """
      extensions:
        listeners:
          - App\HeardListener
      autoload:
        App\: src/App
      """
    And a class "src/App/HeardListener.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\ListenerExtension;
      use PhpSpec\Result\Outcome;

      class HeardListener extends ListenerExtension
      {
          public function onPass(Outcome $outcome): void
          {
              file_put_contents('heard.txt', "passed {$outcome->name()}\n", FILE_APPEND);
          }

          public function onFail(Outcome $outcome): void
          {
              file_put_contents('heard.txt', "failed {$outcome->name()} at {$outcome->failedAt}\n", FILE_APPEND);
          }
      }
      """
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('Calc', function () {
          it('adds', fn () => expect(1 + 1)->toBe(2));
          it('subtracts', fn () => expect(2 - 1)->toBe(2));
      });
      """
    And a feature file "features/calc.feature":
      """
      Feature: Calc
        Scenario: Adds
          Given a passing step
        Scenario: Subtracts
          Given a failing step
      """
    And a step file "features/steps/steps.php":
      """
      <?php
      given('a passing step', fn () => expect(1)->toBe(1));
      given('a failing step', fn () => expect(1)->toBe(2));
      """
    When I run phpspec run
    And I run phpspec run with option "--story"
    Then the file "heard.txt" should contain "passed Calc > adds"
    And the file "heard.txt" should contain "failed Calc > subtracts at spec/App/Calc.spec.php:4"
    And the file "heard.txt" should contain "passed Calc > Adds"
    And the file "heard.txt" should contain "failed Calc > Subtracts at features/steps/steps.php:3"

  Scenario: Under --parallel, a listener extension hears the suite once and each example once
    Given a phpspec.yaml config:
      """
      extensions:
        listeners:
          - App\CountingListener
      autoload:
        App\: src/App
      """
    And a class "src/App/CountingListener.php":
      """
      <?php
      namespace App;

      use PhpSpec\Extensions\ListenerExtension;
      use PhpSpec\Result\Outcome;

      class CountingListener extends ListenerExtension
      {
          public function beforeSuite(): void
          {
              file_put_contents('heard.txt', "began\n", FILE_APPEND);
          }

          public function onPass(Outcome $outcome): void
          {
              file_put_contents('heard.txt', "heard {$outcome->name()}\n", FILE_APPEND);
          }

          public function afterSuite(): void
          {
              file_put_contents('heard.txt', "ended\n", FILE_APPEND);
          }
      }
      """
    And a spec file "spec/App/Left.spec.php":
      """
      <?php
      describe('Left', function () {
          it('works', fn () => expect(true)->toBeTrue());
      });
      """
    And a spec file "spec/App/Right.spec.php":
      """
      <?php
      describe('Right', function () {
          it('works', fn () => expect(true)->toBeTrue());
      });
      """
    When I run phpspec run with option "--parallel=2"
    Then all examples should pass
    And the file "heard.txt" should contain "began" exactly 1 times
    And the file "heard.txt" should contain "heard Left > works" exactly 1 times
    And the file "heard.txt" should contain "heard Right > works" exactly 1 times
    And the file "heard.txt" should contain "ended" exactly 1 times

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

  Scenario: Custom browser extension drives visit()
    Given a phpspec.yaml config:
      """
      extensions:
        browser: App\CannedBrowser
      base_url: http://unused.test
      autoload:
        App\: src/App
      """
    And a class "src/App/CannedBrowser.php":
      """
      <?php
      namespace App;

      use PhpSpec\Browser\Browser;
      use PhpSpec\Browser\Response;

      class CannedBrowser implements Browser
      {
          public function request(string $method, string $url, array $options = []): Response
          {
              return new Response(299, "canned answer for $url", ['X-Canned' => 'yes']);
          }
      }
      """
    And a spec file "spec/App/StatusPage.spec.php":
      """
      <?php

      describe('StatusPage', function () {
          it('is served by whatever browser the extension registered', function () {
              $response = visit('/status');

              expect($response->status)->toBe(299);
              expect($response->body)->toContain('canned answer for http://unused.test/status');
          });
      });
      """
    When I run phpspec run
    Then the output should contain "1 example (1 passed)"
    And the exit code should be 0
