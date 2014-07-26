Feature: Developer adds custom class patches
  As a Developer
  I want to add custom class patches
  In order to bypass problems with classes that fail to be mocked using default behavior

  Scenario: Using a collaborator that cannot be mocked by default
    Given the file "phpspec.yml" contains:
      """
      extensions:
          - spec\Doubler\ClassPatchExample1\Extension
      """
    And the spec file "spec/Doubler/ClassPatchExample1/MarkdownSpec.php" contains:
      """
      <?php namespace spec\Doubler\ClassPatchExample1 { class Extension implements \PhpSpec\Extension\ExtensionInterface {
          function load(\PhpSpec\ServiceContainer $container) { 
              $container->set('prophecy.doubler.class_patch.traversable', function ($c) {
                  return new \Prophecy\Doubler\ClassPatch\TraversablePatch;
              });
          }
      }}
      """
    And the spec file "spec/Doubler/ClassPatchExample1/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Doubler\ClassPatchExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_handles_files(\SplFileInfo $file)
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/Doubler/ClassPatchExample1/Markdown.php" contains:
      """
      <?php

      namespace Doubler\ClassPatchExample1;

      class Markdown
      {
          public function toHtml($text)
          {
              return sprintf('<p>%s</p>', $text);
          }
      }

      """
    When I run phpspec
    Then the suite should fail


  Scenario: Using a collaborator that has been class-patched
    Given the file "phpspec.yml" contains:
      """
      extensions:
          - spec\Doubler\ClassPatchExample2\Extension
      """
    And the spec file "spec/Doubler/ClassPatchExample1/MarkdownSpec.php" contains:
      """
      <?php namespace spec\Doubler\ClassPatchExample2 { class Extension implements \PhpSpec\Extension\ExtensionInterface {
          function load(\PhpSpec\ServiceContainer $container) { 
              $container->set('prophecy.doubler.class_patch.spl_file_info', function ($c) {
                  return new \Prophecy\Doubler\ClassPatch\SplFileInfoPatch;
              });
          }
      }}
      """
    And the spec file "spec/Doubler/ClassPatchExample2/MarkdownSpec.php" contains:
      """
      <?php

      namespace spec\Doubler\ClassPatchExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class MarkdownSpec extends ObjectBehavior
      {
          function it_handles_files(\SplFileObject $file)
          {
              $this->toHtml('Hi, there')->shouldReturn('<p>Hi, there</p>');
          }
      }

      """
    And the class file "src/Doubler/ClassPatchExample2/Markdown.php" contains:
      """
      <?php

      namespace Doubler\ClassPatchExample2;

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
