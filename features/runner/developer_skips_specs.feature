Feature: Developer skips examples
  As a Developer
  I want to skip some examples I know won't pass
  In order to sanitize my result output

  Scenario: Skip a spec with and run it using the dot formatter
    Given the spec file "spec/Runner/SpecExample/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\SpecExample;

      use PhpSpec\ObjectBehavior;
      use PhpSpec\Exception\Example\SkippingException;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_table_to_html_table()
          {
              throw new SkippingException('subject to a php bug');
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
    When I run phpspec using the "dot" format
    Then 1 example should have been skipped
    And the suite should pass

  Scenario: Skipping a spec file should not render the stack trace using verbose option
    Given the spec file "spec/Runner/SpecExample/EmojiSpec.php" contains:
      """
      <?php

      namespace spec\Runner\SpecExample;

      use PhpSpec\ObjectBehavior;
      use PhpSpec\Exception\Example\SkippingException;

      class EmojiSpec extends ObjectBehavior
      {
          function it_conversts_named_emoji_to_utf8()
          {
              throw new SkippingException('😏');
          }
      }

      """
    And the class file "src/Runner/SpecExample/Emoji.php" contains:
      """
      <?php

      namespace Runner\SpecExample;

      class Emoji
      {
          public function toUtf8($text)
          {
            // I don't have the time to implement this right now. 😤
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then 1 example should have been skipped
    And the suite should pass
    But The output should not contain:
      """
      spec/Runner/SpecExample/EmojiSpec.php:12
      """