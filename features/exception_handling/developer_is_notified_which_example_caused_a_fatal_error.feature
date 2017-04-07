@php:~5.6
Feature: Developer is notified of which scenario caused a fatal error
  As a Developer
  I want to know in which scenario or example my script was running
  So that I can better trace where my changes caused a fatal error

  @isolated
  Scenario: Spec attempts to call an undeclared function and outputs to stdout
    Given the spec file "spec/Message/Fatal/FatalSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class FatalSpec extends ObjectBehavior
      {
          function it_fatals_when_calling_an_undeclared_function()
          {
              anything();
          }
      }

      """
    And the class file "src/Message/Fatal/Fatal.php" contains:
      """
      <?php

      namespace Message\Fatal;

      class Fatal
      {
          public function __construct($param)
          {
              if ($param == 'throw') {
                  throw new \Exception();
              }
          }
      }

      """
    When I run phpspec
    Then I should see "Fatal error happened while executing the following"
    And  I should see "it fatals when calling an undeclared function"

  @isolated
  Scenario: Fatal error writer message not shown, when formatter does not support it.
    Given the spec file "spec/Message/Fatal/Fatal2Spec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class Fatal2Spec extends ObjectBehavior
      {
          function it_fatals_when_calling_an_undeclared_function()
          {
              anything();
          }
      }

      """
    And the class file "src/Message/Fatal/Fatal2.php" contains:
      """
      <?php

      namespace Message\Fatal;

      class Fatal2
      {
          public function __construct($param)
          {
              if ($param == 'throw') {
                  throw new \Exception();
              }
          }
      }

      """
    When I run phpspec with the "junit" formatter
    Then I should see "Call to undefined function"
