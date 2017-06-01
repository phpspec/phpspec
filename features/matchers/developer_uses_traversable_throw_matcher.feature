Feature: Developer uses traversable-throw matcher
  As a Developer
  I want a traversable-throw matcher
  In order to confirm that an exception can be thrown when iterating on a Traversable object

  Scenario: "ThrowWhenIterating" alias matches using the traversable-throw matcher
    Given the spec file "spec/Matchers/TraversableThrowExample1/TraversableObjectSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversableThrowExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class TraversableObjectSpec extends ObjectBehavior
    {
        function it_should_throw_an_exception()
        {
            $this->shouldThrowWhenIterating('\RuntimeException');
        }
    }
    """
    And the class file "src/Matchers/TraversableThrowExample1/TraversableObject.php" contains:
    """
    <?php

    namespace Matchers\TraversableThrowExample1;

    class TraversableObject implements \IteratorAggregate
    {
        public function getIterator()
        {
            yield 1;
            yield 2;
            throw new \RuntimeException();
        }
    }
    """
    When I run phpspec
    Then the suite should pass
