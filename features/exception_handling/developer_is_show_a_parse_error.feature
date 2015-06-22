@isolated
Feature: Developer is shown a parse error
  As a Developer
  I want to know if a parse error was thrown
  So that I can know that I can handle pass errors

  Scenario: Spec attempts to call an undeclared function
    Given the isolated file "spec/Message/Fatal/ParseSpec.php" contains:
      """
      <?php

      namespace spec\Message\Parse;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ParseSpec extends ObjectBehavior
      {
          function it_throws_a_syntax_error()
          {
              $this->cool();
          }
      }

      """
    And the isolated file "src/Message/Fatal/Parse.php" contains:
      """
      <?php

      namespace Message\Parse;

      class Parse
      {
          public function cool()
          {
              return true;
          }
      }

      """
    When I run phpspec
    Then I should see "Parse Error: "
