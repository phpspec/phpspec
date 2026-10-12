Feature: A run narrowed to nothing fails
  As a developer, or a coding agent reading the run
  I want a run whose filter, tags or paths select nothing to fail, saying what selected nothing
  So that an empty selection never passes for a green run

  Background:
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('Calc', function () {
          it('handles 1', fn () => expect(1)->toBe(1));
          it('handles 2', fn () => expect(2)->toBe(2));
      });
      """
    And a feature file "features/counting.feature":
      """
      Feature: Counting
        @smoke
        Scenario: Counting one
          Given nothing to count
      """
    And a step file "features/steps/counting.steps.php":
      """
      <?php
      given('nothing to count', function () {});
      """
    And a file "spec/Empty/notes.txt":
      """
      Nothing to run here.
      """
    And a file "paths/none.txt":
      """

      """

  Scenario Outline: A run narrowed to nothing fails, saying once what selected nothing
    When I run phpspec run with option "<narrowing>"
    Then the exit code should be 1
    And the output should contain "<problem>" exactly 1 times

    Examples:
      | narrowing                          | problem                                    |
      | --filter '^handles 1$'             | No example or scenario matches --filter    |
      | --format=dot --filter '^handles 1$' | No example or scenario matches --filter   |
      | --tags @nope                       | No scenario matches --tags                 |
      | --story --tags @nope               | No scenario matches --tags                 |
      | spec/Empty                         | No example or scenario in spec/Empty       |
      | spec/Empty features/steps          | No example or scenario in spec/Empty, features/steps |
      | --paths-from paths/none.txt        | Paths file lists no path: paths/none.txt   |
      | spec/App/Calc.spec.php@0123456789ab | No example at spec/App/Calc.spec.php@0123456789ab |

  Scenario Outline: A run narrowed to nothing answers a coding agent with a fatal
    When I run phpspec run with option "<narrowing> --format=agent"
    Then the exit code should be 1
    And the output should be valid JSON
    And the first event should be "run_started"
    And the fatal event should name "<problem>"
    And the summary should state "examples" as "0"
    And the summary should state "actionable" as "1"

    Examples:
      | narrowing                   | problem                                    |
      | --filter '^handles 1$'      | No example or scenario matches --filter    |
      | --tags @nope                | No scenario matches --tags                 |
      | spec/Empty                  | No example or scenario in spec/Empty       |
      | --paths-from paths/none.txt | Paths file lists no path: paths/none.txt   |

  Scenario Outline: A run that selects anything passes, however little and wherever it found it
    When I run phpspec run with option "<narrowing>"
    Then the exit code should be 0
    And the output should contain "<ran>"

    Examples:
      | narrowing                                  | ran                   |
      | --filter 'handles 1'                       | 1 example (1 passed)  |
      | --story --tags @smoke                      | 1 scenario, 1 step (1 passed) |
      | spec/Empty spec/App/Calc.spec.php          | 2 examples (2 passed) |
      | spec/Empty spec/App/Calc.spec.php:3        | 1 example (1 passed)  |

  Scenario: A run that selects anything reads as green to a coding agent
    When I run phpspec run with option "spec/Empty spec/App/Calc.spec.php --filter 'handles 1' --format=agent"
    Then the exit code should be 0
    And the output should be valid JSON
    And the summary should state "examples" as "1"
    And the summary should state "actionable" as "0"
