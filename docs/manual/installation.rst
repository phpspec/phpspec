Installation
============

Spec BDD with phpspec
---------------------

**phpspec** is a development tool, designed to help you achieve clean and
working PHP code by using a technique derived from test-first development
called (spec) behaviour driven development, or SpecBDD.

The technique consists of describing the next object behaviour (spec) you are about
to implement, using a tool like **phpspec**, then writing just enough code
to quickly satisfy that specification and finally stopping to refactor the last increment,
allowing the emergent design to guide the direction. This is done in small iterative steps.

Requirements
------------

**phpspec** is a php 5.3 library that you'll have in your project
development environment. Before you begin, ensure that you have at least
PHP 5.3.3 installed.

Method #1 (Composer)
~~~~~~~~~~~~~~~~~~~~

The simplest way to install phpspec with all its dependencies is through
Composer:

Create a ``composer.json`` file in the project root:

.. code-block:: js

    {
        "require-dev": {
            "phpspec/phpspec": "~2.0"
        },
        "config": {
            "bin-dir": "bin"
        },
        "autoload": {"psr-0": {"": "src"}}
    }

Then download the ``composer.phar`` and run the ``install`` command:

.. code-block:: bash

    $ curl http://getcomposer.org/installer | php
    $ php composer.phar install --dev

Everything will be installed inside the ``vendor`` folder and a phpspec executable will
be linked into the ``bin`` folder.

SpecBDD and TDD
---------------

There is no real difference between SpecBDD and TDD. The value of using
an xSpec tool instead of a regular xUnit tool for TDD is **the language**.
The concepts and features of the tool will keep your focus on the "right"
things. The focus on verification and structure as opposed to behaviour and
design is, of course, a valid one. We happen to find that the latter is more
valuable in the long run. It was also the intention of early adopters of TDD.

SpecBDD and StoryBDD
--------------------

While with StoryBDD tools like `Behat <http://behat.org>`_ are used to
understand and clarify the domain - specifying feature narratives, its need, and
what do we mean by them - with SpecBDD we are only focused on the how: the
implementation. You are specifying how your classes will achieve those
features.

A good StoryBDD tool will let the business talk the domain language and drive the
development by putting the focus on what really matters first.

Once you know why you are adding a feature and what it will be, it's almost time to write code.
But not yet! Adding code without a way to validate that it serves the specs just
means you will have to go back and rework it, so that it does match the spec. And the later you
find out you missed the requirement or added a bug, the harder and more expensive
it is to fix. Kent Beck also adds that describing the code before you
actually write it is a fear management technique. You don't have to write all
the code, just the spec of the next thing you want to work on. That executable
spec will then guide you to what code you need to write. Once you do that,
then what you have is a chance to refactor. Because if you change the
behaviour of your code the specs will go red. So you spec so that you can
refactor, or allow the design of your code to emerge in a sustainable way. SpecBDD tools are
designed to guide you in the process, or at least not stand on the way.

It's valid to assume that StoryBDD and SpecBDD used together are a very
effective way to achieve highly customer-focused software.

Getting Started
---------------

Say we are building a tool that converts
`Markdown <http://en.wikipedia.org/wiki/Markdown>`_ into HTML. Well, that's a large
task. But I will work on simple things first and a design will emerge that will reach all
the necessary features. Even though I have all the specs from the customer
(we have done all our Behat feature files nicely), I know I will discover new things I
will need, as soon as I sit down to write my classes.

What is the simplest thing I want to add? It should convert a string line into a
paragraph with HTML markup, i.e. `"Hi, there"` would become `"<p>Hi, there</p>"`.

So let's do this. Well, not the boring bits. Let **phpspec** take care of the
boring stuff for us. We just need to tell **phpspec** we will be working on
the `Markdown` class.

.. code-block:: bash

    $ bin/phpspec desc Markdown
    Specification for Markdown created in spec.

You can also specify a fully qualified class name. Don\'t forget that if you
use backslashes you need to pass the class name inside double quotes.
Alternatively you could use forward slashes and skip the quotes. **phpspec**
will create the folder structure following PSR standards.

Ok. What have we just done? **phpspec** has created the spec for us. You can
navigate to the spec folder and see the spec there:

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_is_initializable()
        {
            $this->shouldHaveType('Markdown');
        }
    }

That's awesome! **phpspec** created the spec for me.

