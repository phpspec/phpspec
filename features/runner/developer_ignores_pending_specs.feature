Feature: Developer ignores pending test warnings
  So that I can automate tasks without failures for pending specs
  As a Developer
  I should be able to ignore pending spec warnings

  Scenario: Run command with --ignore-pending flag set, using the dot formatter
    Given the spec file "spec/Runner/IgnoresPendingExample1/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\IgnoresPendingExample1;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_does_nothing()
          {
          }
      }

      """
    When I run phpspec using the "pretty" format and "ignore-pending" option
    Then I should see:
      """
        9  - does nothing
              todo: write pending example


      1 specs
      1 examples (1 pending)
      """
    But the suite should pass


  Scenario: ignore-pending is specified in the config
    Given the config file contains:
      """
      ignore_pending: true
      """
    And the spec file "spec/Runner/IgnoresPendingExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\IgnoresPendingExample2;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_does_nothing()
          {
          }
      }

      """
    When I run phpspec using the "pretty" format
    Then I should see:
      """
        9  - does nothing
              todo: write pending example


      1 specs
      1 examples (1 pending)
      """
    But the suite should pass


  Scenario: ignore-pending is not specified in either the command line or the config
    Given the spec file "spec/Runner/IgnoresPendingExample3/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\IgnoresPendingExample3;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_does_nothing()
          {
          }
      }

      """
    When I run phpspec using the "pretty" format
    Then I should see:
      """
        9  - does nothing
              todo: write pending example


      1 specs
      1 examples (1 pending)
      """
    But the suite should not pass
