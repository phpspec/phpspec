Feature: Developer generates a collaborator's method
  As a Developer
  I want to automate creating collaborators' missing methods
  In order to avoid disrupting my workflow

  Scenario: Being prompted to generate a collaborator method based on typehints
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


  Scenario: Being prompted to generate a collaborator method based on docblocks
    Given the spec file "spec/CodeGeneration/CollaboratorMethodExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorMethodExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          /**
           * @param \CodeGeneration\CollaboratorMethodExample2\Parser $parser
           */
          function it_interacts_with_a_collaborator($parser)
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
    When I run phpspec and answer "n" when asked if I want to generate the code
    Then I should be prompted with:
      """
      Would you like me to generate a method signature
        `CodeGeneration\CollaboratorMethodExample2\Parser::getSuccess()` for you?
                                                                     [Y/n]
      """

  Scenario: Asking for the method signature to be generated
    Given the spec file "spec/CodeGeneration/CollaboratorMethodExample3/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorMethodExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorMethodExample3\Parser;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->getSuccess()->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample3/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample3;

      class Markdown
      {
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample3/Parser.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample3;

      interface Parser
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/CollaboratorMethodExample3/Parser.php" should contain:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample3;

      interface Parser
      {

          public function getSuccess();
      }

      """

  Scenario: Asking for the method signature to be generated with multiple parameters
    Given the spec file "spec/CodeGeneration/CollaboratorMethodExample4/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorMethodExample4;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorMethodExample4\Parser;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->parse('xyz', 2)->willReturn(1);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample4/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample4;

      class Markdown
      {
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample4/Parser.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample4;

      interface Parser
      {
      }

      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/CollaboratorMethodExample4/Parser.php" should contain:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample4;

      interface Parser
      {

          public function parse($argument1, $argument2);
      }

      """

  Scenario: Not being prompted when collaborator is a class
    Given the spec file "spec/CodeGeneration/CollaboratorMethodExample5/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorMethodExample5;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorMethodExample5\Parser;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->getSuccess()->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample5/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample5;

      class Markdown
      {
      }

      """
    And the class file "src/CodeGeneration/CollaboratorMethodExample5/Parser.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorMethodExample5;

      class Parser
      {
      }

      """
    When I run phpspec and answer "n" when asked if I want to generate the code
    Then I should not be prompted for code generation
