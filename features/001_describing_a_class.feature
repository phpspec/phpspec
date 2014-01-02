Feature: Describing a class
  As a Developer
  I want to automate creating classes and specs
  In order to avoid repetitive tasks and interruptions in development flow

  Scenario: Generating a spec
    When I start describing the "Scenario1/Markdown" class
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

  Scenario: Running a spec with a class that doesn't exist
    Given I started describing the "Scenario2/Markdown" class
    When I run phpspec
    Then I should see "class Scenario2\Markdown does not exist"

  Scenario: Generating a class
    Given I started describing the "Scenario3/Markdown" class
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then a new class should be generated in the "src/Scenario3/Markdown.php":
    """
    <?php

    namespace Scenario3;

    class Markdown
    {
    }

    """

  Scenario: Executing a spec
    Given the spec file "spec/Scenario4/MarkdownSpec.php" contains:
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
    And the class file "src/Scenario4/Markdown.php" contains:
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
