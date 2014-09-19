Getting Started
===============

Say you are building a tool that converts
`Markdown <http://en.wikipedia.org/wiki/Markdown>`_ into HTML. Well, that's a large
task. But you can work on simple things first and a design will emerge that will reach all
the necessary features.

What is the simplest thing you could add? It should convert a string line into a
paragraph with HTML markup, i.e. `"Hi, there"` would become `"<p>Hi, there</p>"`.

So you can start by doing this. Well, not the boring bits. Let **phpspec** take care of the
boring stuff for you. You just need to tell **phpspec** you will be working on
the `Markdown` class.

.. code-block:: bash

    $ bin/phpspec desc Markdown
    Specification for Markdown created in spec.

You can also specify a fully qualified class name. Don\'t forget that if you
use backslashes you need to pass the class name inside double quotes.
Alternatively you could use forward slashes and skip the quotes. **phpspec**
will create the folder structure following PSR standards.

Ok. What have you just done? **phpspec** has created the spec for you! You can
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

So what do you have here? Your spec extends the special ``ObjectBehavior`` class.
This class is special, because it gives you the ability to call all the methods of the
class you are describing and match the result of the operations against your
expectations.

Examples
--------

The object behavior is made up of examples. Examples are encased in public methods,
started with ``it_``.
or ``its_``.
**phpspec** searches for these methods in your specification to run.
Why are underscores used in example names? ``just_because_its_much_easier_to_read``
than ``someLongCamelCasingLikeThat``.

Specifying behaviour
--------------------

Now we are ready to move on. Let's update that first example to express your next intention:

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

Here you are telling **phpspec** that your object has a ``toHtml`` method.
You are also telling it that this method should return "<p>Hi, there</p>".
Now what? Run the specs. You may not believe this, but **phpspec** will
understand you are describing a class that doesn't exist and offer to create it!

.. code-block:: bash

    $ bin/phpspec run

    > spec\Markdown

      ✘ it converts plain text to html paragraphs
          Class Markdown does not exist.

             Do you want me to create it for you? [Y/n]

**phpspec** will then place the empty class in the directory. Run your
spec again and... OK, you guessed:

.. code-block:: bash

    $ bin/phpspec run

    > spec\Markdown

      ✘ it converts plain text to html paragraphs
          Method Markdown::toHtml() not found.

             Do you want me to create it for you? [Y/n]

What you just did was moving fast through the amber state into the red.

.. code-block:: php

    <?php

    class Markdown
    {
        public function toHtml($argument1)
        {
            // TODO: write logic here
        }
    }

You got rid of the fatal errors and ugly messages that resulted from non-existent
classes and methods and went straight into a real failed spec:

.. code-block:: bash

    $ bin/phpspec run

    > spec\Markdown

      ✘ it converts plain text to html paragraphs
          Expected "<p>Hi, there</p>", but got null.


    1 examples (1 failed)
    284ms

You can change the generated specs and classes using :doc:`templates </cookbook/templates>`.

According to the TDD rules you now have full permission to write code. Red
means "time to add code"; red is great! Now you can add just enough code to make
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

There are heaps of resources out there already if you would like to read more about
the TDD/SpecBDD cycle. Here are just a couple for you look at:


1. `The Rspec Book <http://www.amazon.com/RSpec-Book-Behaviour-Development-Cucumber/dp/1934356379>`_
   Development with RSpec, Cucumber, and Friends
   by David Chelimsky, Dave Astels, Zach Dennis, Aslak Hellesøy, Bryan
   Helmkamp, Dan North

2. `Test Driven Development: By Example <http://www.amazon.com/Test-Driven-Development-Kent-Beck/dp/0321146530>`_
   Kent Beck

In the example here you specified the value the ``toHtml`` method should
return by using one of **phpspec's** matchers. There are several other
matchers available, you can read more about these in the :doc:`Matchers Cookbook </cookbook/matchers>`

