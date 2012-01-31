Writing Specs with PHPSpec
==========================

Specs, Examples and Contexts
----------------------------

The terminology used throughout Behaviour-Driven Development is
focused entirely on the concept of describing behaviour. This alleviates
any misunderstanding from attempting to describe the process of
Test-Driven Development in terms of tests - a counterintuitive notion for
many programmers.

The terms Spec are almost used interchangeably. While a Spec refers to a
single behavioural requirement, often captured as a simple sentence of the
form "it should do something", an Example refers to the the entire method
within PHPSpec which demonstrates this Spec in code. If you take the
example below, the spec is the line of code commencing with
``$this->spec()`` and the example is the entire
public method which shows how this spec is achievable

A Spec in a PHPSpec Example Method
----------------------------------

Example method in PHPSpec:

.. code-block:: php

    public function itShouldHaveScoreOfZero()
    {
        $bowling = new Bowling;
        $bowling->hit(0);
        $this->spec($bowling->score)->should->be(0);
    }

A more difficult concept is that of a Context.
In brief a Context is the set of conditions prevailing
at the time we are specifying behaviour. Above, our Bowling example
assumes we've just started a new game. This is the Context all our Specs
in the same class would share. Later we might want a game which is
finished, or partially played. Each different Context helps you explore
how behaviour changes under different conditions.

Before Writing Code, Specify Its Required Behaviour
---------------------------------------------------

In the course of developing a new application we've determined we
need a Logging system, perhaps to store an audit trail. We're going to
assume no current open source Logger library is sufficient for our needs
and we are required to develop one from scratch. Before we can do anything
we need to start figuring out what it needs to do. In other words, how we
want it to behave. After consulting with our colleagues we determine at
least one fundamental requirement - to log messages to a
filesystem.

Rather than immediately jumping into an editor to start coding,
we're going to write the specifications first.

Some Plain Text Specs for a Filesystem Logger
---------------------------------------------

New Filesystem Logger:
- should create a new log file if none currently exists
- should use an existing log file if one exists without truncating it
- should throw Exception if existing log file not writable

These simple plain text specifications can be translated to PHPSpec
by creating a new Context class contain the examples demonstrating these
behaviours.

.. code-block:: php

    class DescribeNewFilesystemLogger extends \PHPSpec\Context
    {
    
        public function itShouldCreateCreateNewLogFileIfNoneExists()
        {
            $this->pending();
        }

        public function itShouldUseAnExistingLogFileIfOneExistsWithoutTruncatingIt()
        {
            $this->pending();
        }

        public function itShouldThrowExceptionIfExistingLogFileNotWriteable()
        {
            $this->pending();
        }
    
    }

This skeleton class has two Pending examples. The pending status simply means they are
incomplete or pending completion. If you were to execute this spec from
the command line when saved as NewFilesystemLoggerSpec.php (using the
alternate filename convention which utilises a "Spec" suffix and omits the
"Describe" prefix), the output would look something like:

.. code-block:: bash

    ***

    Pending:
      NewFilesystemLogger should create create new log file if none exists
         # No reason given
         # ./NewFilesystemLoggerSpec.php:8

      NewFilesystemLogger should use an existing log file if one exists without truncating it
         # No reason given
         # ./NewFilesystemLoggerSpec.php:13

      NewFilesystemLogger should throw exception if existing log file not writeable
         # No reason given
         # ./NewFilesystemLoggerSpec.php:18

    Finished in 0.058379 seconds
    3 examples, 3 pendings

The relevant command line target to run PHPSpec would be something
like:

.. code-block:: bash

    $ phpspec NewFileSystemLoggerSpec

We now have two example methods. Based on the defined
specifications, let's fill these in with something useful.

    
Specification for a New Filesystem Logger Context
-------------------------------------------------

The Spec would look like:

