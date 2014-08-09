Feature: Developer loads suite
  As a Developer
  I want to load the test suite
  In order to run my tests

  @issue354
  Scenario: Spec file does not contain expected class
    Given the spec file "spec/Loader/SpecExample1/BadNameSpec.php" contains:
    """
    <?php

    namespace spec\Loader\SpecExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class IncorrectlyNamedSpec extends ObjectBehavior
    {
    }
    """

    And the class file "src/Loader/SpecExample1/BadName.php" contains:
    """
    <?php

    namespace Loader\SpecExample1;

    class BadName
    {
    }
    """

    When I run phpspec
    Then I should see "spec\Loader\SpecExample1\IncorrectlyNamedSpec is a badly named spec"
