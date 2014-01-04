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

          public function toHtml($argument1)
          {
              // TODO: write logic here
          }
      }

      """
