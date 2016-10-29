Feature: Developer uses traversable-contain matcher
  As a Developer
  I want an traversable-contain matcher
  In order to confirm an traversable contains an expected value

  Scenario: "Contain" alias matches using the traversable-contain matcher
    Given the spec file "spec/Matchers/TraversableContainExample1/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversableContainExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_jane_smith_in_the_cast()
        {
            $this->getCast()->shouldContain('Jane Smith');
        }
    }
    """
    And the class file "src/Matchers/TraversableContainExample1/Movie.php" contains:
    """
    <?php

    namespace Matchers\TraversableContainExample1;

    class Movie
    {
        public function getCast()
        {
            yield 'John Smith';
            yield 'Jane Smith';
        }
    }
    """
    When I run phpspec
    Then the suite should pass
