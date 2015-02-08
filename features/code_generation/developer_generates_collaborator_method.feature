Feature: Developer generates a collaborator's method
  As a Developer
  I want to automate creating collaborators' missing methods
  In order to avoid disrupting my workflow

  Scenario: Being prompted but not generating a collaborator method
    Given the spec file "spec/CodeGeneration/CollaboratorMethodExample1/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorMethodExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorMethodExample1\Parser;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->getSuccess()->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample1/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample1;

      class Markdown
      {
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample1/Parser.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample1;

      interface Parser
      {
      }

      """
  When I run phpspec and answer "n" when asked if I want to generate the code
    Then I should be prompted with:
      """
      Would you like me to generate a method signature
        `CodeGeneration\CollaboratorMethodExample1\Parser::getSuccess()` for you?
                                                                     [Y/n]
      """

  Scenario: Asking for the method signature to be generated
    Given the spec file "spec/CodeGeneration/CollaboratorMethodExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorMethodExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorMethodExample2\Parser;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->getSuccess()->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample2/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample2;

      class Markdown
      {
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample2/Parser.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample2;

      interface Parser
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/CollaboratorMethodExample2/Parser.php" should contain:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample2;

      interface Parser
      {

          public function getSuccess();
      }

      """