.. code-block:: php

    class DescribeNewFilesystemLogger extends \PHPSpec\Context
    {

        public function itShouldCreateCreateNewLogFileIfNoneExists()
        {
            $file = $this->getTmpFileName();
            $logger = new Logger($file);
            $this->spec(file_exists($file))->should->beTrue();
        }

        public function itShouldUseAnExistingLogFileIfOneExistsWithoutTruncatingIt()
        {
            $file = $this->getTmpFileName();
            file_put_contents($file, 'Hello' . "\n");
            $logger = new Logger($file);
            $this->spec(file_get_contents($file))->shouldNot->beEmpty();
        }

        public function itShouldThrowExceptionIfExistingLogFileNotWriteable()
        {
            $file = $this->getTmpFileName();
            file_put_contents($file, 'Hello' . "\n");
            $this->spec('Logger', $file)->should->throw('Exception');
        }

        public function after()
        {
            unlink($this->getTmpFileName());
        }

        public function getTmpFileName()
        {
            return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'logger_tmp_file.log';
        }

    }
    
And so we've now turned our plain text specs into coded examples for
execution. Of course executing this now will result in an ugly Fatal Error
since the Logger class does not yet exist. We'll cross this bridge later
on.

Explaining the PHPSpec Spec Layout
----------------------------------

Our completed New Filesystem Logger example demonstrates how a Spec is put together.

* All Specs are aggregated within a \PHPSpec\Context subclass based on the condition of the system being specified

* All Context classnames must begin with the term "Describe" to encourage full sentence descriptions

* All Example methods in a Context must begin with "itShould", again to encourage full sentence specification text (this might be later shortened to optionally omit "Should" to allow present tense specification language)

* A ``\PHPSpec\Context::spec()`` method is utilised to prepare any object or scalar value for expectations via the DSL.

* The domain specific language (DSL) generally includes an Expectation (should/shouldNot) and a Matcher (beSomething, haveSomething, equals, etc.)

* It is almost a rule that you only have one Spec per Example - this ensures each Spec is a single isolated piece of behaviour

* You can add any other methods to the class to provide Helper Methods, e.g. ``getTmpFileName()``

* You can use ``after()`` and ``before()`` methods to setup common Fixtures for each Example

* You can also use ``afterAll()`` and ``beforeAll()`` methods with are run only once before and after all Examples are executed

* Note that any Exceptions or Errors triggered with an Example will be reported but will not interrupt any other tests

The Code To Implement The New Filesystem Logger Specification
-------------------------------------------------------------

With our specification now written up with PHPSpec, we can move on
and implement the Logger to its specifications. I'm sure many people
will note some paths for refactoring but for now we're only interested
in writing the minimum amount of code necessary to pass all our
Specs.

Implementation of the Filesystem Logger
---------------------------------------

And this will be the implementation:

.. code-block:: php

    class Logger
    {

        protected $_file = null;

        public function __construct($file)
        {
            if (!file_exists($file)) {
                $f = fopen($file, 'w');
                fclose($f);
            } elseif (file_exists($file) && is_writeable($file)) {
                $this->_file = $file;
            } else {
                throw new Exception('log file is not writeable');
            }
        }

    }
      
The next step is deciding what the next behaviour should be so we
can write a Spec for it. Maybe you want to add a Logger_Exception class
to extend Exception? Maybe the file needs a few more checks? Maybe you
want to consider moving file handling to a new subclass or strategy
class for composition?

