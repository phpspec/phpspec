Feature: Developer uses trigger matcher
  As a Developer
  I want a trigger matcher
  In order to validate triggered exceptions against my expectations

  Scenario: Checking if a deprecated error has been triggered
    Given the spec file "spec/Matchers/TriggerExample1/FooSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TriggerExample1;

    use PhpSpec\ObjectBehavior;

    class FooSpec extends ObjectBehavior
    {
        function it_triggers_an_error_when_calling_something_deprecated()
        {
            $this->shouldTrigger(E_USER_DEPRECATED)->duringDoDeprecatedStuff();
        }
    }
    """
    And the class file "src/Matchers/TriggerExample1/Foo.php" contains:
    """
    <?php

    namespace Matchers\TriggerExample1;

    class Foo
    {
        public function doDeprecatedStuff()
        {
            trigger_error('Foo', E_USER_DEPRECATED);
        }
    }
    """
    When I run phpspec
    Then the suite should pass

  Scenario: Checking that a deprecated error has the right message
    Given the spec file "spec/Matchers/TriggerExample2/FooSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TriggerExample3;

    use PhpSpec\ObjectBehavior;

    class FooSpec extends ObjectBehavior
    {
        function it_triggers_a_specific_deprecated_error_when_calling_deprecated_method()
        {
            $this->shouldTrigger(E_USER_DEPRECATED, 'This is deprecated')->duringDoDeprecatedStuff();
        }
    }
    """
    And the class file "src/Matchers/TriggerExample2/Foo.php" contains:
    """
    <?php

    namespace Matchers\TriggerExample2;

    class Foo
    {
        public function doDeprecatedStuff()
        {
            trigger_error('This is deprecated', E_USER_DEPRECATED);
        }
    }
    """
    When I run phpspec
    Then the suite should pass

  Scenario: "Trigger" alias matches using the trigger matcher and let the code continue afterwards
    Given the spec file "spec/Matchers/TriggerExample3/FooSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TriggerExample3;

    use PhpSpec\ObjectBehavior;

    class FooSpec extends ObjectBehavior
    {
        function it_triggers_a_deprecated_error_when_calling_deprecated_method_but_do_not_interrupt()
        {
            $this->shouldTrigger(E_USER_DEPRECATED, 'This is deprecated')->duringDoDeprecatedStuff(0);
            $this->getDeprecated()->shouldBe(0);
        }
    }
    """
    And the class file "src/Matchers/TriggerExample3/Foo.php" contains:
    """
    <?php

    namespace Matchers\TriggerExample3;

    class Foo
    {
        private $deprecated;

        public function getDeprecated()
        {
            return $this->deprecated;
        }

        public function doDeprecatedStuff($value)
        {
            trigger_error('This is deprecated', E_USER_DEPRECATED);
            $this->deprecated = $value;
        }
    }
    """
    When I run phpspec
    Then the suite should pass

