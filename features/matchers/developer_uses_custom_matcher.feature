Feature: Developer uses custom matcher
  As a Developer
  I want a custom matcher
  In order to confirm any custom assertion I need

  Scenario: Succesfully register a custom matcher
    Given the spec file "spec/Matchers/Custom/MovieSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\Custom;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_some_specific_options_by_default()
        {
            $this->getEntries()->shouldTotalize(130);
        }
    }
    """

    And the class file "src/Matchers/Custom/TotalizeMatcher.php" contains:
    """
    <?php

    namespace Matchers\Custom;

    use PhpSpec\Matcher\BasicMatcher;
    use PhpSpec\Exception\Example\FailureException;

    class TotalizeMatcher extends BasicMatcher
    {
        public function supports($name, $subject, array $arguments)
        {
            return 'totalize' === $name &&
                is_array($subject) &&
                isset($arguments[0]) &&
                is_int($arguments[0])
            ;
        }

        protected function matches($subject, array $arguments)
        {
            return array_sum($subject) === $arguments[0];
        }

        protected function getFailureException($name, $subject, array $arguments)
        {
            return new FailureException(sprintf(
                'Expected to totalize %d, but got %d.',
                $arguments[0],
                array_sum($subject)
            ));
        }

        protected function getNegativeFailureException($name, $subject, array $arguments)
        {
            return new FailureException(sprintf(
                'Expected to not totalize %d, but it does.',
                $arguments[0]
            ));
        }
    }
    """

    And the class file "src/Matchers/Custom/Movie.php" contains:
    """
    <?php

    namespace Matchers\Custom;

    class Movie
    {
        public function getEntries()
        {
            return [100, 10, 20];
        }
    }
    """

    And the config file contains:
    """
    matchers:
        - Matchers\Custom\TotalizeMatcher
    """
    When I run phpspec
    Then the suite should pass

  Scenario: Developer adds class that is not Matcher to custom matchers list
    Given the config file contains:
      """
      matchers:
          - ArrayObject
      """
    When I run phpspec
    Then I should see "Custom matcher ArrayObject must implement PhpSpec\Matcher\Matcher interface, but it does not"
