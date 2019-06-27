Feature: Developer generates a method
  As a Developer
  I want to automate creating methods
  In order to avoid repetitive tasks and interruptions in development flow

  Scenario: Generating a method
    Given the spec file "spec/CodeGeneration/MethodExample1/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\MethodExample1;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/MethodExample1/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\MethodExample1;

      class Markdown
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/MethodExample1/Markdown.php" should contain:
      """
      <?php

      namespace CodeGeneration\MethodExample1;

      class Markdown
      {
          public function toHtml($argument1)
          {
              // TODO: write logic here
          }
      }

      """
  Scenario: Generating a method in a class with psr4 prefix
    Given the spec file "spec/MyNamespace/PrefixSpec.php" contains:
      """
      <?php

      namespace spec\Behat\Tests\MyNamespace;

      use PhpSpec\ObjectBehavior;

      class PrefixSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the config file contains:
      """
      suites:
        behat_suite:
          namespace: Behat\Tests\MyNamespace
          psr4_prefix: Behat\Tests
      """
    And the class file "src/MyNamespace/Prefix.php" contains:
      """
      <?php

      namespace Behat\Tests\MyNamespace;

      class Prefix
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/MyNamespace/Prefix.php" should contain:
      """
      <?php

      namespace Behat\Tests\MyNamespace;

      class Prefix
      {
          public function toHtml($argument1)
          {
              // TODO: write logic here
          }
      }

      """
  Scenario: Generating a constructor in a file with existing methods places the constructor first
    Given the spec file "spec/MyNamespace/ConstructorSpec.php" contains:
      """
      <?php

      namespace spec\MyNamespace;

      use PhpSpec\ObjectBehavior;

      class ConstructorSpec extends ObjectBehavior
      {
          function it_should_do_something_with_a_constructor()
          {
              $this->beConstructedWith('anArgument');
              $this->foo()->shouldReturn('bar');
          }
      }
      """
    And the class file "src/MyNamespace/Constructor.php" contains:
      """
      <?php

      namespace MyNamespace;

      class Constructor
      {
          public function foo()
          {
              return 'bar';
          }
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/MyNamespace/Constructor.php" should contain:
      """
      <?php

      namespace MyNamespace;

      class Constructor
      {
          public function __construct($argument1)
          {
              // TODO: write logic here
          }

          public function foo()
          {
              return 'bar';
          }
      }

      """

  Scenario: Generating a constructor in a file with no methods
    Given the spec file "spec/MyNamespace/ConstructorFirstSpec.php" contains:
      """
      <?php

      namespace spec\MyNamespace;

      use PhpSpec\ObjectBehavior;

      class ConstructorFirstSpec extends ObjectBehavior
      {
          function it_should_do_something_with_a_constructor()
          {
              $this->beConstructedWith('anArgument');
              $this->foo()->shouldReturn('bar');
          }
      }
      """
    And the class file "src/MyNamespace/ConstructorFirst.php" contains:
      """
      <?php

      namespace MyNamespace;

      class ConstructorFirst
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/MyNamespace/ConstructorFirst.php" should contain:
      """
      <?php

      namespace MyNamespace;

      class ConstructorFirst
      {
          public function __construct($argument1)
          {
              // TODO: write logic here
          }
      }

      """

  Scenario: Generating a method in a class with existing methods and new lines
    Given the spec file "spec/MyNamespace/ExistingMethodSpec.php" contains:
      """
      <?php

      namespace spec\MyNamespace;

      use PhpSpec\ObjectBehavior;

      class ExistingMethodSpec extends ObjectBehavior
      {
          function it_should_do_something()
          {
              $this->foo()->shouldReturn('bar');
          }
      }
      """
    And the class file "src/MyNamespace/ExistingMethod.php" contains:
      """
      <?php

      namespace MyNamespace;

      class ExistingMethod
      {
          public function existing()
          {
              return 'something';
          }

      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/MyNamespace/ExistingMethod.php" should contain:
      """
      <?php

      namespace MyNamespace;

      class ExistingMethod
      {
          public function existing()
          {
              return 'something';
          }

          public function foo()
          {
              // TODO: write logic here
          }

      }

      """

  Scenario: Generating a method in a class with existing methods containing anonymous functions
    Given the spec file "spec/MyNamespace/ExistingMethodAnonymousFunctionSpec.php" contains:
      """
      <?php

      namespace spec\MyNamespace;

      use PhpSpec\ObjectBehavior;

      class ExistingMethodAnonymousFunctionSpec extends ObjectBehavior
      {
          function it_should_do_something()
          {
              $this->foo()->shouldReturn('bar');
          }
      }
      """
    And the class file "src/MyNamespace/ExistingMethodAnonymousFunction.php" contains:
      """
      <?php

      namespace MyNamespace;

      class ExistingMethodAnonymousFunction
      {
          public function existing()
          {
              return function () {
                  return 'something';
              };
          }

      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/MyNamespace/ExistingMethodAnonymousFunction.php" should contain:
      """
      <?php

      namespace MyNamespace;

      class ExistingMethodAnonymousFunction
      {
          public function existing()
          {
              return function () {
                  return 'something';
              };
          }

          public function foo()
          {
              // TODO: write logic here
          }

      }

      """

  Scenario: Generating a constructor in a file with no methods
    Given the spec file "spec/MyNamespace/CommentMethodSpec.php" contains:
      """
      <?php

      namespace spec\MyNamespace;

      use PhpSpec\ObjectBehavior;

      class CommentMethodSpec extends ObjectBehavior
      {
          function it_should_do_something()
          {
              $this->foo()->shouldReturn('bar');
          }
      }
      """
    And the class file "src/MyNamespace/CommentMethod.php" contains:
      """
      <?php

      namespace MyNamespace;

      class CommentMethod
      {
          // this is a comment
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/MyNamespace/CommentMethod.php" should contain:
      """
      <?php

      namespace MyNamespace;

      class CommentMethod
      {
          // this is a comment

          public function foo()
          {
              // TODO: write logic here
          }
      }

      """

  @php:~7 @isolated
  Scenario: Successful generation of a method named with a reserved keyword in previous PHP versions
    Given the spec file "spec/MyNamespace/KeywordMethodSpec.php" contains:
      """
      <?php

      namespace spec\MyNamespace;

      use PhpSpec\ObjectBehavior;

      class KeywordMethodSpec extends ObjectBehavior
      {
          function it_tries_to_call_wrong_method()
          {
              $this->throw()->shouldReturn(null);
          }
      }

      """
    And the class file "src/MyNamespace/KeywordMethod.php" contains:
      """
      <?php

      namespace MyNamespace;

      class KeywordMethod
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/MyNamespace/KeywordMethod.php" should contain:
      """
      <?php

      namespace MyNamespace;

      class KeywordMethod
      {
          public function throw()
          {
              // TODO: write logic here
          }
      }

      """
    And the suite should pass
