Feature: Paths can be relative to config or current working directory
    As a Developer
    I want to be able to specify if paths are relative to the directory of the config or current working directory
    So I may specify a custom location for the config file

    Scenario: Generating a class using a custom config with path being relative to the config directory
        Given the config file located in "Awesome" contains:
            """
            suites:
              behat_suite:
                namespace: MilkyWay\OrionCygnusArm
                use_config_path: true
            """
        And I have started describing the "MilkyWay/OrionCygnusArm/Pleiades/Alcyone" class with the "Awesome/phpspec.yml" custom config
        When I run phpspec with the "Awesome/phpspec.yml" custom config and answer "y" when asked if I want to generate the code
        Then a new class should be generated in the "Awesome/src/MilkyWay/OrionCygnusArm/Pleiades/Alcyone.php":
            """
            <?php

            namespace MilkyWay\OrionCygnusArm\Pleiades;

            class Alcyone
            {
            }

            """

    Scenario: Generating a class using a custom config with path being relative to the current working directory
        Given the config file located in "Awesome" contains:
            """
            suites:
              behat_suite:
                namespace: MilkyWay\OrionCygnusArm
                use_config_path: false
            """
        And I have started describing the "MilkyWay/OrionCygnusArm/BehiveCluster" class with the "Awesome/phpspec.yml" custom config
        When I run phpspec with the "Awesome/phpspec.yml" custom config and answer "y" when asked if I want to generate the code
        Then a new class should be generated in the "src/MilkyWay/OrionCygnusArm/BehiveCluster.php":
            """
            <?php

            namespace MilkyWay\OrionCygnusArm;

            class BehiveCluster
            {
            }

            """

    Scenario: Generating a spec using a custom config with path being relative to the config directory
        Given the config file located in "Awesome" contains:
            """
            suites:
              behat_suite:
                namespace: MilkyWay\OrionCygnusArm
                use_config_path: true
            """
        When I start describing the "MilkyWay/OrionCygnusArm/LocalBubble" class with the "Awesome/phpspec.yml" custom config
        Then a new spec should be generated in the "Awesome/spec/MilkyWay/OrionCygnusArm/LocalBubbleSpec.php":
            """
            <?php

            namespace spec\MilkyWay\OrionCygnusArm;

            use PhpSpec\ObjectBehavior;
            use Prophecy\Argument;

            class LocalBubbleSpec extends ObjectBehavior
            {
                function it_is_initializable()
                {
                    $this->shouldHaveType('MilkyWay\OrionCygnusArm\LocalBubble');
                }
            }

            """