Whatever you would decide - write a spec for it before adding more
code. Take small steps and build up your classes iteratively. Remember
also not to over-specify. Just because you extract file handling to a
new class does not mean you should immediately specify the new class
(unless it's valuable enough to warrant it) since the original Specs
still cover the effects of a Logger being instantiated with a file. This
is not adding new behaviour - it's just changing the implementation of
that behaviour transparently.

The Spec Domain Specific Language (DSL)
---------------------------------------

PHPSpec writes coded Examples of behaviour using a Domain
Specific Language (DSL) for describing expectations. The DSL was designed
to approximate plain grammatically accurate English so it is intuitive to
use, read and comprehend.

The basic form of the DSL is to attach an Expectation (should or
should not) and a Matcher (be, beAnInstanceOf, equal, etc.) to the value
or object passed to a new Spec. This approach leads to a relatively easy
to read sentence requiring minimal translation into the plain English (or
other language!) we normally think in. Since the translation effort is
minimised, and is closer to how we really think, it's invariably easy to
review, critique and modify.

Example Spec DSL: Bowling should not be an instance of Logger
-------------------------------------------------------------

Example follows:

.. code-block:: php

    $bowling = $this->spec(new Bowling);
    $bowling->shouldNot->beAnInstanceOf('Logger');

The Actual Value Term
---------------------

In a PHPSpec Example method block, the DSL is instantiated using a
call to the ``\PHPSpec\Context::spec()``. This accepts
three possible parameter groupings.

* A scalar value, i.e. string, integer, boolean, float, or array
* An object
* An object name, together with any constructor parameters

Actual Term: Scalar Examples
----------------------------

Example follows:

.. code-block:: php

    $this->spec('i am a string')->should-beString();
    $this->spec(567)->should->equal(567);
    $this->spec(array(1, 2, 3))->shouldNot->beEmpty();
      

Actual Term: Object Examples
----------------------------

Example follows:

.. code-block:: php

    $this->spec(new Bowling)->should->beAnInstanceOf('Bowling');

    $bowling = new Bowling;
    $this->spec($bowling)->shouldNot->havePlayers();
      
Actual Term: Object Name With Constructor Params
------------------------------------------------

Example follows:

.. code-block:: php

    $this->spec('Bowling', new Player('Joe'), new Player('Jim'))->should->havePlayers();
      
The Expectation Term (Should or Should Not)
-------------------------------------------

Just as with English, all expectations fall into one of two
possible classes. Those you expect to fail, and those you expect to
pass. Whether you wish a Matched Actual Value or an Unmatched Actual
Value to be interpreted as a pass depends on the use of the DSL
``should`` or ``shouldNot``
phrases.

All the examples below are expected to pass.

Expectation Term: Various Passing Examples
------------------------------------------

The code follows:

.. code-block:: php

    $spec->( array() )->should->beEmpty();
    $spec->('Bowling')->shouldNot->havePlayers();
    $spec->('i am a string')->should->match("/^[a-z ]$/");
    $spec->(is_int('string'))->shouldNot->beTrue();
      
The Matcher Term
----------------

Whereas Unit Testing frameworks rely on assertions, PHPSpec splits
the responsibility between an Expectation Term and a Matcher. A Matcher
is a simple object which compares an Actual Value Term with the expected
value passed to the Matcher method in the DSL for a positive or negative
match. The form of a Matcher is ruled by the
``\PHPSpec\Matcher`` interface so you can
write custom Matchers (pending feature).

An already expansive range of Matchers are provided by the PHPSpec
framework. [Note: Some are still awaiting development.]

A Matcher is generally appended as the last term to a Spec as
demonstrated in earlier examples.

Matchers Included In PHPSpec
----------------------------

Note that all Matchers will return a boolean when called thus
ending the fluent interface of the Spec. Parameters marked
``NULL`` generally mean a parameter is not required
(the expected value is implicit in the Matcher name).

PHPSpec Matchers
----------------

+---------------------------------------+--------------------------------------------------------------------+
| Matcher Method                        | Explanation                                                        |
+=======================================+====================================================================+
| bool be (mixed $expected)             | Identical to using ``equal()`` and reflects general English usage. |
+---------------------------------------+--------------------------------------------------------------------+
| bool beEqualTo (mixed $expected)      | Identical to using ``equal()`` and reflects general English usage. |
+---------------------------------------+--------------------------------------------------------------------+
| bool equal (mixed $expected)          | Attempts to match the expected value on an equal basis             |
|                                       | intelligently comparing scalar values, object class, array         |
|                                       | content, or other metrics generally associated with two items      |
|                                       | being equivalent.                                                  |
+---------------------------------------+--------------------------------------------------------------------+
| bool beTrue (null $expected)          | Matches the actual value against TRUE.                             |
+---------------------------------------+--------------------------------------------------------------------+
| bool beFalse (null $expected)         | Matches the actual value against ``FALSE``.                        |
+---------------------------------------+--------------------------------------------------------------------+
| bool beNull (null $expected)          | Checks if the actual value is ``NULL``.                            |
+---------------------------------------+--------------------------------------------------------------------+
| bool beEmpty (mixed $expected)        | Checks if the actual value is empty (using ``empty()``).           |
+---------------------------------------+--------------------------------------------------------------------+
| bool beSet (null $expected)           | Checks if the actual value is set (using ``isset()``).             |
+---------------------------------------+--------------------------------------------------------------------+
| bool beAnInstanceOf (string $expected)| Determines if the actual value is both an object and an            |
|                                       | instance of the class type provided.                               |
+---------------------------------------+--------------------------------------------------------------------+
| bool beInt (null $expected)           | Checks if the actual value is an integer. This is a                |
|                                       | precise check - the string form of an integer will not             |
|                                       | match.                                                             |
+---------------------------------------+--------------------------------------------------------------------+
| bool beArray (null $expected)         | Checks if the actual value is an array.                            |
+---------------------------------------+--------------------------------------------------------------------+
| bool beString (null $expected)        | Checks if the actual value is a string.                            |
+---------------------------------------+--------------------------------------------------------------------+
| bool beFloat (null $expected)         | Checks if the actual value is a float.                             |
+---------------------------------------+--------------------------------------------------------------------+
| bool beObject (null $expected)        | Checks if the actual value is an object; does not                  |
|                                       | perform type comparison on class type.                             |
+---------------------------------------+--------------------------------------------------------------------+
| bool beGreaterThan (mixed $expected)  | Checks if the actual value is greater than (``>``)                 |
|                                       | the expected value provided.                                       |
+---------------------------------------+--------------------------------------------------------------------+
| bool beLessThan (mixed $expected)     | Checks if the actual value is less than                            |
|                                       | (``<``) the expected value provided                                |
+---------------------------------------+--------------------------------------------------------------------+
| bool beGreaterThanOrEqualTo           | Checks if the actual value is greater than or equal to             |
| (mixed $expected)                     | (``>=``) the expected value provided                               |
+---------------------------------------+--------------------------------------------------------------------+
| bool beLessThanOrEqualTo (mixed       | Checks if the actual value is less than or equal to                |
| $expected)                            | (``<=``) the expected value provided                               |
+---------------------------------------+--------------------------------------------------------------------+

Predicate Matchers
------------------

A Predicate Matcher is a Matcher which captures it's actual value from an
object being specified. It does so by seeking and then calling a
method of the form ``isSomething()`` or
``hasSomething()``. We saw this already in previous
DSL examples where the DSL method ``havePlayers()``
is translated into a call to
``Bowling::hasPlayers()``. A boolean result from
the called method is then compared to a boolean
``TRUE`` to check for a positive or negative
match.

        
Example of Classes and Predicate Matcher Calls
----------------------------------------------

Example follows:

.. code-block:: php

    class Insect {

        public function isInsect() {
            return true;
        }

        public function hasWings() {
            return true;
        }

    }

    class Flea extends Insect {

        public function hasWings() {
            return false; // Fleas are wingless blood sucking things
        }

    }

    class DescribeFlea extends \PHPSpec\Context {

        public function itShouldBeAnInsect()
        {
            $flea = new Flea;
            $this->spec($flea)->should->beAnInsect(); // Flea::isInsect() == TRUE
        }

        public function itShouldHaveNoWings()
        {
            $flea = new Flea;
            $this->spec($flea)->shouldNot->haveWings(); // Flea::hasWings() == FALSE
        }
    }
        

Predicate Matcher methods in the DSL allow for the use of
``be()``, ``beA()``,
``beAn()`` variations which are primarily for
allowing grammatically correct structures and are otherwise identical.
Same applies to ``have(), haveA(), and haveAn()``.
The same variations are also searched for when matching to an object's
methods (even object methods can be grammatically correct!). This form
of matching will eventually be expanded to allow for other predicate
style calling methods. If you have any suggestions be sure to let us
know.