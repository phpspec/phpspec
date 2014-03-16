Feature: Developer generates a spec
  As a Developer
  I want to automate creating specs
  In order to avoid repetitive tasks and interruptions in development flow

  Scenario: Generating a spec
    When I start describing the "CodeGeneration/SpecExample1/Markdown" class
    Then a new spec should be generated in the "spec/CodeGeneration/SpecExample1/MarkdownSpec.php":
      """
      <?php

      namespace spec\CodeGeneration\SpecExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType('CodeGeneration\SpecExample1\Markdown');
          }
      }

      """
  Scenario: Generating a spec with PSR0 must convert classname underscores to directory separator
    When I start describing the "CodeGeneration/SpecExample1/Text_Markdown" class
    Then a new spec should be generated in the "spec/CodeGeneration/SpecExample1/Text/MarkdownSpec.php":
      """
      <?php

      namespace spec\CodeGeneration\SpecExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class Text_MarkdownSpec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType('CodeGeneration\SpecExample1\Text_Markdown');
          }
      }

      """
