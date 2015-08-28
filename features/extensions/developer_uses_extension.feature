Feature: Developer uses extension
  As a Developer
  I want to use my extension

  Scenario: Extension can provide a new matcher
    Given the config file contains:
    """
    extensions:
      - Example1\PhpSpec\MatcherExtension\Extension
    """
    And the class file "src/Example1/PhpSpec/MatcherExtension/Extension.php" contains:
    """
    <?php

    namespace Example1\PhpSpec\MatcherExtension;

    use PhpSpec\Extension\ExtensionInterface;
    use PhpSpec\ServiceContainer;

    class Extension implements ExtensionInterface
    {
        /**
         * @param ServiceContainer $container
         */
        public function load(ServiceContainer $container)
        {
            $container->set('matchers.seven', function (ServiceContainer $c) {
                return new BeSevenMatcher($c->get('formatter.presenter'));
            });
        }
    }

    """
    And the class file "src/Example1/PhpSpec/MatcherExtension/BeSevenMatcher.php" contains:
    """
    <?php

    namespace Example1\PhpSpec\MatcherExtension;

    use PhpSpec\Formatter\Presenter\PresenterInterface;
    use PhpSpec\Exception\Example\FailureException;
    use PhpSpec\Matcher\BasicMatcher;

    class BeSevenMatcher extends BasicMatcher
    {
        /**
         * @var \PhpSpec\Formatter\Presenter\PresenterInterface
         */
        private $presenter;

        /**
         * @param PresenterInterface $presenter
         */
        public function __construct(PresenterInterface $presenter)
        {
            $this->presenter = $presenter;
        }

        /**
         * @param string $name
         * @param mixed  $subject
         * @param array  $arguments
         *
         * @return bool
         */
        public function supports($name, $subject, array $arguments)
        {
            return 'beSeven' === $name
                && is_int($subject)
                && 0 == count($arguments)
            ;
        }

        /**
         * @param mixed $subject
         * @param array $arguments
         *
         * @return bool
         */
        protected function matches($subject, array $arguments)
        {
            return ($subject === 7);
        }

        /**
         * @param string $name
         * @param mixed  $subject
         * @param array  $arguments
         *
         * @return FailureException
         */
        protected function getFailureException($name, $subject, array $arguments)
        {
            return new FailureException(sprintf(
                'Seven expected %s to be 7, but it is not.',
                $this->presenter->presentString($subject)
            ));
        }

        /**
         * @param string $name
         * @param mixed  $subject
         * @param array  $arguments
         *
         * @return FailureException
         */
        protected function getNegativeFailureException($name, $subject, array $arguments)
        {
            return new FailureException(sprintf(
                'Seven did not expect %s to 7, but it is.',
                $this->presenter->presentString($subject)
            ));
        }
    }

    """
    And the spec file "spec/Example1/DummySpec.php" contains:
    """
    <?php

    namespace spec\Example1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class DummySpec extends ObjectBehavior
    {
        function it_is_initializable()
        {
            $this->shouldHaveType('Example1\Dummy');
        }
        function it_should_succeed_in_using_new_matcher()
        {
            $this->getSeven()->shouldBeSeven();
            $this->getFive()->shouldNotBeSeven();
        }
    }

    """
    And the class file "src/Example1/Dummy.php" contains:
    """
    <?php

    namespace Example1;

    class Dummy
    {
        public function getSeven()
        {
            return 7;
        }
        public function getFive()
        {
            return 5;
        }
    }

    """
    When I run phpspec
    Then the suite should pass
