Feature: Developer uses array-key-value matcher
  As a Developer
  I want an array-key-value matcher
  In order to confirm an array the expected value for a key

  Scenario: "HaveKeyWithValue" alias matches using the array-key-value matcher
    Given the spec file "spec/Matchers/ArrayKeyValueExample1/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\ArrayKeyValueExample1;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_jane_smith_in_the_cast()
        {
            $this->getCast()->shouldHaveKeyWithValue('leadRole', 'John Smith');
        }
    }
    """

    And the class file "src/Matchers/ArrayKeyValueExample1/Movie.php" contains:
    """
    <?php

    namespace Matchers\ArrayKeyValueExample1;

    class Movie
    {
        public function getCast()
        {
            return array('leadRole' => 'John Smith', 'supportingRole' => 'Jane Smith');
        }
    }
    """

    When I run phpspec
    Then the suite should pass
