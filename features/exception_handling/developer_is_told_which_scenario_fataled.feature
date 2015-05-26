Feature: As a Developer
  I want to know in which scenario or example my script was running
  So that I can better trace where my changes caused a fatal error

  Scenario: Spec attempts to call an undeclared function
    Given the spec file "spec/Message/Fatal3/FatalSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class FatalSpec extends ObjectBehavior
      {
          function let()
          {
              $this->beConstructedWith('nothrow');
          }

          function it_throws_an_exception_using_magic_syntax()
          {
              $this->callAny();
          }
      }

      """
    And the class file "src/Message/Fatal3/Fatal.php" contains:
      """
      <?php

      namespace Message\Fatal3;

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
    Then I should see "it throws an exception using magic syntax"

  Scenario: No message displayed when the run is successful 
    Given the spec file "spec/Message/Fatal3/NonFatalSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class NonFatalSpec extends ObjectBehavior
      {
          function let()
          {
              $this->beConstructedWith('nothrow');
          }

          function it_throws_an_exception_using_magic_syntax()
          {
              $this->callAny();
          }
      }

      """
    And the class file "src/Message/Fatal3/Fatal.php" contains:
      """
      <?php

      namespace Message\Fatal3;

      class NonFatal
      {
          public function __construct($param)
          {
              if ($param == 'throw') {
                  throw new \Exception();
              }
          }

          public function callAny()
          {
              return true;
          }
      }

      """
    When I run phpspec
    Then the suite should pass
