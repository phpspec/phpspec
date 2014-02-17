Feature: Developer specifies exception behaviour
  As a Developer
  I want to be able to specify the exceptions by SUS will throw
  In order to drive the design of my exception handling

  Scenario: Throwing an exception in a method
    Given the spec file "spec/Runner/ExceptionExample1/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ExceptionExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_throws_an_exception_using_during_syntax()
          {
              $this->shouldThrow('Exception')->during('foo', array());
          }

          function it_throws_an_exception_using_magic_syntax()
          {
              $this->shouldThrow('Exception')->duringFoo();
          }
      }

      """
    And the class file "src/Runner/ExceptionExample1/Markdown.php" contains:
      """
      <?php

      namespace Runner\ExceptionExample1;

      class Markdown
      {
          public function foo()
          {
              throw new \Exception();
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  @issue134
  Scenario: Throwing an exception during object construction
    Given the spec file "spec/Runner/ExceptionExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ExceptionExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_throws_an_exception_using_during_syntax()
          {
              $this->shouldThrow('Exception')->during('__construct', array(1,2));
          }

          function it_throws_an_exception_using_magic_syntax()
          {
              $this->shouldThrow('Exception')->during__construct(1,2);
          }
      }

      """
    And the class file "src/Runner/ExceptionExample2/Markdown.php" contains:
      """
      <?php

      namespace Runner\ExceptionExample2;

      class Markdown
      {
          public function __construct($num1, $num2)
          {
              throw new \Exception();
          }
      }

      """
    When I run phpspec
    Then the suite should pass


  Scenario: Throwing an exception during construction when beConstructedWith specifies valid parameters
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
    Then the suite should pass