Feature: Developer generates a class
  As a Developer
  I want to automate creating classes
  In order to avoid repetitive tasks and interruptions in development flow

  Scenario: Generating a class
    Given I have started describing the "CodeGeneration/ClassExample1/Markdown" class
    When I run phpspec and answer "y" when asked if I want to generate the code
    Then a new class should be generated in the "src/CodeGeneration/ClassExample1/Markdown.php":
      """
      <?php

      namespace CodeGeneration\ClassExample1;

      class Markdown
      {
      }

      """
