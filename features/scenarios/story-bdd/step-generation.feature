Feature: Generating the undefined steps
  As a developer writing stories
  I want every undefined step offered once, to one steps file I choose
  So that steps are grouped by what they do, never one file per feature

  Background:
    Given a feature file "features/playing.feature":
      """
      Feature: Playing
        Scenario: A game
          Given some 42 stuff
          And a user named "Chuck Norris"
          When the user plays
          Then the player always win
      """
    And a feature file "features/paying.feature":
      """
      Feature: Paying
        Scenario: A tea
          Given a tea costing 4.5
          When the user plays
      """

  Scenario: With no steps file yet, the steps of every feature go to steps.php
    When I run phpspec run "features/" answering "y"
    Then the output should contain "You have undefined steps. Would you like me to generate the steps for you? [Y/n]"
    And the file "features/steps/steps.php" should contain:
      """
      given("some {int} stuff", function (int $arg1) {
          pending();
      });
      """
    And the file "features/steps/steps.php" should contain:
      """
      given("a user named {string}", function (string $arg1) {
      """
    And the file "features/steps/steps.php" should contain:
      """
      when("the user plays", function () {
      """
    And the file "features/steps/steps.php" should contain:
      """
      then("the player always win", function () {
      """
    And the file "features/steps/steps.php" should contain:
      """
      given("a tea costing {float}", function (float $arg1) {
      """
    And the file "features/steps/steps.php" should contain "the user plays" exactly 1 times
    And no file "features/steps/playing.steps.php" should be generated
    And no file "features/steps/paying.steps.php" should be generated

  Scenario: A step two features share is defined once, so the next run loads cleanly
    When I run phpspec run "features/" answering "y, y"
    And I run phpspec run "features/"
    Then the output should not contain "already defined"
    And the output should contain "pending"

  Scenario: With steps files, the person picks the file the steps go to
    Given a step file "features/steps/web.steps.php":
      """
      <?php
      given("I visit {string}", function (string $url) {});
      """
    And a step file "features/steps/assertions.steps.php":
      """
      <?php
      then("nothing else happens", function () {});
      """
    When I run phpspec run "features/" answering "2"
    Then the output should contain:
      """
      You have undefined steps. Would you like me to generate the steps for you?

        [0] No, skip
        [1] assertions.steps.php
        [2] web.steps.php
        [3] New file...
      """
    And the file "features/steps/web.steps.php" should contain "a user named {string}"
    And the file "features/steps/assertions.steps.php" should not contain "a user named"
    And no file "features/steps/steps.php" should be generated

  Scenario: The steps can go to a new file, named by the person
    Given a step file "features/steps/web.steps.php":
      """
      <?php
      given("I visit {string}", function (string $url) {});
      """
    When I run phpspec run "features/" answering "2, players"
    Then the output should contain "Name the new steps file"
    And the file "features/steps/players.steps.php" should contain "a user named {string}"
    And the file "features/steps/web.steps.php" should not contain "a user named"

  Scenario: Skipping writes nothing
    Given a step file "features/steps/web.steps.php":
      """
      <?php
      given("I visit {string}", function (string $url) {});
      """
    When I run phpspec run "features/" answering "0"
    Then the file "features/steps/web.steps.php" should not contain "a user named"
    And no file "features/steps/steps.php" should be generated

  Scenario: An unattended run writes nothing and names where --accept-offers would put the steps
    Given a step file "features/steps/web.steps.php":
      """
      <?php
      given("I visit {string}", function (string $url) {});
      """
    When I run phpspec run with option "features/ --no-interaction"
    Then the output should contain "Nothing was written: there is nobody to answer. Run with --accept-offers to append them to features/steps/steps.php."
    And the output should not contain "[0] No, skip"
    And no file "features/steps/steps.php" should be generated

  Scenario: Accepting the offers appends the steps to steps.php, whatever other steps files there are
    Given a step file "features/steps/web.steps.php":
      """
      <?php
      given("I visit {string}", function (string $url) {});
      """
    When I run phpspec run with option "features/ --accept-offers"
    Then the file "features/steps/steps.php" should contain "a user named {string}"
    And the file "features/steps/web.steps.php" should not contain "a user named"

  Scenario: The steps go to the configured steps_path
    Given a phpspec.yaml config:
      """
      steps_path: acceptance_steps
      """
    When I run phpspec run "features/" answering "y"
    Then the file "acceptance_steps/steps.php" should contain "a user named {string}"
    And no file "features/steps/steps.php" should be generated
