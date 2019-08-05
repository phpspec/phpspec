Feature: Developer uses throw matcher
  As a Developer
  I want a throw matcher
  In order to validate objects exceptions against my expectations

  Scenario: "Throw" alias matches using the throw matcher with explicit method name
    Given the spec file "spec/Matchers/ThrowExample1/EmployeeSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\ThrowExample1;

    use PhpSpec\ObjectBehavior;

    class EmployeeSpec extends ObjectBehavior
    {
        function it_throws_an_exception_when_arguments_are_invalid()
        {
            $this->shouldThrow('\InvalidArgumentException')->during('setAge', array(0));
        }
    }
    """

    And the class file "src/Matchers/ThrowExample1/Employee.php" contains:
    """
    <?php

    namespace Matchers\ThrowExample1;

    class Employee
    {
        public function setAge($age)
        {
            if (0 === $age) {
                throw new \InvalidArgumentException();
            }
        }
    }
    """

    When I run phpspec
    Then the suite should pass

  Scenario: "Throw" alias matches using the throw matcher with implicit method name
    Given the spec file "spec/Matchers/ThrowExample2/EmployeeSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\ThrowExample2;

    use PhpSpec\ObjectBehavior;

    class EmployeeSpec extends ObjectBehavior
    {
        function it_throws_an_exception_when_arguments_are_invalid()
        {
            $this->shouldThrow('\InvalidArgumentException')->duringSetAge(0);
        }
    }
    """

    And the class file "src/Matchers/ThrowExample2/Employee.php" contains:
    """
    <?php

    namespace Matchers\ThrowExample2;

    class Employee
    {
        public function setAge($age)
        {
            if (0 === $age) {
                throw new \InvalidArgumentException();
            }
        }
    }
    """

    When I run phpspec
    Then the suite should pass


  Scenario: "Throw" alias matches using the throw matcher with specific exception message
    Given the spec file "spec/Matchers/ThrowExample3/EmployeeSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\ThrowExample3;

    use PhpSpec\ObjectBehavior;

    class EmployeeSpec extends ObjectBehavior
    {
        function it_throws_an_exception_when_arguments_are_invalid()
        {
            $this->shouldThrow(new \InvalidArgumentException('Invalid age'))->duringSetAge(0);
        }
    }
    """

    And the class file "src/Matchers/ThrowExample3/Employee.php" contains:
    """
    <?php

    namespace Matchers\ThrowExample3;

    class Employee
    {
        public function setAge($age)
        {
            if (0 === $age) {
                throw new \InvalidArgumentException('Invalid age');
            }
        }
    }
    """

    When I run phpspec
    Then the suite should pass

  @issue134
  Scenario: Throwing an exception during object construction
    Given the spec file "spec/Runner/ThrowExample4/MarkdownSpec.php" contains:
    """
    <?php

    namespace spec\Runner\ThrowExample4;

    use PhpSpec\ObjectBehavior;

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
    And the class file "src/Runner/ThrowExample4/Markdown.php" contains:
    """
    <?php

    namespace Runner\ThrowExample4;

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

  @issue610
  Scenario: Throwing an exception during object construction
    Given the spec file "spec/Runner/ThrowExample5/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ThrowExample5;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_throws_an_exception_using_during_instantiation_syntax()
          {
              $this->beConstructedWith(1, 2);
              $this->shouldThrow('\InvalidArgumentException')->duringInstantiation();
          }

          function it_throws_an_exception_using_during_named_instantiation_syntax()
          {
              $this->beConstructedThrough('defaultNumber2', array(1));
              $this->shouldThrow('\InvalidArgumentException')->duringInstantiation();
          }
      }

      """
    And the class file "src/Runner/ThrowExample5/Markdown.php" contains:
      """
      <?php

      namespace Runner\ThrowExample5;

      class Markdown
      {
          public function __construct($num1, $num2)
          {
              throw new \InvalidArgumentException();
          }

          public static function defaultNumber2($num1, $num2 = 2)
          {
              return new self($num1, $num2);
          }
      }

      """
    When I run phpspec
    Then the suite should pass

  Scenario: Throw matcher supports Error
    Given the spec file "spec/Runner/ThrowExample6/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ThrowExample6;

      use PhpSpec\ObjectBehavior;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_throws_error_during_division_by_zero()
          {
              $this->shouldThrow(new \DivisionByZeroError())->duringDivide(10, 0);
          }
      }

      """
    And the class file "src/Runner/ThrowExample6/Calculator.php" contains:
      """
      <?php

      namespace Runner\ThrowExample6;

      class Calculator
      {
          public function divide(int $dividend, int $divider): float
          {
              if ($divider === 0) {
                  throw new \DivisionByZeroError();
              }

              return $dividend / $divider;
          }
      }

      """
    When I run phpspec
    Then the suite should pass
