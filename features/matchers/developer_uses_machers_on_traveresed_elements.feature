Feature: Developer uses matcher while traversing subject
  As a Developer
  I want to be able to use matchers on traveresed elements
  In order to more eloquently express my expectations of a subject

    @wip
    Scenario: "BeLike" alias matches using comparison operator
    Given the spec file "spec/Matchers/TraversalExample1/ProcessorSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversalExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class ProcessorSpec extends ObjectBehavior
    {
        function it_returns_an_array_of_integers()
        {
            $result = $this->process(array(1,2,3));

            foreach ($result as $element) {
                $element->shouldBeInteger();
            }

            $element->shouldBe(3);
        }
    }
    """

    And the class file "src/Matchers/TraversalExample1/Processor.php" contains:
    """
    <?php

    namespace Matchers\TraversalExample1;

    class Processor
    {
        public function process($arg)
        {
            return $arg;
        }
    }
    """

    When I run phpspec
    Then the suite should pass
