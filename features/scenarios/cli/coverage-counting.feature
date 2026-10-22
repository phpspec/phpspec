Feature: What coverage counts
  As a developer measuring my suite
  I want a line to count as covered when an example or a scenario runs it
  So that every way of running the suite gives the same number

  Background:
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a class "src/App/Basket.php":
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
    And a class "src/App/Shelf.php":
      """
      <?php

      namespace App;

      class Shelf
      {
          public function name(): string
          {
              return 'Shelf';
          }
      }
      """
    And a class "src/App/Clock.php":
      """
      <?php

      namespace App;

      class Clock
      {
          public function name(): string
          {
              return 'Clock';
          }
      }
      """

  Scenario Outline: An example counts what it runs, closures declared around it included, and nothing outside it counts
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;
      use App\Clock;

      describe(Basket::class, function () {
          beforeAll(function () {
              (new Clock())->name();
          });

          $now = (new Clock())->name();
          $named = fn() => (new Basket())->name();

          it('is named', function () use ($named) {
              expect($named())->toBe('Basket');
          });
      });
      """
    When I run phpspec run with coverage options "spec <coverage>"
    Then the output should contain "App/Basket.php ... 100.0% (2/2)"
    And the output should contain "App/Clock.php ....   0.0%"
    And the output should contain "Total: 66.7% (2/3)"

    Examples:
      | coverage                                 |
      | --coverage                               |
      | --coverage --parallel=2                  |
      | --coverage --coverage-json=coverage.json |

  Scenario Outline: A scenario counts what it runs, its own hooks included, and a feature's hooks do not
    Given a feature file "features/shopping.feature":
      """
      Feature: Shopping
        Scenario: a named basket
          Given a basket
          Then it is named
      """
    And a step file "features/steps/shopping.steps.php":
      """
      <?php

      use App\Basket;
      use App\Clock;
      use App\Shelf;

      beforeFeature(function () {
          (new Clock())->name();
      });

      beforeScenario(function () {
          (new Shelf())->name();
      });

      given('a basket', function () {
          $this->basket = new Basket();
      });

      then('it is named', function () {
          expect($this->basket->name())->toBe('Basket');
      });
      """
    When I run phpspec run with coverage options "features <coverage>"
    Then the output should contain "App/Basket.php ... 100.0% (2/2)"
    And the output should contain "App/Shelf.php .... 100.0% (2/2)"
    And the output should contain "App/Clock.php ....   0.0%"
    And the output should contain "Total: 80.0% (4/5)"

    Examples:
      | coverage                                 |
      | --coverage                               |
      | --coverage --parallel=2                  |
      | --coverage --coverage-json=coverage.json |

  Scenario: The JSON report credits a scenario as it credits an example, in a parallel run too
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php

      use App\Basket;

      describe(Basket::class, function () {
          it('is named', function () {
              expect((new Basket())->name())->toBe('Basket');
          });
      });
      """
    And a feature file "features/stocking.feature":
      """
      Feature: Stocking
        Scenario: a named shelf
          Then the shelf is named
      """
    And a step file "features/steps/stocking.steps.php":
      """
      <?php

      use App\Shelf;

      then('the shelf is named', function () {
          expect((new Shelf())->name())->toBe('Shelf');
      });
      """
    When I run phpspec run with coverage options "spec features --parallel=2 --coverage-json=coverage.json"
    Then the file "coverage.json" should attribute line 9 of "src/App/Basket.php" to "spec/App/Basket.spec.php::App\Basket > is named" only
    And the file "coverage.json" should attribute line 9 of "src/App/Shelf.php" to "features/stocking.feature::Stocking > a named shelf" only
