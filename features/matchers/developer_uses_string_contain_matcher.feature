Feature: Developer uses string-contain matcher
  As a Developer
  I want a string-contain matcher
  In order to confirm a string contains an expected substring

  Scenario: "Contain" alias matches using the string-contain matcher
    Given the spec file "spec/Matchers/StringContainExample1/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\StringContainExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_title_that_contains_days()
        {
            $this->getTitle()->shouldContain('days');
        }
    }
    """

    And the class file "src/Matchers/StringContainExample1/Movie.php" contains:
    """
    <?php

    namespace Matchers\StringContainExample1;

    class Movie
    {
        public function getTitle()
        {
            return 'The future days of past';
        }
    }
    """

    When I run phpspec
    Then the suite should pass
