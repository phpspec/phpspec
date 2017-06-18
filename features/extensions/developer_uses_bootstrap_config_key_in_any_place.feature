Feature: Developer uses bootstrap config key in any place
  As a Developer
  I want to place configuration options at any part of the config file

  Scenario: Extension does not break container parameters
    Given the config file contains:
    """
    extensions:
      - Example1\PhpSpec\LoadsConsoleIoExtension\Extension

    bootstrap: NotExisting.php
    """
    And the class file "src/Example1/PhpSpec/LoadsConsoleIoExtension/Extension.php" contains:
    """
    <?php

    namespace Example1\PhpSpec\LoadsConsoleIoExtension;

    use PhpSpec\Extension\ExtensionInterface as PhpSpecExtension;
    use PhpSpec\ServiceContainer;

    class Extension implements PhpSpecExtension
    {
        public function load(ServiceContainer $container)
        {
            $container->get('console.io');
        }
    }

    """
    And the spec file "spec/Example1/TestConfigSpec.php" contains:
    """
    <?php

    namespace spec\Example1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class TestConfigSpec extends ObjectBehavior
    {
        function it_is_initializable()
        {
            $this->shouldHaveType('Example1\TestConfig');
        }
    }

    """
    And the class file "src/Example1/TestConfig.php" contains:
    """
    <?php

    namespace Example1;

    class TestConfig
    {
    }

    """
    When I run phpspec
    Then I should see "Bootstrap file 'NotExisting.php' does not exist"
