Feature: Intersection Doubles

  Currently these are not supported so we show an appropriate message

  Scenario: Error when using a intersection double
    Given the spec file "spec/Doubles/IntersectionDoublesSpec.php" contains:
       """
       <?php

       namespace spec\Doubles;

       use PhpSpec\ObjectBehavior;

       class IntersectionDoublesSpec extends ObjectBehavior
       {
            function it_does_something_with_a_double(\stdClass&\ArrayObject $double)
            {
                return;
            }
       }
       """
    When I run phpspec
    Then I should see "intersection type \stdClass&\ArrayObject cannot be used to create a double"
