Feature: Developer ignores pending test warnings
  So that I can automate tasks without failures for pending specs
  As a Developer
  I should be able to ignore pending spec warnings

  Scenario: Run command with --ignore-pending flag set, using the dot formatter
    Given the spec file "spec/Runner/SpecExample/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\SpecExample;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use PhpSpec\Exception\Example\SkippingException;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_table_to_html_table()
          {
              // Nothing here, so pending warning triggered
          }
      }

      """
    And the class file "src/Runner/SpecExample/Markdown.php" contains:
      """
      <?php

      namespace Runner\SpecExample;

      class Markdown
      {
          public function toHtml($text)
          {
          }
      }

      """
    When I run phpspec interactively with the "ignore-pending" option
    Then 1 example should be pending
    But the suite should pass