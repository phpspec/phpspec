Feature: Developer uses approximately matcher
  As a Developer
  I want an approximately matcher
  In order to verify if two floats can be close

  @issue581
  Scenario: "Approximately" alias matches using the approximately matcher
    Given the spec file "spec/Matchers/FloatApproximatelyExample1/GeoCoordSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatApproximatelyExample1;

      use PhpSpec\ObjectBehavior;

      class GeoCoordSpec extends ObjectBehavior
      {
          function it_should_have_lat_approximate()
          {
              $this->getLat()->shouldBeApproximately(1.4477, 1.0e-2);
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

  Scenario: Approximately matching an array
    Given the spec file "spec/Matchers/FloatApproximatelyExample2/GeoCoordSpec.php" contains:
      """
      <?php

      namespace spec\Matchers\FloatApproximatelyExample2;

      use PhpSpec\ObjectBehavior;

      class GeoCoordSpec extends ObjectBehavior
      {
          function it_should_have_lat_long_approximate()
          {
              $this->getLatLong()->shouldBeApproximately([1.4477, 2.3422], 1.0e-1);
          }
      }
      """
    And the class file "src/Matchers/FloatApproximatelyExample1/GeoCoord.php" contains:
      """
      <?php

      namespace Matchers\FloatApproximatelyExample2;

      class GeoCoord
      {
          public function getLatLong()
          {
            return [1.444444, 2.3321];
          }
      }
      """
    When I run phpspec with the verbose option
    Then the suite should pass
