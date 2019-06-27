Feature: Developer uses traversable-count matcher
  As a Developer
  I want an traversable-count matcher
  In order to compare an array count against an expectation

  Scenario: "HaveCount" alias matches using the traversable-count matcher
    Given the spec file "spec/Matchers/TraversableCountExample1/CarSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversableCountExample1;

    use PhpSpec\ObjectBehavior;

    class CarSpec extends ObjectBehavior
    {
        function it_returns_the_number_of_wheels()
        {
            $this->getWheels()->shouldHaveCount(4);
        }
    }
    """
    And the class file "src/Matchers/TraversableCountExample1/Car.php" contains:
    """
    <?php

    namespace Matchers\TraversableCountExample1;

    class Car
    {
        public function getWheels()
        {
            yield 'wheel';
            yield 'wheel';
            yield 'wheel';
            yield 'wheel';
        }
    }
    """
    When I run phpspec
    Then the suite should pass
