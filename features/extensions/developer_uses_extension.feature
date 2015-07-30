Feature: Developer uses extensions
  As a Developer
  I want to use my extension with vali
  In order to avoid spending a lot of time debugging I want to see a clear error message

  Scenario: Using an empty extension
    Given the config file contains:
    """
    extensions:
      - Example1\PhpSpec\EmptyExtension\Extension
    """

    And the class file "src/Example1/PhpSpec/EmptyExtension/Extension.php" contains:
    """
    <?php

    namespace Example1\PhpSpec\EmptyExtension;

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
    }
    """

    When I run phpspec
    Then the suite should pass


  Scenario: Using extension with correct matcher
    Given the config file contains:
    """
    extensions:
      - Example2\PhpSpec\IncorrectMatcherExtension\Extension
    """

    And the class file "src/Example2/PhpSpec/IncorrectMatcherExtension/Extension.php" contains:
    """
    <?php

    namespace Example2\PhpSpec\IncorrectMatcherExtension;

    use PhpSpec\Extension\ExtensionInterface;
    use PhpSpec\Extension\ValidatingExtensionTrait;
    use PhpSpec\ServiceContainer;
    use PhpSpec\Matcher\IdentityMatcher;

    class Extension implements ExtensionInterface
    {
        use ValidatingExtensionTrait;

        /**
         * @param ServiceContainer $container
         */
        public function load(ServiceContainer $container)
        {
          $this->setContainer($container);
          $this->set('matchers.anotherIdentityMatcher', function (ServiceContainer $c) {
              return new IdentityMatcher($c->get('formatter.presenter'));
          });
        }
    }
    """

    And the spec file "spec/Example2/DummySpec.php" contains:
    """
    <?php

    namespace spec\Example2;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class DummySpec extends ObjectBehavior
    {
    }
    """

    When I run phpspec
    Then the suite should pass

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
