Matchers
========

You use matchers in **phpspec** to describe how an object should behave.
They are like assertions in xUnit but with a focus on specifying behaviour
instead of verifying output. You use the matchers prefixed by ``should`` or
``shouldNot`` as appropriate.


**phpspec** has 14 built-in matchers, described in more detail here. Many of these
matchers have aliases which you can use to make your specifications easy to
read.

Custom matchers classes can be registered in :doc:`configuration<cookbook/configuration>`.

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

All four of these ways of using the Identity matcher are equivalent.
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

Approximately Matcher
--------------------------

If you want to specify that a method returns a value that approximates to
a certain precision the given value, you can use the Approximately matcher.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_return_a_near_value()
        {
            $this->getRating()->shouldBeApproximately(1.444447777, 1.0e-9);
            $this->getRating()->shouldBeEqualToApproximately(1.444447777, 1.0e-9);
            $this->getRating()->shouldEqualApproximately(1.444447777, 1.0e-9);
            $this->getRating()->shouldReturnApproximately(1.444447777, 1.0e-9);
        }
    }

The first argument is the value we expect, the second is the delta.

All four of these ways of using the Approximately matcher are equivalent. There is no difference in how they work,
this lets you choose the one which makes your specification easier to read.

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


Trigger Matcher
---------------

Let's say you have the following class and a method which is deprecated

.. code-block:: php

    <?php

    class Movie
    {
        function setStars($value)
        {
            trigger_error('The method setStars is deprecated. Use setRating instead', E_USER_DEPRECATED);

            $this->rating = $value * 4;
        }
    }


You can describe an object triggering an error using the Trigger matcher.
You use the Trigger matcher by calling it straight from ``$this``, making
the example easier to read.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function set_stars_should_be_deprecated()
        {
            $this->shouldTrigger(E_USER_DEPRECATED)->duringSetStars(4);
        }
    }

You may want to specify the message of the error. You can do this by
adding a string parameter to the `shouldTrigger` method :

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function set_stars_should_be_deprecated()
        {
            $this->shouldTrigger(E_USER_DEPRECATED, 'The method setStars is deprecated. Use setRating instead')->duringSetRating(4);
        }
    }

.. note::

    As with the Throw matcher, you can also use the `during` syntax described
    in the Throw section, or use the instantiation mechanisms (such as
    duringInstantiation, ... etc)


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
``\Countable`` or ``\Traversable`` interface.

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


IterableContain Matcher
-----------------------

You can specify that a method should return an array or an implementor of ``\Traversable`` that contains a given
value with the IterableContain matcher. **phpspec** matches the value by
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


IterableKeyWithValue Matcher
----------------------------

This matcher lets you assert a specific value for a specific key on a method that returns
an array or an implementor of ``\ArrayAccess`` or ``\Traversable``.
**phpspec** matches both the key and value by identity (``===``).

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


IterableKey Matcher
-------------------

You can specify that a method should return an array or an object implementing ``\ArrayAccess`` or ``\Traversable``
with a specific key using the IterableKey matcher. **phpspec** matches the key by identity (``===``).

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


IterateAs Matcher
-----------------

This matcher lets you specify that a method should return an array or an object implementing ``\Traversable`` that
iterates just as the argument you passed to it. **phpspec** matches both the key and the value by identity (``===``).

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_jane_smith_and_john_smith_in_the_cast()
        {
            $this->getCast()->shouldIterateAs(new \ArrayIterator(['Jane Smith', 'John Smith']));
            $this->getCast()->shouldYield(new \ArrayIterator(['Jane Smith', 'John Smith']));
        }
    }

Both of these ways of using the IterateAs matcher are equivalent.
There is no difference in how they work, this lets you choose the one which
makes your specification easier to read.


StartIteratingAs Matcher
------------------------

This matcher lets you specify that a method should return an array or an object implementing ``\Traversable`` that
starts iterating just as the argument you passed to it. **phpspec** matches both the key and the value by identity (``===``).

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_contain_at_least_jane_smith_in_the_cast()
        {
            $this->getCast()->shouldStartIteratingAs(new \ArrayIterator(['Jane Smith']));
            $this->getCast()->shouldStartYielding(new \ArrayIterator(['Jane Smith']));
        }
    }

Both of these ways of using the StartIteratingAs matcher are equivalent.
There is no difference in how they work, this lets you choose the one which
makes your specification easier to read.


StringContain Matcher
---------------------

The StringContain matcher lets you specify that a method should return a string
containing a given substring. This matcher is case sensitive.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_a_title_that_contains_wizard()
        {
            $this->getTitle()->shouldContain('Wizard');
        }
    }


StringStart Matcher
-------------------

The StringStart matcher lets you specify that a method should return a string
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

You can create custom matchers by providing them in ``getMatchers`` method.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_some_specific_options_by_default()
        {
            $this->getOptions()->shouldHaveKey('username');
            $this->getOptions()->shouldHaveValue('diegoholiveira');
        }

        public function getMatchers(): array
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

In order to print a more verbose error message
your inline matcher should throw `FailureException`:

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use PhpSpec\Exception\Example\FailureException;

    class MovieSpec extends ObjectBehavior
    {
        function it_should_have_some_specific_options_by_default()
        {
            $this->getOptions()->shouldHaveKey('username');
            $this->getOptions()->shouldHaveValue('diegoholiveira');
        }

        public function getMatchers(): array
        {
            return [
                'haveKey' => function ($subject, $key) {
                    if (!array_key_exists($key, $subject)) {
                        throw new FailureException(sprintf(
                            'Message with subject "%s" and key "%s".',
                            $subject, $key
                        ));
                    }
                    return true;
                }
            ];
        }
    }
