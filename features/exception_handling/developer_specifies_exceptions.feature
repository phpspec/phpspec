Feature: Developer specifies exception behaviour
  As a Developer
  I want to be able to specify the exceptions by SUS will throw
  In order to drive the design of my exception handling

  Scenario: Throwing an exception during construction when beConstructedWith specifies valid parameters
    Given the spec file "spec/Runner/ExceptionExample3/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ExceptionExample3;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function let()
          {
              $this->beConstructedWith('nothrow');
          }

          function it_throws_an_exception_using_magic_syntax()
          {
              $this->shouldThrow('Exception')->during__construct('throw');
          }
      }

      """
    And the class file "src/Runner/ExceptionExample3/Markdown.php" contains:
      """
      <?php

      namespace Runner\ExceptionExample3;

      class Markdown
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
    Then the suite should pass

  Scenario: Throwing an exception with unset properties
    Given the spec file "spec/Runner/ExceptionExample/UnsetPropertyClassSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ExceptionExample;

      use PhpSpec\ObjectBehavior;

      class UnsetPropertyClassSpec extends ObjectBehavior
      {
          function it_throws_an_exception_with_unset_property()
          {
              $this->shouldThrow(new \Runner\ExceptionExample\ChildException('exception message'))
                  ->duringDoSomething();
          }
      }

      """
    And the class file "src/Runner/ExceptionExample/UnsetPropertyClass.php" contains:
      """
      <?php

      namespace Runner\ExceptionExample;

      class UnsetPropertyClass
      {
          public function doSomething()
          {
              $e = new \Runner\ExceptionExample\ChildException('exception message');
              throw $e;
          }
      }

      """
    And the class file "src/Runner/ExceptionExample/UnsetPropertyException.php" contains:
      """
      <?php

      namespace Runner\ExceptionExample;

      class UnsetPropertyException extends \RuntimeException
      {
          protected $serialized;

          private $token;

          public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
          {
              unset($this->serialized);
              parent::__construct($message, $code, $previous);
          }

          public function __serialize(): array
          {
              return [$this->token, $this->code, $this->message, $this->file, $this->line];
          }

          public function __unserialize(array $data): void
          {
              [$this->token, $this->code, $this->message, $this->file, $this->line] = $data;
          }

          public function __sleep(): array
          {
              $this->serialized = $this->__serialize();

              return ['serialized'];
          }

          public function __wakeup(): void
          {
              $this->__unserialize($this->serialized);
              unset($this->serialized);
          }
      }

      """
    And the class file "src/Runner/ExceptionExample/ChildException.php" contains:
      """
      <?php

      namespace Runner\ExceptionExample;

      class ChildException extends UnsetPropertyException
      {
          public function childMethod()
          {
              return 'this is child method';
          }
      }

      """
    When I run phpspec
    Then the suite should pass
