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

  Scenario: Generating methods from an interface in an empty class that is in the global namespace
    Given the spec file "spec/PersonSpec.php" contains:
      """
      <?php

      namespace spec;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class PersonSpec extends ObjectBehavior
      {
          function it_can_speak()
          {
              $this->shouldHaveType(\CanSpeak::class);
          }
      }
      """
    And the class file "src/CanSpeak.php" contains:
      """
      <?php

      interface CanSpeak
      {
          public function say(Phrase $phrase);
      }
      """
    And the class file "src/Person.php" contains:
      """
      <?php

      class Person
      {
      }
      """
    And the class file "src/Phrase.php" contains:
      """
      <?php

      interface Phrase
      {
      }
      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/Person.php" should contain:
      """
      <?php

      class Person implements CanSpeak
      {
          public function say(Phrase $phrase)
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

  Scenario: Specifying an interface for a class that has a parent already implementing that interface
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/AbstractTypeSpec.php" contains:
     """
     <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class AbstractTypeSpec extends ObjectBehavior
      {
          function it_is_a_type()
          {
              $this->shouldHaveType(Type::class);
          }
      }
     """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Type.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      interface Type
      {
          public function getType();
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/AbstractType.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      abstract class AbstractType implements Type
      {
          public function getType()
          {
          }
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/ConcreteType.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class ConcreteType extends AbstractType
      {
      }
      """
    When I run phpspec
    Then I should not be prompted for code generation

  Scenario: Generating methods from an interface that has a parent in a class
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/UserActionExceptionSpec.php" contains:
     """
     <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Auth\AuthorisedUserException;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class UserActionExceptionSpec extends ObjectBehavior
      {
          function it_is_an_authorised_user_exception()
          {
              $this->shouldHaveType(AuthorisedUserException::class);
          }
      }
     """

    And the class file "src/CodeGeneration/AbstractTypeMethods/Exceptions/UserException.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Auth;

      interface UserException
      {
          public function getUser();
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Exceptions/AuthorisedUserException.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Auth;

      interface AuthorisedUserException extends UserException
      {
          public function getToken();

          public function setToken(Token $token);
      }
      """

    And the class file "src/CodeGeneration/AbstractTypeMethods/UserActionException.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class UserActionException
      {
      }
      """

    And the class file "src/CodeGeneration/AbstractTypeMethods/Auth/Token.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Auth;

      interface Token
      {
      }
      """

    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/AbstractTypeMethods/UserActionException.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Auth\AuthorisedUserException;
      use CodeGeneration\AbstractTypeMethods\Auth\Token;

      class UserActionException implements AuthorisedUserException
      {
          public function getToken()
          {
              // TODO: write logic here
          }

          public function setToken(Token $token)
          {
              // TODO: write logic here
          }

          public function getUser()
          {
              // TODO: write logic here
          }
      }
      """

  Scenario: Generating methods from an interface in a class that already implements another interface
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/UserSpec.php" contains:
     """
     <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Auth\TokenAware;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class UserSpec extends ObjectBehavior
      {
          function it_is_aware_of_tokens()
          {
              $this->shouldHaveType(TokenAware::class);
          }
      }
     """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Exceptions/TokenAware.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Auth;

      interface TokenAware
      {
          public function getToken();
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Exceptions/UsernameAware.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods\Auth;

      interface UsernameAware
      {
          public function getUsername();
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/User.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Auth\UsernameAware;

      class User implements UsernameAware
      {
          public function getUsername()
          {
          }
      }
      """

    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/AbstractTypeMethods/User.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Auth\UsernameAware;
      use CodeGeneration\AbstractTypeMethods\Auth\TokenAware;

      class User implements UsernameAware, TokenAware
      {
          public function getUsername()
          {
          }

          public function getToken()
          {
              // TODO: write logic here
          }
      }
      """

  Scenario: Specifying an abstract class for a concrete class does not prompt for code generation
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/KnightSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\GamePiece;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class KnightSpec extends ObjectBehavior
      {
          function it_is_a_game_piece()
          {
              $this->shouldHaveType(GamePiece::class);
          }
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/GamePiece.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      abstract class GamePiece
      {
          abstract public function getName();
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Knight.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Knight
      {
      }
      """
    When I run phpspec
    Then I should not be prompted for code generation
     And the suite should not pass

  Scenario: Not generating methods from an interface in a class
    Given the spec file "spec/CodeGeneration/AbstractTypeMethods/CarSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\AbstractTypeMethods;

      use CodeGeneration\AbstractTypeMethods\Vehicle;
      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class CarSpec extends ObjectBehavior
      {
          function it_is_a_vehicle()
          {
              $this->shouldHaveType(Vehicle::class);
          }
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Vehicle.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      interface Vehicle
      {
          public function move($destination);
      }
      """
    And the class file "src/CodeGeneration/AbstractTypeMethods/Car.php" contains:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Car
      {
      }
      """
    When I run phpspec and answer "n" when asked if I want to generate the code
    Then the suite should not pass
     And the class in "src/CodeGeneration/AbstractTypeMethods/Car.php" should contain:
      """
      <?php

      namespace CodeGeneration\AbstractTypeMethods;

      class Car
      {
      }
      """
