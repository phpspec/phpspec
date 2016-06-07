Feature: Developer is shown a parse error
  As a Developer
  I want to know if a parse error was thrown
  So that I can know that I can handle pass errors

  @isolated @php:~5.4||~7.0
  Scenario: Spec attempts to call an undeclared function
    Given the spec file "spec/Message/Fatal/ParseSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use Parse;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ParseSpec extends ObjectBehavior
      {
          function it_thro ws_a_syntax_error()
          {
              $this->cool();
          }
      }

      """
    And the spec file "src/Message/Fatal/Parse.php" contains:
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
    When I run phpspec with the "junit" formatter
    Then I should see "syntax error"
