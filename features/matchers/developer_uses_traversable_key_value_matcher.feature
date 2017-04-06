Feature: Developer uses traversable-key-value matcher
  As a Developer
  I want an traversable-key-value matcher
  In order to confirm an traversable the expected value for a key

  Scenario: "HaveKeyWithValue" alias matches using the traversable-key-value matcher
    Given the spec file "spec/Matchers/TraversableKeyValueExample1/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversableKeyValueExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_jane_smith_in_the_cast()
        {
            $this->getCast()->shouldHaveKeyWithValue('leadRole', 'John Smith');
        }
    }
    """
    And the class file "src/Matchers/TraversableKeyValueExample1/Movie.php" contains:
    """
    <?php

    namespace Matchers\TraversableKeyValueExample1;

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
