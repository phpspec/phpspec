Feature: Describing a class
  As a Developer
  I want to automate creating classes and specs
  In order to avoid repetitive tasks and interruptions in development flow

  Scenario: Generating a spec
    When I describe the "Scenario1/Markdown" class
    Then a new spec should be generated in the "spec/Scenario1/MarkdownSpec.php":
    """
    <?php

    namespace spec\Scenario1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_is_initializable()
        {
            $this->shouldHaveType('Scenario1\Markdown');
        }
    }

    """

  Scenario: Running a spec
    Given I described the "Scenario2/Markdown" class
    When I run phpspec
    Then I should see "class Scenario2\Markdown does not exist"

  Scenario: Generating a class
    Given I described the "Scenario3/Markdown" class
    When I run phpspec and answer "y" to the first question
    Then a new class should be generated in the "src/Scenario3/Markdown.php":
    """
    <?php

    namespace Scenario3;

    class Markdown
    {
    }

    """

  Scenario: Executing a spec
    Given I wrote a spec in the "spec/Scenario4/MarkdownSpec.php":
    """
    <?php

    namespace spec\Scenario4;

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
    And I wrote a class in the "src/Scenario4/Markdown.php":
    """
    <?php

    namespace Scenario4;

    class Markdown
    {
        public function toHtml($text)
        {
            return sprintf('<p>%s</p>', $text);
        }
    }

    """
    When I run phpspec
    Then I should see "1 example (1 passed)"
