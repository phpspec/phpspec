Feature: Option values PhpSpec cannot take are refused
  As a developer, or a coding agent reading the run
  I want a value an option cannot take to stop the run, naming the option and what it takes
  So that a typo never runs as something else, such as a coverage gate of "abc" read as 0%

  Background:
    Given a PSR-4 project with "spec" and "src" directories
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('Calc', function () {
          it('adds', fn () => expect(1 + 1)->toBe(2));
      });
      """

  Scenario Outline: A value an option cannot take stops the run before anything runs
    When I run phpspec run with option "<options>"
    Then the exit code should be 1
    And the output should contain "<problem>"
    And the output should not contain "1 example"

    Examples:
      | options                       | problem                                                    |
      | --order=bogus                 | --order takes default or random, not                       |
      | --seed=abc                    | --seed takes a whole number, not                           |
      | --order=random --seed=1.5     | --seed takes a whole number, not                           |
      | --order=default --seed=42     | --seed shuffles a random run, and --order default does not |
      | --profile=abc                 | --profile takes a number of examples, 1 or more, not       |
      | --profile=0                   | --profile takes a number of examples, 1 or more, not       |
      | --coverage-min=abc            | --coverage-min takes a percentage from 0 to 100, not       |
      | --coverage-min=150            | --coverage-min takes a percentage from 0 to 100, not       |
      | --coverage-min=-1             | --coverage-min takes a percentage from 0 to 100, not       |
      | --parallel=0                  | --parallel takes a number of workers, 1 or more, not       |
      | --filter=                     | --filter takes the text to select by, not                  |
      | --coverage-html=              | --coverage-html takes a directory to write to, not         |
      | --coverage-clover=            | --coverage-clover takes a file to write to, not            |
      | --coverage-json=              | --coverage-json takes a file to write to, not              |
      | --coverage-src=               | --coverage-src takes the source directory to cover, not    |
      | --format=html --out=          | --out takes a file to write the report to, not             |

  Scenario Outline: A value an option cannot take answers a coding agent with a fatal
    When I run phpspec run with option "<options> --format=agent"
    Then the exit code should be 1
    And the output should be valid JSON
    And the fatal event should name "<problem>"
    And the summary should state "actionable" as "1"

    Examples:
      | options            | problem                                              |
      | --order=bogus      | --order takes default or random, not                 |
      | --coverage-min=abc | --coverage-min takes a percentage from 0 to 100, not |

  Scenario Outline: A value an option can take runs
    When I run phpspec run with option "<options>"
    Then the exit code should be 0
    And the output should contain "<shows>"

    Examples:
      | options                    | shows                   |
      | --seed=42                  | Randomised with seed 42 |
      | --order=random --seed=-7   | Randomised with seed -7 |
      | --order=default            | 1 example (1 passed)    |
      | --profile=3                | slowest examples        |
      | --parallel=1               | 1 example (1 passed)    |
