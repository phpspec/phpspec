Feature: Developer has defined a supporting class and should not see a silent error
  As a Developer
  I want to see if my supporting class is properly defined
  So that I can better trace where my changes caused a fatal error

  @isolated
  Scenario: Spec attempts to run a class with an undeclared interface, outputs to stdout
    Given the spec file "spec/SomethingSpec.php" contains:
      """
      <?php
        namespace spec;

        use PhpSpec\ObjectBehavior;
        use Prophecy\Argument;

        class SomethingSpec extends ObjectBehavior
        {
            function it_is_initializable()
            {
                $this->shouldHaveType('Something');
            }
        }

        class ExampleClass implements NotDefinedInterface
        {
        }

      """
    And the class file "src/Something.php" contains:
      """
      <?php
      namespace spec;

      class Something
      {
          public function __construct($param)
          {
              if ($param == 'throw') {
                  throw new \Exception();
              }
          }
      }

      """
    When I run phpspec
    Then I should see "Fatal error happened while executing the following"
