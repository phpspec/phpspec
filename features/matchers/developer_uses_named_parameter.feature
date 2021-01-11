@php8
Feature: Developer uses named parameter
  As a Developer
  I want to use named parameter
  In order to leverage PHP8 capabilities

  Scenario: Named parameters are correctly matched
    Given the spec file "spec/Matchers/NamedParams/FooSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\NamedParams;

    use PhpSpec\ObjectBehavior;

    class FooSpec extends ObjectBehavior
    {
        function it_uses_unordered_named_param()
        {
            $this->doStuff(b: 'World', a: 'Hello')->shouldReturn('Hello World!');
        }

        function it_uses_named_param_on_resulting_object()
        {
            $thing = $this->askThing();
            $thing->doStuff(b: 'World', a: 'Hello')->shouldReturn('Hello World!');
        }
    }
    """
    And the class file "src/Matchers/NamedParams/Foo.php" contains:
    """
    <?php

    namespace Matchers\NamedParams;

    class Foo
    {
        public function doStuff(string $a, string $b, string $c = '!'): string
        {
            return "$a $b$c";
        }

        public function askThing(): self
        {
            return clone $this;
        }
    }
    """
    When I run phpspec
    Then the suite should pass
