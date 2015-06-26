Feature: Developer runs the specs
  As a Developer
  I want to run the specs
  In order to get feedback on a state of my application

  Scenario: Running a spec with a class that doesn't exist
    Given I have started describing the "Runner/SpecExample1/Markdown" class
    When I run phpspec
    Then I should see "class Runner\SpecExample1\Markdown does not exist"

  Scenario: Reporting success when running a spec with correctly implemented class
    Given the spec file "spec/Runner/SpecExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\SpecExample2;

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
    And the class file "src/Runner/SpecExample2/Markdown.php" contains:
      """
      <?php

      namespace Runner\SpecExample2;

      class Markdown
      {
          public function toHtml($text)
          {
              return sprintf('<p>%s</p>', $text);
          }
      }

      """
    When I run phpspec
    Then the suite should pass

    @issue214
  Scenario: Letgo is executed after successful spec
    Given the spec file "spec/Runner/SpecExample3/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\SpecExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function letgo()
          {
              throw new \Exception('Letgo is called');
          }

          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/Runner/SpecExample3/Markdown.php" contains:
      """
      <?php

      namespace Runner\SpecExample3;

      class Markdown
      {
          public function toHtml($text)
          {
              return sprintf('<p>%s</p>', $text);
          }
      }

      """
    When I run phpspec
    Then I should see "Letgo is called"

    @issue214
  Scenario: Letgo is executed after exception is thrown
    Given the spec file "spec/Runner/SpecExample4/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Runner\SpecExample4;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function letgo()
          {
              throw new \Exception('Letgo is called');
          }

          function it_converts_plain_text_to_html_paragraphs()
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/Runner/SpecExample4/Markdown.php" contains:
      """
      <?php

      namespace Runner\SpecExample4;

      class Markdown
      {
          public function toHtml($text)
          {
              throw new \Exception('Some exception');
          }
      }

      """
    When I run phpspec
    Then I should see "Letgo is called"


  Scenario: Fully qualified class name can run specs
    Given the spec file "spec/Runner/Namespace/Example1Spec.php" contains:
      """
      <?php
      namespace spec\Runner\TestNamespace;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class Example1Spec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType('Runner\TestNamespace\Example1');
          }
      }

      """
    And the class file "src/Runner/TestNamespace/Example1.php" contains:
      """
      <?php

      namespace Runner\TestNamespace;

      class Example1
      {
      }

      """
    When I run phpspec with the spec "Runner\TestNamespace\Example1"
    Then the suite should pass

  Scenario: Fully qualified PSR4 class name can run specs
    Given the spec file "spec/Runner/Namespace/Example2Spec.php" contains:
      """
      <?php
      namespace spec\Psr4\Runner\TestNamespace;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class Example2Spec extends ObjectBehavior
      {
          function it_is_initializable()
          {
              $this->shouldHaveType('Psr4\Runner\TestNamespace\Example2');
          }
      }

      """
    And the class file "src/Psr4/Runner/TestNamespace/Example2.php" contains:
      """
      <?php

      namespace Psr4\Runner\TestNamespace;

      class Example2
      {
      }

      """
    And the config file located in "Psr4" contains:
      """
      suites:
        behat_suite:
          namespace: Psr4
          psr4_prefix: Psr4
      """
    When I run phpspec with the spec "Psr4\Runner\TestNamespace\Example2" and the config "Psr4/phpspec.yml"
    Then the suite should pass
