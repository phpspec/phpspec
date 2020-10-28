Feature: Developer generates a method returning a constant
  As a Developer
  I want to automate creating methods that return constants
  In order to avoid having to manually write the code

  Scenario: Generating a scalar return type when method exists
    Given the spec file "spec/CodeGeneration/ConstantExample1/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample1;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample1/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample1;

      class Markdown
      {
          public function toHtml($argument1)
          {}
      }

      """
    When I run phpspec with the option "fake" and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/ConstantExample1/Markdown.php" should contain:
      """
      <?php

      namespace CodeGeneration\ConstantExample1;

      class Markdown
      {
          public function toHtml($argument1)
          {
              return '<p>Hi, there</p>';
          }
      }

      """

  Scenario: Generating a scalar return type when method contains comments
    Given the spec file "spec/CodeGeneration/ConstantExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample2;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample2/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample2;

      class Markdown
      {
          public function toHtml($argument1)
          {
            // TODO: Add Logic here
            /*
                This code is inactive
             */
          }
      }

      """
    When I run phpspec with the option "fake" and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/ConstantExample2/Markdown.php" should contain:
      """
      <?php

      namespace CodeGeneration\ConstantExample2;

      class Markdown
      {
          public function toHtml($argument1)
          {
              return '<p>Hi, there</p>';
          }
      }

      """

  Scenario: No prompt when method contains code
    Given the spec file "spec/CodeGeneration/ConstantExample3/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample3;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample3/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample3;

      class Markdown
      {
          public function toHtml($argument1)
          {
            $foo = 'bar';
          }
      }

      """
    When I run phpspec interactively with the "fake" option
    Then I should not be prompted for code generation

  Scenario: No prompt when examples contradict code
    Given the spec file "spec/CodeGeneration/ConstantExample4/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample4;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }

          function it_converts_more_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hello, there')->shouldReturn('<p>Hello, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample4/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample4;

      class Markdown
      {
          public function toHtml($argument1)
          {
          }
      }

      """
    When I run phpspec interactively with the "fake" option
    Then I should not be prompted for code generation

  Scenario: No prompt when CLI option is not used
    Given the spec file "spec/CodeGeneration/ConstantExample5/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample5;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample5/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample5;

      class Markdown
      {
          public function toHtml($argument1)
          {
          }
      }

      """
    When I run phpspec interactively
    Then I should not be prompted for code generation

  Scenario: Prompted when CLI option is not used but config flag is set
    Given the spec file "spec/CodeGeneration/ConstantExample6/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample6;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample6/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample6;

      class Markdown
      {
          public function toHtml($argument1)
          {
          }
      }

      """
    And the config file contains:
      """
      fake: true
      """
    When I run phpspec interactively
    Then I should be prompted for code generation

  Scenario: Generating a scalar return type when method is in trait
    Given the spec file "spec/CodeGeneration/ConstantExample7/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample7;

      use PhpSpec\ObjectBehavior;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the trait file "src/CodeGeneration/ConstantExample7/MarkdownTrait.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample7;

      trait MarkdownTrait
      {
          public function toHtml($argument1)
          {
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample7/Markdown.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample7;

      class Markdown
      {
          use MarkdownTrait;
      }

      """
    When I run phpspec with the option "fake" and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/ConstantExample7/MarkdownTrait.php" should contain:
      """
      <?php

      namespace CodeGeneration\ConstantExample7;

      trait MarkdownTrait
      {
          public function toHtml($argument1)
          {
              return '<p>Hi, there</p>';
          }
      }

      """

  Scenario: Generating a scalar return type for positive matcher when method exists
    Given the spec file "spec/CodeGeneration/ConstantExample8/MyFeatureSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample8;

      use PhpSpec\ObjectBehavior;

      class MyFeatureSpec extends ObjectBehavior
      {
          function it_should_be_active()
          {
              $this->shouldBeActive();
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample8/MyFeature.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample8;

      class MyFeature
      {
          public function isActive()
          {}
      }

      """
    When I run phpspec with the option "fake" and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/ConstantExample8/MyFeature.php" should contain:
      """
      <?php

      namespace CodeGeneration\ConstantExample8;

      class MyFeature
      {
          public function isActive()
          {
              return true;
          }
      }

      """

  Scenario: Generating a scalar return type for negative matcher when method exists
    Given the spec file "spec/CodeGeneration/ConstantExample9/MyFeatureSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample9;

      use PhpSpec\ObjectBehavior;

      class MyFeatureSpec extends ObjectBehavior
      {
          function it_should_not_be_active()
          {
              $this->shouldNotBeActive();
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample9/MyFeature.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample9;

      class MyFeature
      {
          public function isActive()
          {}
      }

      """
    When I run phpspec with the option "fake" and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/ConstantExample9/MyFeature.php" should contain:
      """
      <?php

      namespace CodeGeneration\ConstantExample9;

      class MyFeature
      {
          public function isActive()
          {
              return false;
          }
      }

      """

  Scenario: Generating a scalar return type for has positive matcher when method exists
    Given the spec file "spec/CodeGeneration/ConstantExample10/MyFeatureSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample10;

      use PhpSpec\ObjectBehavior;

      class MyFeatureSpec extends ObjectBehavior
      {
          function it_should_have_availability()
          {
              $this->shouldHaveAvailability();
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample10/MyFeature.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample10;

      class MyFeature
      {
          public function hasAvailability()
          {}
      }

      """
    When I run phpspec with the option "fake" and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/ConstantExample10/MyFeature.php" should contain:
      """
      <?php

      namespace CodeGeneration\ConstantExample10;

      class MyFeature
      {
          public function hasAvailability()
          {
              return true;
          }
      }

      """

  Scenario: Generating a scalar return type for has negative matcher when method exists
    Given the spec file "spec/CodeGeneration/ConstantExample11/MyFeatureSpec.php" contains:
      """
      <?php

      namespace spec\CodeGeneration\ConstantExample11;

      use PhpSpec\ObjectBehavior;

      class MyFeatureSpec extends ObjectBehavior
      {
          function it_should_not_have_availability()
          {
              $this->shouldNotHaveAvailability();
          }
      }

      """
    And the class file "src/CodeGeneration/ConstantExample11/MyFeature.php" contains:
      """
      <?php

      namespace CodeGeneration\ConstantExample11;

      class MyFeature
      {
          public function hasAvailability()
          {}
      }

      """
    When I run phpspec with the option "fake" and answer "y" when asked if I want to generate the code
    Then the class in "src/CodeGeneration/ConstantExample11/MyFeature.php" should contain:
      """
      <?php

      namespace CodeGeneration\ConstantExample11;

      class MyFeature
      {
          public function hasAvailability()
          {
              return false;
          }
      }

      """
