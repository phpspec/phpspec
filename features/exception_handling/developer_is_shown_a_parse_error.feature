Feature: Developer is shown a parse error
  As a Developer
  I want to know if a parse error was thrown
  So that I can know that I can handle pass errors

  @isolated @php-version @php5.4 @php7
  Scenario: Spec attempts to call an undeclared function and outputs to stderr
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

  @isolated @hhvm
  Scenario: Spec attempts to call an undeclared function and outputs to stdout
    Given the spec file "spec/Message/Fatal/ParseHhvmSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use Parse;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ParseHhvmSpec extends ObjectBehavior
      {
          function it_thro ws_a_syntax_error()
          {
              $this->cool();
          }
      }

      """
    And the spec file "src/Message/Fatal/ParseHhvm.php" contains:
      """
      <?php

      namespace Message\Parse;

      class ParseHhvm
      {
          public function cool()
          {
              return true;
          }
      }

      """
    When I run phpspec on HHVM with the "junit" formatter
    Then I should see "syntax error"
