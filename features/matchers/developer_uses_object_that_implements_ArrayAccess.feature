Feature: Developer uses object implementing ArrayAccess interface
  As a Developer
  I want an object implementing ArrayAccess interface
  In order to validate that I can use it as an array

  @issue1383
  Scenario: I can access the value of an object just like an array
    Given the spec file "spec/Matchers/ObjectImplementsArrayAccess/FooBarSpec.php" contains:
    """
    <?php

    namespace spec\Matchers\ObjectImplementsArrayAccess;

    use PhpSpec\ObjectBehavior;
    use Matchers\ObjectImplementsArrayAccess\Foobar;

    class FooBarSpec extends ObjectBehavior
    {
        function it_is_initializable()
        {
            $this->shouldHaveType(Foobar::class);
        }

        public function it_implements_arrayAccess_offsetSet_and_offsetGet()
        {
            $this->offsetSet('foo', 'bar');
            $this['bar'] = 'foo';

            $this
                ->offsetGet('foo')
                ->shouldReturn('bar');

            $this
                ->offsetGet('bar')
                ->shouldReturn('foo');

            $this['foo']
                ->shouldReturn('bar');

            $this['bar']
                ->shouldReturn('foo');
        }

        public function it_implements_arrayAccess_offsetExists()
        {
            $this->offsetSet('foo', 'bar');
            $this['bar'] = 'foo';

            $this
                ->offsetExists('foo')
                ->shouldReturn(true);

            $this
                ->offsetExists('bar')
                ->shouldReturn(true);

            $this
                ->offsetExists('foobar')
                ->shouldReturn(false);
        }
    }
    """

    And the class file "src/Matchers/ObjectImplementsArrayAccess/FooBar.php" contains:
    """
    <?php

    declare(strict_types=1);

    namespace Matchers\ObjectImplementsArrayAccess;

    use ArrayAccess;

    final class FooBar implements ArrayAccess
    {
        private $storage = [];

        public function offsetExists($offset) {
            return array_key_exists($offset, $this->storage);
        }

        public function offsetGet($offset) {
            return $this->storage[$offset];
        }

        public function offsetSet($offset, $value) {
            $this->storage[$offset] = $value;
        }

        public function offsetUnset($offset) {
            unset($this->storage[$offset]);
        }

    }
    """

    When I run phpspec
    Then the suite should pass
