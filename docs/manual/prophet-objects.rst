Prophet Objects
===============

Stubs
-----

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
to format. You have not yet written any implementations of Reader to pass
into the method though. You do not want to get distracted by creating an implementation
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

.. code-block:: php

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

.. code-block:: php

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
-----

You also need to be able to get your parser to output to somewhere instead
of just returning the formatted text. Again you create an interface:

.. code-block:: php

    <?php

    namespace Markdown;

    interface Writer
    {
        public function writeText($text);
    }

You again pass it to the method but this time the ``Markdown\Writer::writeText($text)``
method does not return something to your parser class. The new method you
are going to create on the parser will not return anything either. Instead
it is going to give the formatted text to the `Markdown\Writer` so you want
to be able to give an example of what that formatted text should be. You
can do this using a mock, the mock gets created in the same way as the stub.
This time you tell it to expect ``Markdown\Writer::writeText($text)``
to get called with a particular value:

.. code-block:: php

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

Spies
-----

Instead of using a mock you could use a spy. The difference is that you check
what happened after the object's behaviour has happened:

.. code-block:: php

    <?php

    namespace spec;

    use PhpSpec\ObjectBehavior;
    use Markdown\Writer;

    class MarkdownSpec extends ObjectBehavior
    {
        function it_outputs_converted_text(Writer $writer)
        {
            $this->outputHtml("Hi, there", $writer);

            $writer->writeText("<p>Hi, there</p>")->shouldHaveBeenCalled();
        }
    }

The difference is one of style. You may prefer to use mocks and say what
should happen beforehand. You may prefer to use spies and say what should
have happened afterwards.
