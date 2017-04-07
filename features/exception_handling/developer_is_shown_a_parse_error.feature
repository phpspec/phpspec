Feature: Developer is shown a parse error
  As a Developer
  I want to know if a parse error was thrown
  So that I can know that I can handle pass errors

  @php:~5.6 @isolated
  Scenario: Parse error in spec
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

  @php:~7.0
  Scenario: Parse error in class
    Given the spec file "spec/Message/Fatal/ParseSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use Parse;
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
    And the spec file "src/Message/Fatal/Parse.php" contains:
      """
      <?php

      namespace Message\Parse;

      class Par se
      {
          public function cool()
          {
              return true;
          }
      }

      """
    When I run phpspec
    Then I should see "1 broken"

  @php:~7.0
  Scenario: Parse error in spec
    Given the spec file "spec/Message/Fatal2/ParseSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal2;

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
    And the spec file "src/Message/Fatal2/Parse.php" contains:
      """
      <?php

      namespace Message\Parse2;

      class Parse
      {
          public function cool()
          {
              return true;
          }
      }

      """
    When I run phpspec
    Then I should see "1 broken"
    And I should see "syntax error"
