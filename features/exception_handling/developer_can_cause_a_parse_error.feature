@isolated
Feature: Developer is notified of parse error
  As a Developer
  I want to know when a parse error was caused
  So that I can better trace errors in my specs

  Scenario: Spec attempts to call a mispelt function
    Given the isolated spec "spec/Message/Fatal/ParseSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ParseSpec extends ObjectBehavior
      {
          function it_fatals_when_calli ng_an_undeclared_function()
          {
              anything();
          }
      }

      """
    Then I should see the following parse error "syntax error, unexpected end of file"
