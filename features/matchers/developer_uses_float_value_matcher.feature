Feature: Developer uses float value matcher
  As a Developer
  I want a float value matcher
  In order to match the float value of a number against an expectation

  Scenario: "Return" alias matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample1/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldReturn(0.2);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample1/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample1;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Return" alias not matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample2/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotReturn(0);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample2/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample2;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Return" alias not matching type using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample3/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotReturn("0.2");
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample3/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample3;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Be" alias matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample4/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample4;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldBe(0.2);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample4/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample4;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Be" alias not matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample5/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample5;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotBe(0);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample5/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample5;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Be" alias not matching type using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample6/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample6;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotBe("0.2");
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample6/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample6;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Equal" alias matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample7/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample7;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldEqual(0.2);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample7/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample7;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Equal" alias not matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample8/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample8;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotEqual(0);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample8/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample8;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Equal" alias not matching type using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample9/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample9;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotEqual("0.2");
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample9/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample9;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "BeEqualTo" alias matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample10/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample10;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldEqual(0.2);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample10/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample10;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Equal" alias not matching using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample11/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample11;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotEqual(0);
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample11/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample11;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: "Equal" alias not matching type using float value matcher
    Given the spec file "spec/Matchers/FloatValueExample12/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatValueExample12;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_subtracts_two_numbers()
          {
              $this->subtract(1.2, 1)->shouldNotEqual("0.2");
          }
      }

      """
    And the class file "src/Matchers/FloatValueExample12/Calculator.php" contains:
      """
      <?php

      namespace Matchers\FloatValueExample12;

      class Calculator
      {
          public function subtract($x, $y)
          {
              return $x - $y;
          }
      }

      """
    When I run phpspec
    Then the suite should pass
