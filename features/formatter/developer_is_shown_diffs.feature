Feature: Developer is shown diffs
  In order to debug failing tests
  As a developer
  I should be shown a detailed diff when expected values do not match

  Scenario: String diffing
    Given the spec file "spec/Diffs/DiffExample1/ClassWithStringsSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample1;

      use PhpSpec\ObjectBehavior;

      class ClassWithStringsSpec extends ObjectBehavior
      {
          function it_is_equal()
          {
              $this->getString()->shouldReturn('foo');
          }
      }

      """
    And the class file "src/Diffs/DiffExample1/ClassWithStrings.php" contains:
      """
      <?php

      namespace Diffs\DiffExample1;

      class ClassWithStrings
      {
          public function getString()
          {
              return 'bar';
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
       @@ -1,1 +1,1 @@
            -foo
            +bar
      """

  Scenario: Array diffing
    Given the spec file "spec/Diffs/DiffExample2/ClassWithArraysSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample2;

      use PhpSpec\ObjectBehavior;

      class ClassWithArraysSpec extends ObjectBehavior
      {
          function it_is_equal()
          {
              $this->getArray()->shouldReturn(array(
                'int' => 1,
                'string' => 'foo'
              ));
          }
      }

      """
    And the class file "src/Diffs/DiffExample2/ClassWithArrays.php" contains:
      """
      <?php

      namespace Diffs\DiffExample2;

      class ClassWithArrays
      {
          public function getArray()
          {
              return array(
                'int' => 3,
                'string' => 'bar'
              );
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
            @@ -1,4 +1,4 @@
               [
            -    int => 1,
            -    string => "foo",
            +    int => 3,
            +    string => "bar",
               ]
      """

  Scenario: Array of object diffing
    Given the spec file "spec/Diffs/DiffExample2/ClassWithArraysOfObjectsSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample2;

      use PhpSpec\ObjectBehavior;

      class ClassWithArraysOfObjectsSpec extends ObjectBehavior
      {
          function it_is_equal()
          {
              $std = new \stdClass;
              $std->test = 'anotherProperty';
              $this->getArray()->shouldBeLike([$std]);
          }
      }

      """
    And the class file "src/Diffs/DiffExample2/ClassWithArraysOfObjects.php" contains:
      """
      <?php

      namespace Diffs\DiffExample2;

      class ClassWithArraysOfObjects
      {
          public function getArray()
          {
              $std = new \stdClass;
              $std->property = 'testValue';
              $std->hash = 'fooHash';

              return [$std];
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
          -        'test' => 'anotherProperty'
      """
    And I should see:
      """
          +        'property' => 'testValue'
      """
    And I should see:
      """
          +        'hash' => 'fooHash'
      """

  Scenario: Object diffing
    Given the spec file "spec/Diffs/DiffExample3/ClassWithObjectsSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample3;

      use PhpSpec\ObjectBehavior;

      class ClassWithObjectsSpec extends ObjectBehavior
      {
          function it_is_equal()
          {
              $obj = new \StdClass;
              $obj->i = 1;
              $obj->s = 'foo';

              $this->getObject()->shouldReturn($obj);
          }
      }

      """
    And the class file "src/Diffs/DiffExample3/ClassWithObjects.php" contains:
      """
      <?php

      namespace Diffs\DiffExample3;

      class ClassWithObjects
      {
          public function getObject()
          {
              $obj = new \StdClass;
              $obj->i = 2;
              $obj->s = 'bar';

              return $obj;
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
            -    'i' => 1
            -    's' => 'foo'
      """
    And I should see:
      """
            +    'i' => 2
            +    's' => 'bar'
      """


  Scenario: Unexpected method arguments call arguments string diffing
    Given the spec file "spec/Diffs/DiffExample4/ClassUnderSpecificationSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample4;

      use PhpSpec\ObjectBehavior;
      use Diffs\DiffExample4\ClassBeingMocked;

      class ClassUnderSpecificationSpec extends ObjectBehavior
      {
          function it_can_do_work(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->setValue('some really really long string, and even more, and more!')->shouldBeCalled();
              $this->doWork($objectBeingMocked);
          }
      }
      """
    And the class file "src/Diffs/DiffExample4/ClassUnderSpecification.php" contains:
      """
      <?php

      namespace Diffs\DiffExample4;

      class ClassUnderSpecification
      {
          public function doWork(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->setValue('some really really long string, and even more, and more');
          }
      }
      """
    And the class file "src/Diffs/DiffExample4/ClassBeingMocked.php" contains:
      """
      <?php

      namespace Diffs\DiffExample4;

      class ClassBeingMocked
      {
          public function setValue($value)
          {
          }
      }
      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
            @@ -1,1 +1,1 @@
            -some really really long string, and even more, and more!
            +some really really long string, and even more, and more
      """


  Scenario: Unexpected method arguments call arguments array diffing
    Given the spec file "spec/Diffs/DiffExample5/ClassUnderSpecificationSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample5;

      use PhpSpec\ObjectBehavior;
      use Diffs\DiffExample5\ClassBeingMocked;

      class ClassUnderSpecificationSpec extends ObjectBehavior
      {
          function it_can_do_work(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->setValue(array(
                  'key1' => 'val1',
                  'key2' => 'val2',
              ))->shouldBeCalled();
              $this->doWork($objectBeingMocked);
          }
      }
      """
    And the class file "src/Diffs/DiffExample5/ClassUnderSpecification.php" contains:
      """
      <?php

      namespace Diffs\DiffExample5;

      class ClassUnderSpecification
      {
          public function doWork(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->setValue(array(
                  'key1' => 'val1',
                  'key5' => 'val5',
              ));
          }
      }
      """
    And the class file "src/Diffs/DiffExample5/ClassBeingMocked.php" contains:
      """
      <?php

      namespace Diffs\DiffExample5;

      class ClassBeingMocked
      {
          public function setValue($value)
          {
          }
      }
      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
            @@ -1,4 +1,4 @@
               [
                 key1 => "val1",
            -    key2 => "val2",
            +    key5 => "val5",
               ]
      """

  Scenario: Unexpected method arguments call with multiple arguments including null diffing
    Given the spec file "spec/Diffs/DiffExample6/ClassUnderSpecificationSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample6;

      use PhpSpec\ObjectBehavior;
      use Diffs\DiffExample6\ClassBeingMocked;

      class ClassUnderSpecificationSpec extends ObjectBehavior
      {
          function it_can_do_work(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->setValue(array(
                  'key' => 'value'
              ), 'foo', null)->shouldBeCalled();
              $this->doWork($objectBeingMocked);
          }
      }
      """
    And the class file "src/Diffs/DiffExample6/ClassUnderSpecification.php" contains:
      """
      <?php

      namespace Diffs\DiffExample6;

      class ClassUnderSpecification
      {
          public function doWork(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->setValue(array(
                'key' => 'another value'
              ), 'foo', 'bar');
          }
      }
      """
    And the class file "src/Diffs/DiffExample6/ClassBeingMocked.php" contains:
      """
      <?php

      namespace Diffs\DiffExample6;

      class ClassBeingMocked
      {
          public function setValue($value)
          {
          }
      }
      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
            @@ -1,3 +1,3 @@
               [
            -    key => "value",
            +    key => "another value",
               ]
      """
    And I should see:
      """
            @@ -1,1 +1,1 @@
            -null
            +bar
      """

  Scenario: Unexpected method call
    Given the spec file "spec/Diffs/DiffExample7/ClassUnderSpecificationSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample7;

      use PhpSpec\ObjectBehavior;
      use Diffs\DiffExample7\ClassBeingMocked;

      class ClassUnderSpecificationSpec extends ObjectBehavior
      {
          function it_can_do_work(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->methodOne('value')->shouldBeCalled();
              $this->doWork($objectBeingMocked);
          }
      }
      """
    And the class file "src/Diffs/DiffExample7/ClassUnderSpecification.php" contains:
      """
      <?php

      namespace Diffs\DiffExample7;

      class ClassUnderSpecification
      {
          public function doWork(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->methodTwo('value');
          }
      }
      """
    And the class file "src/Diffs/DiffExample7/ClassBeingMocked.php" contains:
      """
      <?php

      namespace Diffs\DiffExample7;

      class ClassBeingMocked
      {
          public function methodOne($value)
          {
          }

          public function methodTwo($value)
          {
          }

      }
      """
    When I run phpspec with the "verbose" option
    Then I should see the error that 'methodTwo("value")' was not expected on "Double\Diffs\DiffExample7\ClassBeingMocked\P13"

  Scenario: Unexpected method call when another prophecy for that call with not matching arguments exists
    Given the spec file "spec/Diffs/DiffExample8/ClassUnderSpecificationSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample8;

      use PhpSpec\ObjectBehavior;
      use Diffs\DiffExample8\ClassBeingMocked;

      class ClassUnderSpecificationSpec extends ObjectBehavior
      {
          function it_can_do_work(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->methodTwo('value')->shouldBeCalled();
              $objectBeingMocked->methodOne('another value')->shouldBeCalled();

              $this->doWork($objectBeingMocked);
          }
      }
      """
    And the class file "src/Diffs/DiffExample8/ClassUnderSpecification.php" contains:
      """
      <?php

      namespace Diffs\DiffExample8;

      class ClassUnderSpecification
      {
          public function doWork(ClassBeingMocked $objectBeingMocked)
          {
              $objectBeingMocked->methodTwo('value');
              $objectBeingMocked->methodTwo('another value');
          }
      }
      """
    And the class file "src/Diffs/DiffExample8/ClassBeingMocked.php" contains:
      """
      <?php

      namespace Diffs\DiffExample8;

      class ClassBeingMocked
      {
          public function methodOne($value)
          {
          }

          public function methodTwo($value)
          {
          }

      }
      """
    When I run phpspec with the "verbose" option
    Then I should see the error that 'methodTwo("another value")' was not expected on "Double\Diffs\DiffExample8\ClassBeingMocked\P14"

  Scenario: Array diffing with long strings
    Given the spec file "spec/Diffs/DiffExample9/ClassWithArraysSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample9;

      use PhpSpec\ObjectBehavior;

      class ClassWithArraysSpec extends ObjectBehavior
      {
          function it_is_equal()
          {
              $this->getArray()->shouldReturn(array(
                'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam nunc nulla, posuere et arcu ut.'
              ));
          }
      }

      """
    And the class file "src/Diffs/DiffExample9/ClassWithArrays.php" contains:
      """
      <?php

      namespace Diffs\DiffExample9;

      class ClassWithArrays
      {
          public function getArray()
          {
              return array(
                  'Vestibulum vehicula nisl at ex maximus, nec lobortis orci luctus. Integer euismod in nunc nec lobortis'
              );
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
            @@ -1,3 +1,3 @@
               [
            -    0 => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam nunc nulla, posuere et arcu ut.",
            +    0 => "Vestibulum vehicula nisl at ex maximus, nec lobortis orci luctus. Integer euismod in nunc nec lobortis",
               ]
      """

  Scenario: Array diffing with multi line strings
    Given the spec file "spec/Diffs/DiffExample10/ClassWithArraysSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample10;

      use PhpSpec\ObjectBehavior;

      class ClassWithArraysSpec extends ObjectBehavior
      {
          function it_is_equal()
          {
              $this->getArray()->shouldReturn(array(
                  'Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                  Etiam nunc nulla, posuere et arcu ut.'
              ));
          }
      }

      """
    And the class file "src/Diffs/DiffExample10/ClassWithArrays.php" contains:
      """
      <?php

      namespace Diffs\DiffExample10;

      class ClassWithArrays
      {
          public function getArray()
          {
              return array(
                  'Vestibulum vehicula nisl at ex maximus, nec lobortis orci luctus.
                  Integer euismod in nunc nec lobortis'
              );
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
            @@ -1,4 +1,4 @@
               [
            -    0 => "Lorem ipsum dolor sit amet, consectetur adipiscing elit.
            -            Etiam nunc nulla, posuere et arcu ut.",
            +    0 => "Vestibulum vehicula nisl at ex maximus, nec lobortis orci luctus.
            +            Integer euismod in nunc nec lobortis",
               ]
      """

  Scenario: Integer diff in verbose mode
    Given the spec file "spec/Diffs/DiffExample11/CalculatorSpec.php" contains:
      """
      <?php

      namespace spec\Diffs\DiffExample11;

      use PhpSpec\ObjectBehavior;

      class CalculatorSpec extends ObjectBehavior
      {
          function it_is_equal()
          {
              $this->calculate()->shouldReturn(2);
          }
      }

      """
    And the class file "src/Diffs/DiffExample11/Calculator.php" contains:
      """
      <?php

      namespace Diffs\DiffExample11;

      class Calculator
      {
          public function calculate()
          {
              return 1;
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then I should see:
      """
        expected [integer:2], but got [integer:1]
      """
