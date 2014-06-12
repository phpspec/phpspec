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
      use Prophecy\Argument;

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

          public function toHtml($string1)
          {
              // TODO: write logic here
          }
      }

      """
  Scenario: Generating a method with more arguments and types
    Given the spec file "spec/CodeGeneration/MethodExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\MethodExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs_and_repeats_it()
          {
              $this->toHtml('Hi, there', 2)->shouldReturn('<p>Hi, there</p><p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/MethodExample2/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\MethodExample2;

      class Markdown
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/MethodExample2/Markdown.php" should contain:
      """
      <?php

      namespace CodeGeneration\MethodExample2;

      class Markdown
      {

          public function toHtml($string1, $integer2)
          {
              // TODO: write logic here
          }
      }

      """
  Scenario: Generating a method with an object as argument
    Given the spec file "spec/CodeGeneration/MethodExample3/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\MethodExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\MethodExample3\Greet;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_gets_a_greeting_and_converts_it_to_html_paragraphs(
            Greet $greet
          ) {
              $greet->getGreetings()->willReturn('Hi, there');
              $this->toHtml($greet)->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/MethodExample3/Greet.php" contains:
      """
      <?php

      namespace CodeGeneration\MethodExample3;

      class Greet
      {
          public function getGreetings()
          {
            return 'Hello!';
          }
      }

      """
    And the class file "src/CodeGeneration/MethodExample3/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\MethodExample3;

      class Markdown
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/MethodExample3/Markdown.php" should contain:
      """
      <?php

      namespace CodeGeneration\MethodExample3;

      class Markdown
      {

          public function toHtml(Greet $greet1)
          {
              // TODO: write logic here
          }
      }

      """
  Scenario: Generating a method with an object as argument in other namespace
    Given the spec file "spec/CodeGeneration/MethodExample4/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\MethodExample4;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\MethodExample5\Greet;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_gets_a_greeting_and_converts_it_to_html_paragraphs(
            Greet $greet
          ) {
              $greet->getGreetings()->willReturn('Hi, there');
              $this->toHtml($greet)->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/MethodExample5/Greet.php" contains:
      """
      <?php

      namespace CodeGeneration\MethodExample5;

      class Greet
      {
          public function getGreetings()
          {
            return 'Hello!';
          }
      }

      """
    And the class file "src/CodeGeneration/MethodExample4/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\MethodExample4;

      class Markdown
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/MethodExample4/Markdown.php" should contain:
      """
      <?php

      namespace CodeGeneration\MethodExample4;

      use CodeGeneration\MethodExample5\Greet;

      class Markdown
      {

          public function toHtml(Greet $greet1)
          {
              // TODO: write logic here
          }
      }

      """
  Scenario: Generating a method with an array as argument
    Given the spec file "spec/CodeGeneration/MethodExample6/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\MethodExample6;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_an_array_of_plain_text_to_html_paragraphs()
          {
              $this->toHtml(array('Hi, there'))->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/MethodExample6/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\MethodExample6;

      class Markdown
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/MethodExample6/Markdown.php" should contain:
      """
      <?php

      namespace CodeGeneration\MethodExample6;

      class Markdown
      {

          public function toHtml(array $array1)
          {
              // TODO: write logic here
          }
      }

      """
  Scenario: Generating a method in a class with psr4 prefix
    Given the spec file "spec/Behat/Tests/MyNamespace/PrefixSpec.php" contains:
    """
    <?php

    namespace spec\Behat\Tests\MyNamespace;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

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

        public function toHtml($string1)
        {
            // TODO: write logic here
        }
    }

    """