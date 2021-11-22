Feature: Display ignored resources using the progress formatter
  In order to know and understand why some specs are not executed
  As a developer
  I should be shown a counter and a list of ignored specs while using the progress formatter

  Background:
    Given the config file located in "." contains:
    """
    composer_suite_detection: true
    """
    And there is a PSR-4 namespace "MilkyWay\OrionCygnusArm\" configured for the "src" folder
    And the spec file "spec/MilkyWay/OrionCygnusArm/MyClassSpec.php" contains:
    """
    <?php

    namespace spec\MilkyWay\OrionCygnusArm;

    use MilkyWay\OrionCygnusArm\MyClass;
    use PhpSpec\ObjectBehavior;

    class MyClassSpec extends ObjectBehavior
    {
      function it_is_initializable()
      {
        $this->shouldHaveType(MyClass::class);
      }
    }
    """

  Scenario: Non verbose output
    When I run phpspec
    Then I should see:
      """
      1 ignored
      """

  Scenario: Verbose output
    When I run phpspec with the "verbose" option
    Then I should see:
      """
      1 ignored
        ! spec\MilkyWay\OrionCygnusArm\MyClassSpec could not be loaded at path
      """
