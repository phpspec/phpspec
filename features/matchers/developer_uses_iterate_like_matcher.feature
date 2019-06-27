Feature: Developer uses iterate-as matcher
  As a Developer
  I want an iterate-like matcher
  In order to confirm an traversable the expected loosely-typed value for a key

  Scenario: "Iterate" alias matches using the iterate-like matcher
    Given the spec file "spec/Matchers/IterateLikeExample1/IterSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\IterateLikeExample1;

    use PhpSpec\ObjectBehavior;

    class IterSpec extends ObjectBehavior
    {
        function it_should_contain_object_in_the_elements()
        {
            $this->getElements()->shouldIterateLike([ (object)['foo' => 'bar'] ]);
        }
    }
    """
    And the class file "src/Matchers/IterateLikeExample1/Iter.php" contains:
    """
    <?php

    namespace Matchers\IterateLikeExample1;

    class Iter
    {
        public function getElements()
        {
            yield (object)['foo' => 'bar'];
        }
    }
    """
    When I run phpspec
    Then the suite should pass