But first, let's see what we have here. Our spec extends the special
``ObjectBehavior`` class.
This class is special, because it gives you ability to call all the methods of the
class you are describing and match the result of the operations against your
expectations.

Examples
~~~~~~~~

The object behavior is made of examples. Examples are encased in public methods,
started with ``it_``.
or ``its_``.
**phpspec** searches for such methods in your specification to run.
Why underscores for example names? ``just_because_its_much_easier_to_read``
than ``someLongCamelCasingLikeThat``.

Matchers
~~~~~~~~

Matchers are much like assertions in xUnit, except the fact that matchers
concentrate on telling how the object **should** behave instead of verifying how
it works. It just expresses better the focus on behaviour and fits better in the test-first cycle.
There are 5 matchers in **phpspec** currently, but almost each
one of them has aliases to make your examples read more fluid:

* Identity (return, be, equal, beEqualTo) - it's like checking ``===``
* Comparison (beLike) - it's like checking ``==``
* Throw (throw -> during) - for testing exceptions
* Type (beAnInstanceOf, returnAnInstanceOf, haveType) - checks object type
* ObjectState (have**) - checks object ``is**`` method return value

How do you use those? You're just prefixing them with ``should`` or ``shouldNot``
depending on what you expect and call them on subject of interest.

Now we are ready to move on. Let's update that first example to express my next intention:

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_converts_plain_text_to_html_paragraphs()
        {
            $this->toHtml("Hi, there")->shouldReturn("<p>Hi, there</p>");
        }
    }

Now what? We run the specs. You may not believe this, but **phpspec** will
understand we are describing a class that doesn't exist and offer to create it!

.. code-block:: bash

    $ bin/phpspec run

    > spec\Markdown

      ✘ it converts plain text to html paragraphs
          Class Markdown does not exists.

             Do you want me to create it for you? [Y/n]

**phpspec** will then place the empty class in the directory. You run your
spec again and... OK, you guessed:

.. code-block:: bash

    $ bin/phpspec run

    > spec\Markdown

      ✘ it converts plain text to html paragraphs
          Method Markdown::toHtml() not found.

             Do you want me to create it for you? [Y/n]

What we just did was moving fast through the amber state into the red.

.. code-block:: php

    <?php

    class Markdown
    {
        public function toHtml($argument1)
        {
            // TODO: write logic here
        }
    }

We got rid of the fatal errors and ugly messages that resulted from nonnexistent
classes and methods and went straight into a real failed spec:

.. code-block:: bash

    $ bin/phpspec run

    > spec\Markdown

      ✘ it converts plain text to html paragraphs
          Expected "<p>Hi, there</p>", but got null.


    1 examples (1 failed)
    284ms

According to the TDD rules we now have full permission to write code. Red
means "time to add code"; red is great! Now we add just enough code to make
the spec green, quickly. There will be time to get it right, but first just
get it green.

.. code-block:: php

    <?php

    class Markdown
    {
        public function toHtml()
        {
            return "<p>Hi, there</p>";
        }
    }

And voilà:

.. code-block:: bash

    $ bin/phpspec run

    > spec\Markdown

      ✔ it converts plain text to html paragraphs

    1 examples (1 passed)
    247ms

I will leave the explanation of the whole TDD/SpecBDD cycle for a blog post.
There are heaps of resources out there already. Here are just a couple for you look at:


1. `The Rspec Book <http://www.amazon.com/RSpec-Book-Behaviour-Development-Cucumber/dp/1934356379>`_
   Development with RSpec, Cucumber, and Friends
   by David Chelimsky, Dave Astels, Zach Dennis, Aslak Hellesøy, Bryan
   Helmkamp, Dan North

2. `Test Driven Development: By Example <http://www.amazon.com/Test-Driven-Development-Kent-Beck/dp/0321146530>`_
   Kent Beck

Prophet Objects
---------------

Stubs
~~~~~

You also need your `Markdown` parser to be able to format text fetched from
an external source such as a file. You decide to create an interface so that
you can have different implementations for different types of source.

.. code-block:: php

    <?php

    namespace Markdown;

    interface Reader
    {
        public function getMarkdown();
    }


You want to describe a method which has an instance of a `Reader` as an
argument. It will call ``Markdown\Reader::getMarkdown()`` to get the markdown
to format. You have not you written any implementations of Reader to pass
into the method though. You do want to get distracted by creating an implementation
before you can carry on writing the parser. Instead we can create a fake
version of Reader called a stub and tell **phpspec** what ``Markdown\Reader::getMarkdown()``
should return.

