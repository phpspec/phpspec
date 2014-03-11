Feature: Developer uses matcher while traversing subject
  As a Developer
  I want to be able to use matchers on traveresed elements
  In order to more eloquently express my expectations of a subject

    Scenario: comparison alias matches when traversing an array
    Given the spec file "spec/Matchers/TraversalExample1/ProcessorSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversalExample1;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class ProcessorSpec extends ObjectBehavior
    {
        function it_returns_an_array_of_integers()
        {
            $result = $this->process(array(1,2,3));

            foreach ($result as $element) {
                $element->shouldBeInteger();
            }

            $element->shouldBe(3);
        }
    }
    """

    And the class file "src/Matchers/TraversalExample1/Processor.php" contains:
    """
    <?php

    namespace Matchers\TraversalExample1;

    class Processor
    {
        public function process($arg)
        {
            return $arg;
        }
    }
    """

    When I run phpspec
    Then the suite should pass


  Scenario: comparison alias matches when traversing an array access object
    Given the spec file "spec/Matchers/TraversalExample2/ProcessorSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversalExample2;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class ProcessorSpec extends ObjectBehavior
    {
        function it_returns_an_array_of_integers()
        {
            $result = $this->process(new \ArrayIterator(array(1,2,3,4)));

            foreach ($result as $element) {
                $element->shouldBeInteger();
            }

            $element->shouldBe(4);
        }
    }
    """

    And the class file "src/Matchers/TraversalExample2/Processor.php" contains:
    """
    <?php

    namespace Matchers\TraversalExample2;

    class Processor
    {
        public function process($arg)
        {
            return $arg;
        }
    }
    """

    When I run phpspec
    Then the suite should pass

  Scenario: comparison alias matches when traversing an array hash
    Given the spec file "spec/Matchers/TraversalExample3/ProcessorSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversalExample3;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class ProcessorSpec extends ObjectBehavior
    {
        function it_returns_an_array_hash()
        {
            $result = $this->process(array(
                'one' => 'foo',
                'two' => 'bar'
            ));

            foreach ($result as $element) {
                $element->shouldBeString();
            }

            $element->shouldBe('bar');
        }
    }
    """

    And the class file "src/Matchers/TraversalExample3/Processor.php" contains:
    """
    <?php

    namespace Matchers\TraversalExample3;

    class Processor
    {
        public function process($arg)
        {
            return $arg;
        }
    }
    """

    When I run phpspec
    Then the suite should pass

  Scenario: comparison alias matches when traversing an iterable object that has no array access
    Given the spec file "spec/Matchers/TraversalExample4/ProcessorSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\TraversalExample4;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    use Matchers\TraversalExample4\SimpleIterator;

    class ProcessorSpec extends ObjectBehavior
    {
        function it_returns_a_traversable_object()
        {
            $result = $this->process(new SimpleIterator());

            foreach ($result as $element) {
                $element->shouldBeInteger();
            }

            $element->shouldBe(12);
        }
    }
    """

    And the class file "src/Matchers/TraversalExample4/Processor.php" contains:
    """
    <?php

    namespace Matchers\TraversalExample4;

    class Processor
    {
        public function process($arg)
        {
            return $arg;
        }
    }
    """

    And the class file "src/Matchers/TraversalExample4/SimpleIterator.php" contains:
    """
    <?php

    namespace Matchers\TraversalExample4;

    class SimpleIterator implements \Iterator
    {
        private $value;

        public function current()
        {
            return $this->value;
        }

        public function next()
        {
            $this->value += 4;
        }

        public function valid()
        {
            return $this->value < 13;
        }

        public function rewind()
        {
            $this->value = 0;
        }

        public function key()
        {
        }
    }
    """

    When I run phpspec
    Then the suite should pass