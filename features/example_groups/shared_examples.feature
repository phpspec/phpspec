Feature: Developer can reuse examples from another spec
  As a developer
  I can use a shared example
  So that I don't need to repeat the description of behaviour that is common to
          more than one type

Scenario: shared examples group included in one file
  Given a file named "ACollectionSpec.php" with:
      """
      <?php
      
      use PHPSpec\Specification\SharedExample;
      
      class ACollectionSharedExamples extends SharedExample
      {
          function before()
          {
              $this->collection = new Collection('one', 'two', 'three');
          }

          function itSaysItHasThreeItems()
          {
              $this->collection->size()->should->equal(3);
          }
      }
      """
  And a file named "MyArraySpec.php" with:
      """
      <?php
      use PHPSpec\Context;
      
      class DescribeMyArray extends Context
      {
          public $itBehavesLike = 'a collection';
      }
      """
  When I run "phpspec MyArraySpec.php -f d"
  Then the output should contain:
      """
      MyArray
        behaves like a collection
          says it has three items
      """
      
  