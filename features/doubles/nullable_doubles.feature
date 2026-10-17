Feature: Nullable Doubles

  Collaborators with a nullable typehint are doubled using the underlying type

  Scenario: Creating a double from a nullable typehint
    Given the spec file "spec/Doubles/NullableDouble/HandlerSpec.php" contains:
       """
       <?php

       namespace spec\Doubles\NullableDouble;

       use PhpSpec\ObjectBehavior;

       class HandlerSpec extends ObjectBehavior
       {
            function it_creates_a_double_from_a_nullable_typehint(?\ArrayObject $double)
            {
                if (!$double->getWrappedObject() instanceof \ArrayObject) {
                    throw new \Exception('The nullable collaborator was not doubled as ArrayObject');
                }
            }
       }
       """
    And the class file "src/Doubles/NullableDouble/Handler.php" contains:
       """
       <?php

       namespace Doubles\NullableDouble;

       class Handler
       {
       }
       """
    When I run phpspec
    Then the suite should pass

  Scenario: Loading a spec that has a nullable typehint on a private helper method
    Given the spec file "spec/Doubles/NullableHelper/CalendarSpec.php" contains:
       """
       <?php

       namespace spec\Doubles\NullableHelper;

       use PhpSpec\ObjectBehavior;

       class CalendarSpec extends ObjectBehavior
       {
            function it_is_initializable()
            {
                $this->shouldHaveType(\Doubles\NullableHelper\Calendar::class);
            }

            private function helper(?\DateTimeImmutable $date = null): void
            {
            }
       }
       """
    And the class file "src/Doubles/NullableHelper/Calendar.php" contains:
       """
       <?php

       namespace Doubles\NullableHelper;

       class Calendar
       {
       }
       """
    When I run phpspec
    Then the suite should pass
