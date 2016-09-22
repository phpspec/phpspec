Feature: Developer implements interface
  As a Developer
  I want to automate creating abstract type methods
  In order to avoid repetitive tasks and interruptions in development flow

  Scenario: Generating methods from an interface in an empty class that share the same namespace
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/PersonSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\CanSpeak;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class PersonSpec extends ObjectBehavior
      {
          function it_can_speak()
          {
              $this->shouldHaveType(CanSpeak::class);
          }
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/CanSpeak.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      interface CanSpeak
      {
          public function say($phrase);
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Person.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Person
      {
      }
      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/AbstractTypeMethods/Person.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Person implements CanSpeak
      {
          public function say($argument1)
          {
              // TODO: write logic here
          }
      }
      """