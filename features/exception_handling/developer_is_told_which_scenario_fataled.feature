Feature: As a Developer
  I want to know in which scenario or example my script was running
  So that I can better trace where my changes caused a fatal error

  Scenario: Spec attempts to call an undeclared function
    Given the spec file "spec/Runner/ExceptionExample3/MarkdownSpec.php" contains:
    """
    <?php

    namespace spec\Runner\ExceptionExample3;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class MarkdownSpec extends ObjectBehavior
    {
        function let()
        {
            $this->beConstructedWith('nothrow');
        }

        function it_throws_an_exception_using_magic_syntax()
        {
            $this->shouldThrow('Exception')->during__construct('throw');
        }
    }
    """
	And the class file "src/Runner/ExceptionExample3/Markdown.php" contains:
    """
      <?php

      namespace Runner\ExceptionExample3;

      class Markdown
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
    Then I should see "it causes a fatal error"

  Scenario: No message displayed when the run is successful
    Given the spec file "spec/Message/ProcessSpec/Limit2.php" contains:
    """
      <?php

      namespace spec\Message\ProcessSpec;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class Limit2Spec extends ObjectBehavior
      {
          function let()
          {
              $this->beConstructedWith('nothrow');
          }

          function it_causes_a_fatal_error()
          {
    :          $this->callMe();
          }
      }

      """
    And the class file "src/Message/Process/Limit2.php" contains:
    """
      <?php

      namespace src\Message\Process;

      class Limit2 {
          public function callMe()
          {
              return true;
          }
      }
    """
    When I run phpspec
    Then the suite should pass
