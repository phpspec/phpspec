Matchers
========

Matchers are much like assertions in xUnit, except the fact that matchers
concentrate on telling how the object should behave instead of verifying how it
works. It just expresses better the focus on behaviour and fits better in the
test-first cycle. There are 13 matchers in phpspec currently, but almost each
one of them has aliases to make your examples read more fluid.

Identity Matcher
----------------

Identity matcher is used to describe that method should return a specific value.
It compare the result using the identity operator: ``===``.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_be_a_great_movie()
        {
            $this->getRating()->shouldBe(5);
            $this->getTitle()->shouldBeEqualTo("Star Wars");
            $this->getReleaseDate()->shouldReturn(233366400);
        }
    }

In order to make our specs more readable phpspec allows us to use four different
variations of the Identity matcher, namely, ``shouldEqual``, ``shouldBeEqualTo``,
``shouldReturn`` and ``shouldBe``. It's worth pointing out that there is no difference
in using one over other from a technical perspective. The final result will be the
same in each case, except that readability of specs might differ.


Comparison Matcher
------------------

Use Comparison matcher to specify that a method should return a specific value
but it's not as strict as the identity matcher. It's pretty much like comparing
the result using the comparison operator ``==``, following PHP rules for loosely
type comparison.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_be_a_great_movie()
        {
            $this->getRating()->shouldBeLike('5');
        }
    }

We have changed the assertion from the previous example. Now, it doesn't matter
whether ``StarWars::rating`` returns an integer or a string, the spec will pass
either way.


Throw Matcher
-------------

Throw matcher should be used to describe cases in which a method throws an
exception. The usage of this matcher is a little bit different, as we call
the matcher straight from ``$this``, which makes reading the example more natural.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_not_allow_negative_ratings()
        {
            $this->shouldThrow('\InvalidArgumentException')->duringSetRating(-3);
        }
    }

The code above could also be written as follows:

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_not_allow_negative_ratings()
        {
            $this->shouldThrow('\InvalidArgumentException')->during('setRating', array(-3));
        }
    }

The first argument of ``during`` is a method name and the second one is
an array of values passed to the method.

You may want to specify the message of the exception. Another possible way to
use the Throw matcher is by passing an exception object to shouldThrow:

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_not_allow_negative_ratings()
        {
            $this->shouldThrow(new \InvalidArgumentException("Invalid rating"))->during('setRating', array(-3));
        }
    }


Type Matcher
------------

Type matcher looks into the type of object being described.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_be_a_movie()
        {
            $this->shouldHaveType('Movie');
            $this->shouldReturnAnInstanceOf('Movie');
            $this->shouldBeAnInstanceOf('Movie');
            $this->shouldImplement('Movie');
        }
    }

All four matcher methods are equivalent and will serve to describe if the object
is a ``Movie`` or not.


ObjectState Matcher
-------------------

ObjectState matcher is used to check some common state validation methods,
typically started with ``is*`` and ``has*``. Similar to what you'd see in
``rspec`` predicate matcher.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_be_available_on_cinemas()
        {
            $this->shouldBeAvailableOnCinemas();
        }

        function it_should_have_soundtrack()
        {
            $this->shouldHaveSoundtrack();
        }
    }

In order to make our specs green the implementation of the ``Movie`` should
provide ``isAvailableOnCinemas`` and ``hasSoundtrack`` methods:

.. code-block:: php

    <?php

    class Movie
    {
        public function isAvailableOnCinemas()
        {
            return true;
        }

        public function hasSoundtrack()
        {
            return true;
        }
    }


Count Matcher
-------------

Use Count matcher to specify the number of items that should be returned by a method.
This return could be either an array or an object that implements the ``\Countable``
interface.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_one_director()
        {
            $this->getDirectors()->shouldHaveCount(1);
        }
    }


Scalar Matcher
--------------

Use Scalar matcher to specify that value returned by a method should be of a
specific primitive type. It's pretty much like using the ``is_*`` function family,
e.g, ``is_bool``, ``is_integer``, ``is_decimal``, etc ..

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_string_as_title()
        {
            $this->getTitle()->shouldBeString();
        }

        function it_should_have_an_array_as_cast()
        {
            $this->getCast()->shouldBeArray();
        }
    }


ArrayContain Matcher
--------------------

Use the ArrayContain matcher to specify that a method should return an array that
contains a given value. Be aware that this value is matched by identity
(``===``).

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_jane_smith_in_the_cast()
        {
            $this->getCast()->shouldContain('Jane Smith');
        }
    }


ArrayKey Matcher
----------------

Use the ArrayKey matcher to specify that a method should return an array (or an
object implementing ``ArrayAccess``) that has a given key.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_release_date_for_france()
        {
            $this->getReleaseDates()->shouldHaveKey('France');
        }
    }


StringStart Matcher
-------------------

Use the StringStarts matcher to specify that a method should return a string that
starts with a given substring.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_title_that_starts_with_the_wizard()
        {
            $this->getTitle()->shouldStartWith('The Wizard');
        }
    }


StringEnd Matcher
-----------------

Use the StringEnd matcher to specify that a method should return a string that
ends with a given substring.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_title_that_ends_with_of_oz()
        {
            $this->getTitle()->shouldEndWith('of Oz');
        }
    }


StringRegex Matcher
-------------------

Use the StringRegex matcher to specify that a method should return a string that
matches a given regular expression.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_title_that_contains_wizard()
        {
            $this->getTitle()->shouldMatch('/wizard/i');
        }
    }


Inline Matcher
--------------

Inline matchers can be used to provide custom expectations not available in phpspec
native matcher, more or specific to your project or domain.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use PhpSpec\Matcher\InlineMatcher;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_some_specific_options_by_default()
        {
            $this->getOptions()->shouldHaveKey('username');
            $this->getOptions()->shouldHaveValue('diegoholiveira');
        }

        public function getMatchers()
        {
            return [
                'haveKey' => function($subject, $key) {
                    return array_key_exists($key, $subject);
                },
                'haveValue' => function($subject, $value) {
                    return in_array($value, $subject);
                },
            ];
        }
    }
