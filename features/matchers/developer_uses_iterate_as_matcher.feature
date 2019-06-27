Feature: Developer uses iterate-as matcher
  As a Developer
  I want an iterate-as matcher
  In order to confirm an traversable the expected value for a key

  Scenario: "Iterate" alias matches using the iterate-as matcher
    Given the spec file "spec/Matchers/IterateExample1/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\IterateExample1;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_jane_smith_in_the_cast()
        {
            $this->getCast()->shouldIterateAs(['supportingRole' => 'Jane Smith', 'leadRole' => 'John Smith']);
        }
    }
    """
    And the class file "src/Matchers/IterateExample1/Movie.php" contains:
    """
    <?php

    namespace Matchers\IterateExample1;

    class Movie
    {
        public function getCast()
        {
            yield 'supportingRole' => 'Jane Smith';
            yield 'leadRole' => 'John Smith';
        }
    }
    """
    When I run phpspec
    Then the suite should pass
