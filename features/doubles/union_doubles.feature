Feature: Union Doubles

  Currently these are not supported so we show an appropriate message

  Scenario: Error when using a union double
    Given the spec file "spec/Doubles/UnionDoublesSpec.php" contains:
       """
       <?php

       namespace spec\Doubles;

       use PhpSpec\ObjectBehavior;

       class UnionDoublesSpec extends ObjectBehavior
       {
            function it_does_something_with_a_double(\stdClass|\ArrayObject $double)
            {
                return;
            }
       }
       """
    When I run phpspec
    Then I should see "union type \stdClass|\ArrayObject cannot be used to create a double"
