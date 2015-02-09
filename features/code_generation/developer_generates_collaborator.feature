Feature: Developer generates a collaborator
  As a Developer
  I want to automate creating collaborators
  In order to avoid disrupting my workflow

  Scenario: Being prompted but not generating a collaborator
    Given the spec file "spec/CodeGeneration/CollaboratorExample1/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorExample1\Parser;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorExample1/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample1;

      class Markdown
      {
      }

      """
  When I run phpspec and answer "n" when asked if I want to generate the code
    Then I should be prompted with:
      """
      Would you like me to generate an interface
        `CodeGeneration\CollaboratorExample1\Parser` for you?
                                                                     [Y/n]
      """

  Scenario: Asking for interface to be generated
    Given the spec file "spec/CodeGeneration/CollaboratorExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorExample2\Parser;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorExample2/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample2;

      class Markdown
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/CollaboratorExample2/Parser.php" should contain:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample2;

      interface Parser
      {
      }

      """

  Scenario: Not being prompted when typehint is in spec namespace
    Given the spec file "spec/CodeGeneration/CollaboratorExample3/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorExample3/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample3;

      class Markdown
      {
      }

      """
    When I run phpspec and answer "n" when asked if I want to generate the code
    Then I should not be prompted for code generation

