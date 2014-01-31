Feature: Use the JUnit formatter
  In order to provide my CI tool with parsable phpspec results
  As a developper
  I need to be able to use a JUnit formatter

  Scenario: Successfully export phpspec results in JUnit format
    Given the spec file "spec/Formatter/SpecExample/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Formatter\SpecExample;

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
    And the class file "src/Formatter/SpecExample/Markdown.php" contains:
      """
      <?php

      namespace Formatter\SpecExample;

      class Markdown
      {
          public function toHtml($text)
          {
              return sprintf('<p>%s</p>', $text);
          }
      }

      """
    When I run phpspec using the "junit" format
    Then I should see valid junit output
