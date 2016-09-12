Feature: Developer generates abstract type methods
  As a Developer
  I want to automate creating abstract type methods
  In order to avoid repetitive tasks and interruptions in development flow

  Scenario: Generating interface methods in an empty class
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/PersonSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Types\CanSpeak;
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
    And the class file "src/CodeGeneration/AbstractTypeMethods/Types/CanSpeak.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Types;

      interface CanSpeak
      {
          public function sayHello($phrase);
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
    Then the suite should pass
     And the class in "src/CodeGeneration/AbstractTypeMethods/Person.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypes\Types\CanSpeak;

      class Person implements CanSpeak
      {
          public function sayHello($phrase)
          {
              // TODO: write logic here
          }
      }
      """
