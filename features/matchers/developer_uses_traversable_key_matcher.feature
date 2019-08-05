Feature: Developer uses traversable-key matcher
  As a Developer
  I want an traversable-key matcher
  In order to confirm an array contains an expected key

  Scenario: "HaveKey" alias matches using the traversable-key matcher
    Given the spec file "spec/Matchers/TraversableKeyExample1/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversableKeyExample1;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_release_date_for_france()
        {
            $this->getReleaseDates()->shouldHaveKey('France');
        }
    }
    """
    And the class file "src/Matchers/TraversableKeyExample1/Movie.php" contains:
    """
    <?php

    namespace Matchers\TraversableKeyExample1;

    class Movie
    {
        public function getReleaseDates()
        {
            yield 'Australia' => '12 April 2013';
            yield 'France' => '24 April 2013';
        }
    }
    """
    When I run phpspec
    Then the suite should pass
