Feature: Developer uses extension
  As a Developer
  I want to use my extension

  Scenario: Extension can provide a new matcher
    Given the config file contains:
    """
    extensions:
      Example1\PhpSpec\MatcherExtension\Extension: ~
    """
    And the class file "src/Example1/PhpSpec/MatcherExtension/Extension.php" contains:
    """
    <?php

    namespace Example1\PhpSpec\MatcherExtension;

    use PhpSpec\Extension as PhpSpecExtension;
    use PhpSpec\ServiceContainer;

    class Extension implements PhpSpecExtension
    {
        public function load(ServiceContainer $container, array $params)
        {
            $container->define('matchers.seven', function (ServiceContainer $c) {
                return new BeSevenMatcher($c->get('formatter.presenter'));
            }, ['matchers']);
        }
    }

    """
    And the class file "src/Example1/PhpSpec/MatcherExtension/BeSevenMatcher.php" contains:
    """
    <?php

    namespace Example1\PhpSpec\MatcherExtension;

    use PhpSpec\Formatter\Presenter\Presenter;
    use PhpSpec\Exception\Example\FailureException;
    use PhpSpec\Matcher\BasicMatcher;

    class BeSevenMatcher extends BasicMatcher
    {
        /**
         * @var \PhpSpec\Formatter\Presenter\Presenter
         */
        private $presenter;

        /**
         * @param Presenter $presenter
         */
        public function __construct(Presenter $presenter)
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
        public function supports(string $name, $subject, array $arguments): bool
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
        protected function matches($subject, array $arguments): bool
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
        protected function getFailureException(string $name, $subject, array $arguments): FailureException
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
        protected function getNegativeFailureException(string $name, $subject, array $arguments): FailureException
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


  Scenario: Using an extension with an event listener
    Given the config file contains:
    """
    extensions:
      Example2\PhpSpec\Extensions\EventSubscriberExtension: ~
    """
    And the class file "src/Example2/PhpSpec/Extensions/EventSubscriberExtension.php" contains:
    """
    <?php

    namespace Example2\PhpSpec\Extensions;

    use PhpSpec\Extension as PhpSpecExtension;
    use PhpSpec\ServiceContainer;

    class EventSubscriberExtension implements PhpSpecExtension
    {
        public function load(ServiceContainer $compositeContainer, array $params)
        {
            $io = $compositeContainer->get('console.io');
            $eventDispatcher = $compositeContainer->get('event_dispatcher');
            $eventDispatcher->addSubscriber(new MyEventSubscriber($io));
        }
    }

    """
    And the class file "src/Example2/PhpSpec/Extensions/MyEventSubscriber.php" contains:
    """
    <?php

    namespace Example2\PhpSpec\Extensions;

    use PhpSpec\Event\SuiteEvent;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;

    class MyEventSubscriber implements EventSubscriberInterface
    {
        private $io;

        public function __construct($io)
        {
            $this->io = $io;
        }

        public static function getSubscribedEvents()
        {
            return ['afterSuite' => ['afterSuite', 11]];
        }

        public function afterSuite(SuiteEvent $event)
        {
            $this->io->writeln('Omg suite ran! :-)');
        }
    }

    """
    And the spec file "spec/Example2/DummySpec.php" contains:
    """
    <?php

    namespace spec\Example2;

    use PhpSpec\ObjectBehavior;
    use Example2\Dummy;

    class DummySpec extends ObjectBehavior
    {
        function it_is_initializable()
        {
            $this->shouldHaveType(Dummy::class);
        }
    }

    """
    And the class file "src/Example2/Dummy.php" contains:
    """
    <?php

    namespace Example2;

    class Dummy
    {
    }

    """
    When I run phpspec
    Then I should see "Omg suite ran! :-)"
