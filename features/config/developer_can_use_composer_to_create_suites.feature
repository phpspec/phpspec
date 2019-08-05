Feature: Composer can be leveraged to create suites
  As a Developer
  I need the autoload rules I defined to be reused by phpspec
  So I may enable the Composer namespace provider and get one suite per autoload rule


  Scenario: Using composer as namespace_provider in a PSR0 namespace
    Given the config file located in "." contains:
      """
      composer_suite_detection: true
      """
      And there is a PSR-0 namespace "Andromeda\N4S4Arm\" configured for the "src" folder
    When I start describing the "Andromeda/N4S4Arm/Gazorpazorp" class with the "phpspec.yml" custom config
    Then a new spec should be generated in the "spec/Andromeda/N4S4Arm/GazorpazorpSpec.php":
      """
      <?php

      namespace spec\Andromeda\N4S4Arm;

      use Andromeda\N4S4Arm\Gazorpazorp;
      use PhpSpec\ObjectBehavior;

      class GazorpazorpSpec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType(Gazorpazorp::class);
          }
      }

      """

  Scenario: Using composer as namespace_provider in a PSR4 namespace
    Given the config file located in "." contains:
      """
      composer_suite_detection : true
      """
      And there is a PSR-4 namespace "MilkyWay\OrionCygnusArm\" configured for the "src" folder
    When I start describing the "MilkyWay/OrionCygnusArm/LocalBubble" class with the "phpspec.yml" custom config
    Then a new spec should be generated in the "spec/LocalBubbleSpec.php":
      """
      <?php

      namespace spec\MilkyWay\OrionCygnusArm;

      use MilkyWay\OrionCygnusArm\LocalBubble;
      use PhpSpec\ObjectBehavior;

      class LocalBubbleSpec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType(LocalBubble::class);
          }
      }

      """
