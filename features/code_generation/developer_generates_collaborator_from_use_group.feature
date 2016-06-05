@php:~7.0
Feature: Developer generates a collaborator
  As a Developer
  I want to automate creating collaborators
  In order to avoid disrupting my workflow

  Scenario: Being prompted but not generating a collaborator
    Given the spec file "spec/CodeGeneration/CollaboratorExample1/Markdown1Spec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorExample1\{Tokenizer, Parser};

      class Markdown1Spec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorExample1/Markdown1.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample1;

      class Markdown1
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

  Scenario: Asking for interface to be generated for second collaborator in group
    Given the spec file "spec/CodeGeneration/CollaboratorExample2/Markdown1Spec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorExample2\{Tokenizer, Parser};

      class Markdown1Spec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Parser $parser)
          {
              $parser->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorExample2/Markdown1.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample2;

      class Markdown1
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

  Scenario: Asking for interface to be generated for the first collaborator in group
    Given the spec file "spec/CodeGeneration/CollaboratorExample3/Markdown1Spec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\CollaboratorExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;
      use CodeGeneration\CollaboratorExample3\{Tokenizer, Parser};

      class Markdown1Spec extends ObjectBehavior
      {
          function it_interacts_with_a_collaborator(Tokenizer $tokenizer)
          {
              $tokenizer->willReturn(true);
          }
      }

      """
    And the class file "src/CodeGeneration/CollaboratorExample3/Markdown1.php" contains:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample3;

      class Markdown1
      {
      }
      """
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/CollaboratorExample3/Tokenizer.php" should contain:
      """
      <?php

      namespace CodeGeneration\CollaboratorExample3;

      interface Tokenizer
      {
      }

      """


