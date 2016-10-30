Feature: Developer uses close-float matcher
  As a Developer
  I want an close-float matcher
  In order to verify if two floats can be close

  @issue581
  Scenario: "CloseFloat" alias matches using the close-float matcher
    Given the spec file "spec/Matchers/FloatApproximatelyExample1/GeoCoordSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\FloatApproximatelyExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class GeoCoordSpec extends ObjectBehavior
    {
        function it_should_have_lat_approximate()
        {
            $this->getLat()->shouldBeACloseFloat(1.4477, 2);
        }
    }
    """

    And the class file "src/Matchers/FloatApproximatelyExample1/GeoCoord.php" contains:
    """
    <?php

    namespace Matchers\FloatApproximatelyExample1;

    class GeoCoord
    {
        public function getLat()
        {
            return 1.444444;
        }
    }
    """

    When I run phpspec
    Then the suite should pass