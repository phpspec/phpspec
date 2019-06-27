Feature: Developer runs specs with the given specs path configured to be the same as source path
  As a Developer
  I want to run the specs from given directory
  In order to get feedback on a state of requested part of my application

  Scenario: Reporting success when running a spec with correctly implemented class, passing spec path as an argument
    Given the config file contains:
      """
      suites:
        code_generator_suite:
          namespace: Runner\SpecPathExample
          psr4_prefix: Runner\SpecPathExample
          src_path: src/Runner/SpecPathExample
          spec_path: src/Runner/SpecPathExample

      """
    And the spec file "src/Runner/SpecPathExample/spec/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\SpecPathExample;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/Runner/SpecPathExample/Markdown.php" contains:
      """
      <?php

      namespace Runner\SpecPathExample;

      class Markdown
      {
          public function toHtml($text)
          {
              return sprintf('<p>%s</p>', $text);
          }
      }

      """
    When I run phpspec with "src/Runner/SpecPathExample/spec" specs to run
    Then 1 example should have been run
    And the suite should pass
