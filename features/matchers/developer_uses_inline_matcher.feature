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
              return ['bePositive' => function($subject) {
                  return $subject->getTotal() > 0;
              }];
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
