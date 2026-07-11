Feature: Story BDD with Gherkin
  As a developer
  I want to write acceptance scenarios in Gherkin
  So that I can describe system behaviour in plain language

  Background:
    Given a PSR-4 project with "spec", "src", and "features" directories

  Scenario: Running a passing feature
    Given a feature file "features/greeting.feature":
      """
      Feature: Greeting
        Scenario: Say hello
          Given a greeting service
          When I greet "World"
          Then I should see "Hello, World!"
      """
    And a step file "features/steps/greeting.steps.php":
      """
      <?php
      given("a greeting service", function () {
          $this->result = '';
      });

      when("I greet {string}", function (string $name) {
          $this->result = "Hello, {$name}!";
      });

      then("I should see {string}", function (string $expected) {
          expect($this->result)->toBe($expected);
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass

  Scenario: Undefined steps are reported
    Given a feature file "features/missing.feature":
      """
      Feature: Missing steps
        Scenario: No definitions
          Given a step with no definition
      """
    When I run phpspec run "features/"
    Then the output should contain "undefined"

  Scenario: Pending steps are reported
    Given a feature file "features/pending.feature":
      """
      Feature: Pending
        Scenario: Work in progress
          Given a pending step
      """
    And a step file "features/steps/pending.steps.php":
      """
      <?php
      given("a pending step", function () {
          pending();
      });
      """
    When I run phpspec run "features/"
    Then the output should contain "pending"

  Scenario: Failed steps skip remaining steps in scenario
    Given a feature file "features/failing.feature":
      """
      Feature: Failing
        Scenario: Fails midway
          Given a step that fails
          Then this step is skipped
      """
    And a step file "features/steps/failing.steps.php":
      """
      <?php
      given("a step that fails", function () {
          throw new \RuntimeException("boom");
      });

      then("this step is skipped", function () {
          expect(true)->toBeTrue();
      });
      """
    When I run phpspec run "features/"
    Then the output should contain "failed"
    And the output should contain "skipped"

  Scenario: Background steps run before each scenario
    Given a feature file "features/background.feature":
      """
      Feature: Background
        Background:
          Given a common setup

        Scenario: First
          Then the setup should be available

        Scenario: Second
          Then the setup should be available
      """
    And a step file "features/steps/background.steps.php":
      """
      <?php
      given("a common setup", function () {
          $this->ready = true;
      });

      then("the setup should be available", function () {
          expect($this->ready)->toBeTrue();
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass

  Scenario: Scenario outline expands examples
    Given a feature file "features/outline.feature":
      """
      Feature: Cucumbers
        Scenario Outline: Eating
          Given there are <start> cucumbers
          When I eat <eat> cucumbers
          Then I should have <left> cucumbers

          Examples:
            | start | eat | left |
            | 12    | 5   | 7    |
            | 20    | 5   | 15   |
      """
    And a step file "features/steps/cucumbers.steps.php":
      """
      <?php
      given("there are {int} cucumbers", function (int $n) {
          $this->count = $n;
      });

      when("I eat {int} cucumbers", function (int $n) {
          $this->count -= $n;
      });

      then("I should have {int} cucumbers", function (int $n) {
          expect($this->count)->toBe($n);
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass

  Scenario: Data table passed to step
    Given a feature file "features/tables.feature":
      """
      Feature: Tables
        Scenario: User list
          Given the following users:
            | name  | role  |
            | Alice | admin |
            | Bob   | user  |
          Then there should be 2 users
      """
    And a step file "features/steps/tables.steps.php":
      """
      <?php
      use PhpSpec\StoryBDD\DataTable;

      given("the following users:", function (DataTable $table) {
          $this->users = $table->toArray();
      });

      then("there should be {int} users", function (int $count) {
          expect($this->users)->toHaveCount($count);
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass

  Scenario: Tags on features and scenarios
    Given a feature file "features/tagged.feature":
      """
      @smoke
      Feature: Tagged
        @wip
        Scenario: Tagged scenario
          Given a step
      """
    And a step file "features/steps/tagged.steps.php":
      """
      <?php
      given("a step", function () {
          expect(true)->toBeTrue();
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass

  Scenario: Shared world across steps in a scenario
    Given a feature file "features/world.feature":
      """
      Feature: Shared state
        Scenario: Pass data between steps
          Given I store "hello" as message
          Then the message should be "hello"
      """
    And a step file "features/steps/world.steps.php":
      """
      <?php
      given("I store {string} as message", function (string $msg) {
          $this->message = $msg;
      });

      then("the message should be {string}", function (string $expected) {
          expect($this->message)->toBe($expected);
      });
      """
    When I run phpspec run "features/"
    Then all steps should pass

  Scenario: Step generation for undefined steps
    Given a feature file "features/generate.feature":
      """
      Feature: Generation
        Scenario: New steps
          Given I have a new thing
          When I use the thing
          Then something happens
      """
    When I run phpspec run "features/" and answer "y" to generation prompts
    Then a step file should be generated with step definitions

  Scenario: And and But steps generate the keyword of the step they follow
    Given a feature file "features/keywords.feature":
      """
      Feature: Keywords
        Scenario: Mixed keywords
          Given a precondition
          And another precondition
          When an action
          But not another action
          Then an outcome
          And another outcome
      """
    When I run phpspec run "features/" and answer "y" to generation prompts
    Then the file "features/steps/keywords.steps.php" should contain:
      """
      given("a precondition"
      """
    And the file "features/steps/keywords.steps.php" should contain:
      """
      given("another precondition"
      """
    And the file "features/steps/keywords.steps.php" should contain:
      """
      when("an action"
      """
    And the file "features/steps/keywords.steps.php" should contain:
      """
      when("not another action"
      """
    And the file "features/steps/keywords.steps.php" should contain:
      """
      then("an outcome"
      """
    And the file "features/steps/keywords.steps.php" should contain:
      """
      then("another outcome"
      """
