Feature: Config directory can be used in spec and src paths
  As a Developer
  I need a path variable representing the config directory
  So I may use the directory of the config in spec and src paths and thus be able to run tests regardless of the current working directory

  Scenario: Using %paths.config% variable in spec_path
    Given the config file located in "Awesome" contains:
      """
      suites:
        behat_suite:
          namespace: MilkyWay\OrionCygnusArm
          spec_path: %paths.config%
      """
    When I start describing the "MilkyWay/OrionCygnusArm/LocalBubble" class with the "Awesome/phpspec.yml" custom config
    Then a new spec should be generated in the "Awesome/spec/MilkyWay/OrionCygnusArm/LocalBubbleSpec.php":
      """
      <?php

      namespace spec\MilkyWay\OrionCygnusArm;

      use MilkyWay\OrionCygnusArm\LocalBubble;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class LocalBubbleSpec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType(LocalBubble::class);
          }
      }

      """

  Scenario: Not using %paths.config% variable in spec_path
    Given the config file located in "Awesome" contains:
      """
      suites:
        behat_suite:
          namespace: MilkyWay\OrionCygnusArm
      """
    When I start describing the "MilkyWay/OrionCygnusArm/ButterflyCluster" class with the "Awesome/phpspec.yml" custom config
    Then a new spec should be generated in the "spec/MilkyWay/OrionCygnusArm/ButterflyClusterSpec.php":
      """
      <?php

      namespace spec\MilkyWay\OrionCygnusArm;

      use MilkyWay\OrionCygnusArm\ButterflyCluster;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ButterflyClusterSpec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType(ButterflyCluster::class);
          }
      }

      """

  Scenario: Using %paths.config% variable in src_path
    Given the config file located in "Awesome" contains:
      """
      suites:
        behat_suite:
          namespace: MilkyWay\OrionCygnusArm
          src_path: %paths.config%/src
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

  Scenario: Not using %paths.config% variable in src_path
    Given the config file located in "Awesome" contains:
      """
      suites:
        behat_suite:
          namespace: MilkyWay\OrionCygnusArm
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
