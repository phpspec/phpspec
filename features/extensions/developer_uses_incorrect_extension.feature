@php-version @php5.4
Feature: Developer uses incorrect extension
  As a Developer
  I want to use my extension
  In order to avoid spending a lot of time debugging I want to see a clear error message

  Scenario: Using extension with incorrect matcher
    Given the config file contains:
    """
    extensions:
      - Example3\PhpSpec\IncorrectMatcherExtension\Extension
    """

    And the class file "src/Example3/PhpSpec/IncorrectMatcherExtension/Extension.php" contains:
    """
    <?php

    namespace Example3\PhpSpec\IncorrectMatcherExtension;

    use PhpSpec\Extension\ExtensionInterface;
    use PhpSpec\Extension\ValidatingExtensionTrait;
    use PhpSpec\ServiceContainer;

    class Extension implements ExtensionInterface
    {
        use ValidatingExtensionTrait;

        /**
         * @param ServiceContainer $container
         */
        public function load(ServiceContainer $container)
        {
          $this->setContainer($container);
          $this->set('matchers.doSomething', function (ServiceContainer $c) {
              return new \StdClass();
          });
        }
    }
    """

    And the spec file "spec/Example3/DummySpec.php" contains:
    """
    <?php

    namespace spec\Example3;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class DummySpec extends ObjectBehavior
    {
    }
    """

    When I run phpspec
    Then the suite should not pass
