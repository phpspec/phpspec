---
title: "Prophet Objects"
weight: 40
---

Stubs
-----

You also need your _Markdown_ parser to be able to format text fetched
from an external source such as a file. You decide to create an
interface so that you can have different implementations for different
types of source.

```php
<?php

namespace Markdown;

interface Reader
{
    public function getMarkdown();
}
```

You want to describe a method which has an instance of a _Reader_ as an
argument. It will call `Markdown\Reader::getMarkdown()` to get the
markdown to format. You have not yet written any implementations of
Reader to pass into the method though. You do not want to get distracted
by creating an implementation before you can carry on writing the
parser. Instead we can create a fake version of Reader called a stub and
tell **phpspec** what `Markdown\Reader::getMarkdown()` should return.

You can create a stub by telling **phpspec** that you want it to be a
double of the _Markdown\\Reader_ interface:

```php
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
```

At the moment calling `Markdown\Reader::getMarkdown()` will return null.
We can tell **phpspec** what we want it to return though.

```php
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
```

Now you can write the code that will get this example to pass. As well
as refactoring your implementation you should see if you can refactor
your specs once they are passing. In this case we can tidy it up a bit
as **phpspec** lets you create the stub in an easier way. If you use a
typehint, **phpspec** determine the required type of the collaborator:

```php
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
```

**phpspec** 2.\* supports the use of _@param_ annotations instead of
parametric typehints for this purpose. However, this functionality is
removed in **phpspec** 3.0.

Mocks
-----

You also need to be able to get your parser to output to somewhere
instead of just returning the formatted text. Again you create an
interface:

```php
<?php

namespace Markdown;

interface Writer
{
    public function writeText($text);
}
```

You again pass it to the method but this time the
`Markdown\Writer::writeText($text)` method does not return something to
your parser class. The new method you are going to create on the parser
will not return anything either. Instead it is going to give the
formatted text to the _Markdown\\Writer_ so you want to be able to give an
example of what that formatted text should be. You can do this using a
mock, the mock gets created in the same way as the stub. This time you
tell it to expect `Markdown\Writer::writeText($text)` to get called with
a particular value:

```php
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
```

Now if the method is not called with that value then the example will
fail.

The _shouldBeCalled_ method should be used **before** any SUS (System
Under Spec) calls in order to make an example fail if other mock methods
are invoked. In the previous example, if other methods than _writeText_
are called in _outputHTML_ (that is the SUS) function, the test will fail.
PHPSpec won't prevent you to use _shouldBeCalled_ **after** SUS calls:
this is not recommended as _shouldBeCalled_ would behave as
_shouldHaveBeenCalled_. To understand how _shouldHaveBeenCalled_ behaves,
please continue reading.

Spies
-----

Instead of using a mock you could use a spy. The difference is that you
check what happened after the object's behaviour has happened:

```php
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
```

The difference is in behaviour: when using spies, you will not be forced
to check every call that happens on double object