You can create a stub by telling **phpspec** that you want it to be a
double of the `Markdown\Reader` interface:

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_converts_text_from_an_external_source($reader)
        {
            $reader->beADoubleOf('Markdown\Reader');
            $this->toHtmlFromReader($reader)->shouldReturn("<p>Hi, there</p>");
        }
    }

At the moment calling ``Markdown\Reader::getMarkdown()`` will return null.
We can tell **phpspec** what we want it to return though.

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_converts_text_from_an_external_source($reader)
        {
            $reader->beADoubleOf('Markdown\Reader');
            $reader->getMarkdown()->willReturn("Hi, there");

            $this->toHtmlFromReader($reader)->shouldReturn("<p>Hi, there</p>");
        }
    }

Now you can write the code that will get this example to pass. As well as
refactoring your implementation you should see if you can refactor your specs
once they are passing. In this case we can tidy it up a bit as **phpspec**
lets you create the stub in an easier way. You can pass in a variable to
the example and use an `@param` docblock to tell it what type it should have:

 <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;

    class MarkdownSpec extends ObjectBehavior
    {
        /**
         * @param Markdown\Reader $reader
         */
        function it_converts_text_from_an_external_source($reader)
        {
            $reader->getMarkdown()->willReturn("Hi, there");

            $this->toHtmlFromReader($reader)->shouldReturn("<p>Hi, there</p>");
        }
    }

We can improve this further by instead using a type hint which **phpspec**
will use to determine the type of the stub:

<?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use Markdown\Reader;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_converts_text_from_an_external_source(Reader $reader)
        {
            $reader->getMarkdown()->willReturn("Hi, there");

            $this->toHtmlFromReader($reader)->shouldReturn("<p>Hi, there</p>");
        }
    }

Mocks
~~~~~

You also need to be able to get your parser to output to somewhere instead
of just returning the formatted text. Again you create an interface:

.. code-block:: php

    <?php

    namespace Markdown;

    interface Writer
    {
        public function writeText($text);
    }

You again pass it to to the method but this time the ``Markdown\Writer::writeText($text)``
method does not return something to your parser class. The new method you
are going to create on the parser will not return anything either. Instead
it is going to give the formatted text to the `Markdown\Writer` so you want
to be able to give an example of what that formatted text should be. You
can do this using a mock, the mock gets created in the same way as the stub.
This this time you tell it to expect ``Markdown\Writer::writeText($text)``
to get called with a particular value:

<?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use Markdown\Writer;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_outputs_converted_text(Writer $writer)
        {
            $writer->writeText("<p>Hi, there</p>")->shouldBeCalled();

            $this->outputHtml("Hi, there", $writer);
        }
    }

Now if the method is not called with that value then the example will
fail.

Let and Let Go
--------------

If you need to pass the object into the constructor instead of a method
then you can do it like this:

<?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use Markdown\Writer;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_outputs_converted_text(Writer $writer)
        {
            $this->beConstructedWith($writer);
            $writer->writeText("<p>Hi, there</p>")->shouldBeCalled();

            $this->outputHtml("Hi, there");
        }
    }

If you have many examples then writing this in each example will get
tiresome. You can instead move this to a `let` method. The `let` method
gets run before each example so each time the parser gets constructed with
a fresh mock object.

<?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use Markdown\Writer;

    class MarkdownSpec extends ObjectBehavior
    {
        function let(Writer $writer)
        {
            $this->beConstructedWith($writer);
        }
    }

There is also a `letGo` method which runs after each example if you need
to clean up after the examples.

It looks like you will now have difficulty getting hold of the instance
of the mock object in the examples. This is easier to deal with than it looks
though. Providing you use the same variable name for both, **phpspec** will
inject the same instance into the `let` method and the example. The following
will work:

<?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use Markdown\Writer;

    class MarkdownSpec extends ObjectBehavior
    {
        function let(Writer $writer)
        {
            $this->beConstructedWith($writer);
        }

        function it_outputs_converted_text($writer)
        {
            $writer->writeText("<p>Hi, there</p>")->shouldBeCalled();

            $this->outputHtml("Hi, there");
        }
    }


Cookbook
--------

Other useful resources for learning/using **phpspec**:

.. toctree::
    :maxdepth: 1

    ../cookbook/matchers
    ../cookbook/templates
    ../cookbook/configuration
