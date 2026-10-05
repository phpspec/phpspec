Feature: Agent output format
  As a coding agent
  I want phpspec results as machine-readable JSON events, one per line
  So that I can act on a failure while the rest of the suite is still running

  Scenario: The run answers in JSON Lines, one event per line
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds', function () { expect(1)->toBe(2); });
          it('subtracts', function () { expect(3)->toBe(4); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should have 4 events
    And the first event should be "run_started"
    And the last event should be "summary"

  Scenario: A passing run emits a valid JSON document with run_started and summary
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "run_started"
    And the output should contain "summary"
    And the output should contain "passing"

  Scenario: The header states which PHP ran and whether coverage and guard are on
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the header should state "php"
    And the header should state "coverage" as "false"
    And the header should state "guard" as "off"

  Scenario: A fatal says how to get past it, when that is known
    When I run phpspec run with option "--bootstrap=nope.php --format=agent"
    Then the output should be valid JSON
    And the output should contain "fatal"
    And the output should contain "remedy"
    And the output should contain "--bootstrap"

  Scenario: A failing example carries its expected, actual and state
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(3500)->toBe(4000); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "failing"
    And the output should contain "expected"
    And the output should contain "4000"
    And the output should contain "3500"

  Scenario: What a failing example printed rides with it, off the document's stream
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () {
              echo "the subject said this";
              expect(3500)->toBe(4000);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the reported entry should have printed "the subject said this"

  Scenario: What a scenario printed rides with it, whichever step printed it
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/counting.feature":
      """
      Feature: Counting
        Scenario: Counting up
          Given I run the counter
          Then it should have counted 3
      """
    And a step file "features/steps/counting.steps.php":
      """
      <?php

      given('I run the counter', function () {
          echo "the counter said 2";
          $this->count = 2;
      });

      then('it should have counted {int}', function (int $expected) {
          expect($this->count)->toBe($expected);
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the reported entry should have printed "the counter said 2"

  Scenario: A boolean failure says what the matcher wanted, not only what it got
    Given a spec file "spec/App/Watch.spec.php":
      """
      <?php
      describe('App\Watch', function () {
          it('arrives', function () { expect(false)->toBeTrue(); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the reported entry should expect "true" and have got "false"

  Scenario: An emptiness failure wants the empty form of what it was given
    Given a spec file "spec/App/Bag.spec.php":
      """
      <?php
      describe('App\Bag', function () {
          it('holds nothing', function () { expect([1, 2])->toBeEmpty(); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the reported entry should expect "[]" and have got "[1,2]"

  Scenario: A scenario hands over the log it was watching, read before its teardown
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/watching.feature":
      """
      Feature: Watching
        Scenario: Watching a file
          Given the watcher is running
          Then it should have arrived
      """
    And a step file "features/steps/watching.steps.php":
      """
      <?php

      use function PhpSpec\attach;

      given('the watcher is running', function () {
          $this->log = sys_get_temp_dir() . '/phpspec_watch_' . getmypid() . '.log';
          file_put_contents($this->log, 'nothing yet');
          attach('watch.log', fn() => @file_get_contents($this->log));
      });

      then('it should have arrived', function () {
          // The watcher writes its diagnosis only now, after the attachment was made.
          file_put_contents($this->log, 'CANNOT READ --watch');
          expect(false)->toBeTrue();
      });

      afterScenario(function () {
          @unlink($this->log);
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the reported entry should have attached "watch.log" containing "CANNOT READ --watch"

  Scenario: A failing example carries a line-targeted rerun command
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(3500)->toBe(4000); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "rerun"
    And the output should contain "run spec/App/Calc.spec.php:"

  Scenario: An error thrown inside the code under test is addressed by the example that reached it
    Given a class "src/App/Money.php":
      """
      <?php
      namespace App;

      class Money {
          public static function fromCents(int $cents): self {
              throw new \RuntimeException('not yet');
          }
      }
      """
    And a spec file "spec/App/Money.spec.php":
      """
      <?php
      describe('App\Money', function () {
          it('is made from cents', function () {
              expect(App\Money::fromCents(1000))->toBeAnInstanceOf(App\Money::class);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "run spec/App/Money.spec.php:3"
    And the output should contain "src/App/Money.php:6"
    And the output should not contain "run src/"

  Scenario: A let binding that throws fails each example that needed it, and the count holds
    Given a spec file "spec/App/Money.spec.php":
      """
      <?php
      describe('App\Money', function () {
          let('tenEuros', fn() => throw new RuntimeException('not yet'));

          it('one', function () { expect($this->tenEuros)->toBeNull(); });
          it('two', function () { expect($this->tenEuros)->toBeNull(); });
          it('three', function () { expect(true)->toBeTrue(); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should have 5 events
    And the output should contain "Money > one"
    And the output should contain "Money > three"
    And the output should contain "run spec/App/Money.spec.php:5"

  Scenario: A stop flag halts the run at the first example that meets it
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('Calc', function () {
          it('adds', function () { throw new RuntimeException('boom'); });
          it('subtracts', function () { expect(1)->toBe(1); });
          it('multiplies', function () { throw new RuntimeException('bang'); });
      });
      """
    When I run phpspec run with option "--format=agent --stop-on-error"
    Then the output should be valid JSON
    And the output should contain "Calc > adds" exactly 1 times
    And the output should not contain "multiplies"

  Scenario: A rerun naming several lines of one file reports each example once
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('Calc', function () {
          it('adds', function () { expect(1)->toBe(2); });
          it('subtracts', function () { expect(3)->toBe(3); });
          it('multiplies', function () { expect(5)->toBe(6); });
      });
      """
    When I run phpspec run with option "spec/App/Calc.spec.php:3 spec/App/Calc.spec.php:5 --format=agent"
    Then the output should be valid JSON
    And the output should contain "Calc > adds" exactly 1 times
    And the output should contain "Calc > multiplies" exactly 1 times
    And the output should not contain "subtracts"

  Scenario: A verbose run reports each passing example too, with its id and the line that re-runs it alone
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('Calc', function () {
          it('adds', function () { expect(1)->toBe(1); });
          it('subtracts', function () { expect(3)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent -v"
    Then the output should be valid JSON
    And the output should have 4 events
    And the output should contain "Calc > adds" exactly 1 times
    And the output should contain "run spec/App/Calc.spec.php:3" exactly 1 times
    And the output should contain "run spec/App/Calc.spec.php:4" exactly 2 times

  Scenario: A verbose story run reports each passing scenario too
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/counting.feature":
      """
      Feature: Counting
        Scenario: Counting up
          Given a working step
      """
    And a step file "features/steps/counting.steps.php":
      """
      <?php

      given('a working step', function () {
      });
      """
    When I run phpspec run with option "features/ --format=agent -v"
    Then the output should be valid JSON
    And the output should have 3 events
    And the output should contain "Counting > Counting up" exactly 1 times
    And the output should contain "run features/counting.feature:2" exactly 1 times

  Scenario: A randomised run keeps the document the only thing on stdout
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent --order=random"
    Then the output should be valid JSON
    And the output should contain "seed"

  Scenario: A profiled run keeps the document the only thing on stdout
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent --profile=3"
    Then the output should be valid JSON

  Scenario: A failed coverage gate rides inside the document instead of printing after it
    Given a PSR-4 project with "spec" and "src" directories
    And a class "src/App/Calc.php":
      """
      <?php

      namespace App;

      class Calc
      {
          public function add(int $a, int $b): int
          {
              return $a + $b;
          }

          public function unused(): int
          {
              return 0;
          }
      }
      """
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php

      use App\Calc;

      describe('Calc', function () {
          it('adds two numbers', function () {
              expect((new Calc())->add(1, 2))->toBe(3);
          });
      });
      """
    When I run phpspec run with coverage options "--format=agent --coverage-min=90"
    Then the output should be valid JSON
    And the output should contain "coverage"
    And the output should contain "required"
    And the exit code should be 1

  Scenario: Two scenarios failing the same step are told apart and re-run one by one
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/counting.feature":
      """
      Feature: Counting
        Scenario: Counting up
          Given a broken step

        Scenario: Counting down
          Given a broken step
      """
    And a step file "features/steps/counting.steps.php":
      """
      <?php

      given('a broken step', function () {
          throw new RuntimeException('this step is broken');
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the failing entries should have distinct ids
    And the output should contain "run features/counting.feature:2 features/counting.feature:5"

  Scenario: A broken scenario is one entry, naming the step that broke it
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/checkout.feature":
      """
      Feature: Checkout
        Scenario: Paying for a basket
          Given a basket with 2 items
          When I pay
          Then I get a receipt
      """
    And a step file "features/steps/checkout.steps.php":
      """
      <?php

      given('a basket with {int} items', function (int $count) {
          throw new RuntimeException('no basket yet');
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the report should have 1 entry
    And the output should contain "Checkout > Paying for a basket"
    And the output should contain "no basket yet"
    And the output should contain "run features/checkout.feature:2"

  Scenario: A failing step carries the expectation, not only the English of it
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/counting.feature":
      """
      Feature: Counting
        Scenario: Counting up
          Given I count 2 items
      """
    And a step file "features/steps/counting.steps.php":
      """
      <?php

      given('I count {int} items', function (int $count) {
          expect($count)->toBe(3);
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the report should have 1 entry
    And the failing step should report expected 3 and actual 2

  Scenario: The same step twice in one scenario is still one entry
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/twice.feature":
      """
      Feature: Twice
        Scenario: The same step twice
          Given a broken step
          Given a broken step
      """
    And a step file "features/steps/twice.steps.php":
      """
      <?php

      given('a broken step', function () {
          throw new RuntimeException('this step is broken');
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the report should have 1 entry

  Scenario: Each row of a scenario outline is told apart by its values
    Given a PSR-4 project with "spec", "src", and "features" directories
    And a feature file "features/adding.feature":
      """
      Feature: Adding
        Scenario Outline: Adding numbers
          Given I add <a> and <b>

          Examples:
            | a | b |
            | 1 | 2 |
            | 3 | 4 |
      """
    And a step file "features/steps/adding.steps.php":
      """
      <?php

      given('I add {int} and {int}', function (int $a, int $b) {
          throw new RuntimeException("cannot add $a and $b");
      });
      """
    When I run phpspec run with option "features/ --format=agent"
    Then the output should be valid JSON
    And the report should have 2 entries
    And the failing entries should have distinct ids
    And the output should contain "Adding numbers (1, 2)"
    And the output should contain "Adding numbers (3, 4)"

  Scenario: An errored example says what went wrong in the same field a failure does
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { throw new RuntimeException('the adder exploded'); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And every reported entry should carry a message
    And the output should contain "the adder exploded"

  Scenario: A whole float is not reported as an integer
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(90.0)->toBe(90); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "Expected 90.0 to be 90"

  Scenario: An object in a failure is named, not numbered
    Given a spec file "spec/App/Bag.spec.php":
      """
      <?php

      enum Status
      {
          case Active;
      }

      class Money
      {
          public function __construct(private string $amount) {}

          public function __toString(): string
          {
              return $this->amount;
          }
      }

      describe('App\Bag', function () {
          it('holds a status', function () { expect(Status::Active)->toBeNull(); });
          it('holds an amount', function () { expect(new Money('12.00 GBP'))->toBeNull(); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "Status::Active"
    And the output should contain "12.00 GBP"
    And the output should not contain "Status#"

  Scenario: A length failure reports the length the subject actually has
    Given a spec file "spec/App/Bag.spec.php":
      """
      <?php
      describe('App\Bag', function () {
          it('holds two', function () { expect([1])->toHaveLength(2); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "Expected [1] to have length 2, has 1"

  Scenario: The summary carries one command that re-runs everything that failed
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds', function () { expect(1)->toBe(2); });
          it('subtracts', function () { expect(3)->toBe(4); });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "spec/App/Calc.spec.php:3 spec/App/Calc.spec.php:4"

  Scenario: A run that cannot start still answers with a document
    Given a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () { expect(2)->toBe(2); });
      });
      """
    When I run phpspec run with option "--format=agent --bootstrap=nope.php"
    Then the output should be valid JSON
    And the output should contain "fatal"
    And the output should contain "Bootstrap file not found"
    And the first event should be "run_started"
    And the last event should be "summary"
    And the exit code should be 1

  Scenario: A fatal still leaves a document, naming what stopped the run
    Given a spec file "spec/App/Broken.spec.php":
      """
      <?php

      interface Speaks
      {
          public function speak(): string;
      }

      class Mute implements Speaks {}

      describe('App\Broken', function () {
          it('never runs', function () {});
      });
      """
    When I run phpspec run in a fresh process with option "--format=agent"
    Then the standard output should be valid JSON
    And the output should contain "fatal"
    And the output should contain "abstract method"

  Scenario: An offer can be taken on its own, by the id the run reported
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php
      describe('App\Basket', function () {
          it('applies a coupon', function () {
              expect(new App\Coupon())->toBeAnInstanceOf(App\Coupon::class);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "create_class"
    And no file "src/App/Coupon.php" should be generated
    When I accept the offers phpspec made
    Then a class file "src/App/Coupon.php" should be generated

  Scenario: A missing method surfaces as an offer, and accept by id writes it
    Given a class "src/App/Calc.php":
      """
      <?php
      namespace App;

      class Calc {}
      """
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () {
              expect((new App\Calc())->add(2, 3))->toBe(5);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "create_method"
    When I accept the offers phpspec made with option "--format=agent"
    Then the output should contain "src/App/Calc.php"
    And the class "src/App/Calc.php" should contain "public function add($argument1, $argument2)"

  Scenario: An offer whose method was written by hand in the meantime is reported as not applied
    Given a class "src/App/Calc.php":
      """
      <?php
      namespace App;

      class Calc {}
      """
    And a spec file "spec/App/Calc.spec.php":
      """
      <?php
      describe('App\Calc', function () {
          it('adds two numbers', function () {
              expect((new App\Calc())->add(2, 3))->toBe(5);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    And a class "src/App/Calc.php":
      """
      <?php
      namespace App;

      class Calc {
          public function add($a, $b) { return $a + $b; }
      }
      """
    And I accept the offers phpspec made with option "--format=agent"
    Then the receipt should report the offer as not applied because "already exists"
    And the exit code should be 1

  Scenario: An undefined method on a double is offered on the type it doubles
    Given a class "src/App/Sundial.php":
      """
      <?php
      namespace App;

      interface Sundial {}
      """
    And a spec file "spec/App/Alarm.spec.php":
      """
      <?php
      describe('App\Alarm', function () {
          it('rings on time', function () {
              $sundial = mock(App\Sundial::class);
              allow($sundial->now())->toReturn('noon');
              expect($sundial->now())->toBe('noon');
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "Sundial::now"
    And the output should not contain "PhpspecDouble"
    When I run phpspec run with option "--accept-offers"
    Then the file "src/App/Sundial.php" should contain "public function now("

  Scenario: A mock failure says what call was wanted and what the double received
    Given a class "src/App/Ledger.php":
      """
      <?php
      namespace App;

      interface Ledger {
          public function record(string $line): void;
      }
      """
    And a spec file "spec/App/Till.spec.php":
      """
      <?php
      describe('App\Till', function () {
          it('records a sale', function () {
              $ledger = mock(App\Ledger::class);
              $ledger->record('refund');
              expect($ledger->record('sale'))->toBeCalled();
          });

          it('records the sale it was asked to', function () {
              $ledger = mock(App\Ledger::class);
              $ledger->record('refund');
              expect($ledger->record('sale'))->toBeCalledWith('sale');
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "toBeCalled" exactly 2 times
    And the output should contain "toBeCalledWith"
    And the output should contain "refund"
    And the output should not contain "LastCallDouble"

  Scenario: The accept receipt names the file a generated class was written to
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php
      describe('App\Basket', function () {
          it('applies a coupon', function () {
              expect(new App\Coupon())->toBeAnInstanceOf(App\Coupon::class);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    And I accept the offers phpspec made with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "files"
    And the output should contain "src/App/Coupon.php"
    And a class file "src/App/Coupon.php" should be generated

  Scenario: A missing class surfaces as an offer, and --accept-offers generates it
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php
      describe('App\Basket', function () {
          it('applies a coupon', function () {
              expect(new App\Coupon())->toBeAnInstanceOf(App\Coupon::class);
          });
      });
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "create_class"
    And the output should contain "Coupon"
    When I run phpspec run with option "--accept-offers"
    Then the exit code should be 0
    And a class file "src/App/Coupon.php" should be generated

  Scenario: The summary of an --accept-offers run says what was written and that nothing has verified it
    Given a spec file "spec/App/Basket.spec.php":
      """
      <?php
      describe('App\Basket', function () {
          it('applies a coupon', function () {
              expect(new App\Coupon())->toBeAnInstanceOf(App\Coupon::class);
          });
      });
      """
    When I run phpspec run with option "--format=agent --accept-offers"
    Then the exit code should be 0
    And the output should be valid JSON
    And the output should contain "applied"
    And the output should contain "src/App/Coupon.php"
    And the output should contain "verified"
    And a class file "src/App/Coupon.php" should be generated

  Scenario: An empty method surfaces as a fake_method offer, filled by --accept-offers --fake
    Given a spec file "spec/App/Calculator.spec.php":
      """
      <?php
      use App\Calculator;

      describe(Calculator::class, function () {
          let("calc", fn() => new Calculator());
          it("adds", function () {
              expect($this->calc->add(1, 2))->toBe(3);
          });
      });
      """
    And a class "src/App/Calculator.php":
      """
      <?php
      namespace App;

      class Calculator {
          public function add($a, $b) {}
      }
      """
    When I run phpspec run with option "--format=agent"
    Then the output should be valid JSON
    And the output should contain "fake_method"
    When I run phpspec run with option "--accept-offers --fake"
    Then the exit code should be 0
    And a class file "src/App/Calculator.php" should be generated
    And it should contain "return 3"
