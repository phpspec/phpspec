Introduction
============

Testing Your Code
-----------------

How do you currently test code?

The most obvious means of testing PHP code is to save it to a file and browse to it in your
web browser. If the output of the script seems correct then your test has
been successful. A variation on this theme when testing functions or
classes which don't have direct output is to insert `var_dump()` and echo
statements to reveal the content of variables. Another variation is
interacting with a completed application to check things work as
expected.

These methods of testing are extremely easy but they bely a serious
problem. You cannot easily repeat these same tests without a lot of
effort. It's in avoiding this effort, and making tests or examples
repeatable that makes PHPSpec a valuable tool. I'm not going to exclude
SimpleTest, PHPUnit, or PHPT from that either - familiarity with more than one framework
in PHP is always advisable.

Using a framework like PHPSpec allows you to write examples which
are repeatable. These automatic tests can be executed time and time again
using a quick command line call or browser refresh. Repeatable testing
however is not where it all ends.

Source Code Examples As Documentation
-------------------------------------

Repeatable tests are all fine and well, but it turns out that they
also serve another purpose. Just as a picture is worth a thousand words,
in programming a good example of how to use your code is also worth a
thousand words. Your repeatable examples, which make great future tests,
also act as a guide for users and other programmers in how to correctly
use your source code.

It's here where I can introduce the idea that the traditional "test"
of Unit Testing and Test-Driven Development can simply be referred to as an example. Each example you
write explains one more facet of behaviour
your source code is capable of, how to make that behaviour
occur, and what it's effects are. In Behaviour-Driven
Development, each such example can also be thought of as a
specification (or "spec" for short).

In BDD, which promotes the use of a language more readily readable
and accessible for coding examples, the value of your specs as
documentation is improved using a fluent Domain Specific
Language. Consider the differences which make the second example
below more clear and legible than the first example.

Example with PHPUnit
--------------------

PHPUnit example:

.. code-block:: php

    $logger = new Logger;
    $this->assertTrue($logger->hasFile());

Example with PHPSpec
--------------------

PHPSpec example:

.. code-block:: php

    $logger = new Logger;
    $this->spec($logger)->should->haveFile();

Because code examples make for great documentation (not just tests)
it's better to have examples which follow the natural flow of plain
English to improve their readability and clarity. Not only that but done
properly any set of examples should be translatable back into their plain
text form so you have a sentence (based on our earlier examples)
of:

.. note::

      Logger
      - should have a file

Before we continue, remember that BDD is not just TDD with "should"
instead of "assert". There are other reasons for BDD's raison d'etre
besides a simple language differential from TDD.

Writing Examples (Specs) Before Writing Code
--------------------------------------------

As we move up the chain of practices, we eventually reach a point
where we have an epiphany. If our examples/tests make such great
documentation, describe the behaviour of our classes and applications,
and are repeatable over and over, then what if instead of writing pages of
planning documentation we just write some examples first, and then write
the code to agree to them?

This approach is where Test-Driven Development
enters the fray, with the infamous warcry of "Test first!".
Since PHPSpec is a Behaviour-Driven Development framework we'll replace
this with a "Spec first!" cry.

Why Write Examples First?
-------------------------

The reasoning behind writing examples first is really
simple.

The first effect is to force us to face the problem at hand and
make us think about what we're doing. It's all too easy to just start
coding without a firm idea and get lost before you've even written your
first spec or test. I'm sure we've all been there more than once!
Writing an example on the other hand means we have to immediately start
looking at how the public API will work, how the logic flows between
components, the range of output we really need, the logic we may require
for an implementation.

The second effect is that doing this in small steps lets us write
better code. The code resulting from the structured repetition of small
steps - specify, write example, write implementing code, refactor, and
rewind - leads to code which has a superior design, is more easily
maintained, is usually simpler and clearer, and will generally have
fewer bugs as a result.

There are lots of other advantages, but perhaps the best one is
confidence. Improved confidence in your design, your code quality,
your own skills as a programmer, your complete fearlessness when faced
with change, and let's not forget the confidence other programmers and
users will have in well designed and tested source code.

The Workflow of BDD using PHPSpec
---------------------------------

The process of applying BDD to your coding methodology.
Remember to execute your examples once written to ensure
they first fail, and during implementation until you're successfully
passing them.

* Identify a valuable behaviour you want to implement
* Describe it in plain text in the form "it should ... ", i.e. a
  specification
* Write one or more specs/examples for the "it should"
  components of your specification
* Implement the behaviour you've just described in the
  specification
* Refactor the implemented code if needed
* Return to Step 1

We'll cover this in greater detail when explaining exactly what
Behaviour-Driven Development is later. The steps are very similar to the
TDD variant of write test, write code, refactor except there's also a
focus on the documentation/specification aspect and we're not discussing
Testing (that specs make good tests is coincidental to the true purpose
of BDD which is to improve design).

PHPSpec And BDD In Context
--------------------------

PHPSpec is the first Behaviour-Driven Development
framework for PHP, so in PHP terms we're the new kid on the
block - and full of wacky ideas! Outside of PHP, BDD has become
progressively more and more popular with a proliferation of frameworks
from Ruby's RSpec to Java's JBehave. Frameworks exist for Java, Ruby,
.NET, Smalltalk, Javascript and more. Often there exists more than one BDD
framework in a language. Java has three or more.

On the flip side, traditional Unit Testing frameworks have started
to take notice of the swell of support for BDD and started to provide BDD
friendly APIs. These have not been overwhelmingly successful however. As I
noted before - BDD is not just TDD with "should" instead of "assert".
However these milder approaches are still valuable for intermixing BDD
specifications with existing TDD Unit Tests without switching frameworks
completely.

This will get more confusing as BDD frameworks start to do the exact
same - offering API Runner support for Unit Tests so migration is even
simpler. Something RSpec in Ruby is already beginning to improve in recent
versions.
