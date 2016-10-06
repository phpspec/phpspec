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

  Scenario: Generating methods from an interface in a non-empty class that share the same namespace
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/ManagerSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\CanManage;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ManagerSpec extends ObjectBehavior
      {
          function it_can_speak()
          {
              $this->shouldHaveType(CanManage::class);
          }
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/CanManage.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      interface CanManage
      {
          public function manage($what, $how);

          public function delegate($what, $who);
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Manager.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Manager
      {
          public function foo()
          {
          }

          private function bar()
          {
          }
      }
      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/AbstractTypeMethods/Manager.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Manager implements CanManage
      {
          public function foo()
          {
          }

          private function bar()
          {
          }

          public function manage($argument1, $argument2)
          {
              // TODO: write logic here
          }

          public function delegate($argument1, $argument2)
          {
              // TODO: write logic here
          }
      }
      """

  Scenario: Generating methods from an interface in an empty class that have different namespaces
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/EngineerSpec.php" contains:
     """
     <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Engineering\CanWriteCode;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class EngineerSpec extends ObjectBehavior
      {
          function it_can_write_code()
          {
              $this->shouldHaveType(CanWriteCode::class);
          }
      }
     """

    And the class file "src/CodeGeneration/AbstractTypeMethods/Engineering/CanWriteCode.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Engineering;

      interface CanWriteCode
      {
          public function writeCode($code);
      }
      """

    And the class file "src/CodeGeneration/AbstractTypeMethods/Engineer.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Engineer
      {
      }
      """

    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/AbstractTypeMethods/Engineer.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Engineering\CanWriteCode;

      class Engineer implements CanWriteCode
      {
          public function writeCode($argument1)
          {
              // TODO: write logic here
          }
      }
      """

  Scenario: Generating methods from an interface in a class that has a parent
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/UserFriendlyExceptionSpec.php" contains:
     """
     <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Exceptions\FriendlyMessageException;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class UserFriendlyExceptionSpec extends ObjectBehavior
      {
          function it_is_a_friendly_exception()
          {
              $this->shouldHaveType(FriendlyMessageException::class);
          }
      }
     """

    And the class file "src/CodeGeneration/AbstractTypeMethods/Exceptions/FriendlyMessageException.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Exceptions;

      interface FriendlyMessageException
      {
          public function getFriendlyMessage();
      }
      """

    And the class file "src/CodeGeneration/AbstractTypeMethods/UserFriendlyException.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class UserFriendlyException extends \RuntimeException
      {
      }
      """

    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/AbstractTypeMethods/UserFriendlyException.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Exceptions\FriendlyMessageException;

      class UserFriendlyException extends \RuntimeException implements FriendlyMessageException
      {
          public function getFriendlyMessage()
          {
              // TODO: write logic here
          }
      }
      """