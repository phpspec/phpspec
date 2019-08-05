Feature: Developer uses start-iterate-as matcher
  As a Developer
  I want an start-iterate-as matcher
  In order to confirm an traversable the expected value for a key

  Scenario: "StartIterating" alias matches using the start-iterate-as matcher
    Given the spec file "spec/Matchers/StartIteratingExample1/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\StartIteratingExample1;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_jane_smith_in_the_cast()
        {
            $this->getCast()->shouldStartIteratingAs(['supportingRole' => 'Jane Smith', 'leadRole' => 'John Smith']);
        }
    }
    """
    And the class file "src/Matchers/StartIteratingExample1/Movie.php" contains:
    """
    <?php

    namespace Matchers\StartIteratingExample1;

    class Movie
    {
        public function getCast()
        {
            yield 'supportingRole' => 'Jane Smith';
            yield 'leadRole' => 'John Smith';
            yield 'supportingRole' => 'Will Smith';
        }
    }
    """
    When I run phpspec
    Then the suite should pass
