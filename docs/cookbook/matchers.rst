Matchers
========

You use matchers in **phpspec** to describe how an object should behave.
They are like assertions in xUnit but with a focus on specifying behaviour
instead of verifying output. You use the matchers prefixed by ``should`` or
``shouldNot`` as appropriate.


**phpspec** has 13 built-in matchers, described in more detail here. Many of these
matchers have aliases which you can use to make your specifications easy to
read.

Identity Matcher
----------------

If you want to specify that a method returns a specific value, you can use
the Identity matcher. It compares the result using the identity operator: ``===``.

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
            $this->getDescription()->shouldEqual("Inexplicably popular children's film");
        }
    }

All four ways of these ways of using the Identity matcher are equivalent.
There is no difference in how they work, this lets you choose the one which
makes your specification easier to read.

Comparison Matcher
------------------

The Comparison matcher is like the Identity matcher. The difference is
that is uses the comparison operator ``==``. So it is not as strict and
follows the PHP rules for loose type comparison.

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

Using ``shouldBeLike`` it does not matter whether ``StarWars::getRating()`` returns
an integer or a string. The spec will pass for 5 and "5".


Throw Matcher
-------------

You can describe an object throwing an exception using the Throw matcher.
You use the Throw matcher by calling it straight from ``$this``, making
the example easier to read.

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

You can also write this as:

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

The first argument of ``during`` is the method name and the second one is
an array of values passed to the method.

You may want to specify the message of the exception. You can do this by
passing an exception object to shouldThrow:

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

If you want to use the Throw matcher to check for exceptions thrown
during object instantiation you can use the ``duringInstantiation``
method.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_not_allow_negative_ratings()
        {
            $this->beConstructedWith(-3);
            $this->shouldThrow('\InvalidArgumentException')->duringInstantiation();
        }
    }

You can also use the Throw matcher with named constructors.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_not_allow_negative_ratings()
        {
            $this->beConstructedThrough('rated', array(-3));
            $this->shouldThrow('\InvalidArgumentException')->duringInstantiation();
        }
    }


Type Matcher
------------

You can specify the type of the object you are describing with the Type matcher.
You can also use this matcher to check that a class implements an interface
or that it extends a class.

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

The ObjectState matcher lets you check the state of an object by calling
methods on it. These methods should start with ``is*`` or ``has*`` and return
a boolean.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_be_available_on_cinemas()
        {
            // calls isAvailableOnCinemas()
            $this->shouldBeAvailableOnCinemas();
        }

        function it_should_have_soundtrack()
        {
            // calls hasSoundtrack()
            $this->shouldHaveSoundtrack();
        }
    }

The spec will pass if the object has ``isAvailableOnCinemas`` and ``hasSoundtrack``
methods which both return true:

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

You can check the number of items in the return value using the Count matcher.
The returned value could be an array or an object that implements the
``\Countable`` interface.

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

To specify that the value returned by a method should be a specific primitive
type you can use the Scalar matcher. It's like using one of the ``is_*`` functions,
e.g, ``is_bool``, ``is_integer``, ``is_float``, etc.

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

You can specify that a method should return an array that contains a given
value with the ArrayContain matcher. **phpspec** matches the value by
identity (``===``).

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


ArrayKeyWithValue Matcher
--------------------

This matcher lets you assert a specific value for a specific key on a method that returns an array or an implementor of ArrayAccess.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_jane_smith_in_the_cast_with_a_lead_role()
        {
            $this->getCast()->shouldHaveKeyWithValue('leadRole', 'John Smith');
        }
    }


ArrayKey Matcher
----------------

You can specify that a method should return an array or an ArrayAccess object
with a specific key using the ArrayKey matcher.

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

The StringStarts matcher lets you specify that a method should return a string
starting with a given substring.

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

The StringEnd matcher lets you specify that a method should return a string
ending with a given substring.

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

The StringRegex matcher lets you specify that a method should return a string
matching a given regular expression.

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

You can create custom matchers using the Inline matcher.

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
                'haveKey' => function ($subject, $key) {
                    return array_key_exists($key, $subject);
                },
                'haveValue' => function ($subject, $value) {
                    return in_array($value, $subject);
                },
            ];
        }
    }
