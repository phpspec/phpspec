Let and Let Go
==============

If you need to pass the object into the constructor instead of a method
then you can do it like this:

.. code-block:: php

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

.. code-block:: php

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

.. code-block:: php

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
