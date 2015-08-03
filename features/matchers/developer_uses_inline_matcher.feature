Feature: Developer uses identity matcher
  As a Developer
  I want an inline matcher
  So I can create expectations in a language closer to the domain I am describing

  Scenario: Inline matcher with no argument
    Given the spec file "spec/Matchers/InlineExample1/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\InlineExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_calculates_the_sum_of_two_addends()
          {
              $this->sum(1, 2);
              $this->shouldBePositive();
          }

          function getMatchers()
          {
              return array ('bePositive' => function($subject) {
                  return $subject->getTotal() > 0;
              });
          }

      }

      """
    And the class file "src/Matchers/InlineExample1/Calculator.php" contains:
      """
      <?php

      namespace Matchers\InlineExample1;

      class Calculator
      {
          private $total;

          public function sum($x, $y)
          {
              $this->total = $x + $y;
          }

          public function getTotal()
          {
              return $this->total;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: Inline matcher with an argument
    Given the spec file "spec/Matchers/InlineExample2/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\InlineExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_calculates_the_sum_of_two_addends()
          {
              $this->sum(1, 2);
              $this->shouldTotal(3);
          }

          function getMatchers()
          {
              return array ('total' => function($subject, $total) {
                  return $subject->getTotal() === $total;
              });
          }

      }

      """
    And the class file "src/Matchers/InlineExample2/Calculator.php" contains:
      """
      <?php

      namespace Matchers\InlineExample2;

      class Calculator
      {
          private $total;

          public function sum($x, $y)
          {
              $this->total = $x + $y;
          }

          public function getTotal()
          {
              return $this->total;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: Inline matcher throwing an exception
    Given the spec file "spec/Matchers/InlineExample3/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\InlineExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use PhpSpec\Exception\Example\FailureException;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_uses_inline_matcher_that_throws_an_exception()
          {
              $this->shouldDoSomething('abc');
          }

          function getMatchers()
          {
              return array ('doSomething' => function($subject, $param) {
                  throw new FailureException(sprintf(
                    'a very important message with subject "%s" and param "%s".',
                    $subject, $param
                ));
              });
          }

      }

      """
    And the class file "src/Matchers/InlineExample3/Calculator.php" contains:
      """
      <?php

      namespace Matchers\InlineExample3;

      class Calculator
      {
          public function __toString()
          {
              return 'Hi';
          }
      }

      """
    When I run phpspec
    Then the suite should not pass
    Then I should see "a very important message"

