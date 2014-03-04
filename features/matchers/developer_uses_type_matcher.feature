Feature: Developer uses comparison matcher
  As a Developer
  I want a type matcher
  In order to confirm that my object is of a given type

  Scenario: "HaveType" alias matches using the type matcher
    Given the spec file "spec/Matchers/TypeExample1/CarSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TypeExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class CarSpec extends ObjectBehavior
    {
        function let()
        {
            $this->beConstructedWith();
        }

        function it_should_be_a_car()
        {
            $this->shouldHaveType('Matchers\TypeExample1\Car');
        }
    }
    """

    And the class file "src/Matchers/TypeExample1/Car.php" contains:
    """
    <?php

    namespace Matchers\TypeExample1;

    class Car
    {
        public function __construct()
        {
        }
    }
    """

    When I run phpspec
    Then the suite should pass


  Scenario: "ReturnAnInstanceOf" alias matches using the type matcher
    Given the spec file "spec/Matchers/TypeExample1/BigCarSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TypeExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class BigCarSpec extends ObjectBehavior
    {
        function it_should_be_a_car()
        {
            $this->shouldReturnAnInstanceOf('Matchers\TypeExample1\BigCar');
        }
    }
    """

    And the class file "src/Matchers/TypeExample1/BigCar.php" contains:
    """
    <?php

    namespace Matchers\TypeExample1;

    class BigCar
    {
        public function get()
        {
            return $this;
        }


    }
    """

    When I run phpspec
    Then the suite should pass