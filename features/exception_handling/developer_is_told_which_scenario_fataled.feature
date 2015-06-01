@isolated
Feature: As a Developer
  I want to know in which scenario or example my script was running
  So that I can better trace where my changes caused a fatal error

  Scenario: Spec attempts to call an undeclared function
    Given the spec file "spec/Message/Fatal/FatalSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class FatalSpec extends ObjectBehavior
      {
          function it_throws_an_exception_using_magic_syntax()
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
    Then I should see "Fatal error happened while executing the following example"
    And  I should see "it throws an exception using magic syntax"

