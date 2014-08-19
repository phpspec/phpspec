Introduction
============

Spec BDD with phpspec
---------------------

**phpspec** is a tool which can help you write clean and working PHP code using
behaviour driven development or BDD. BDD is a technique derived from test-first
development.

BDD is a technique used at story level and spec level. **phpspec** is a tool for
use at the spec level or SpecBDD.  The technique is to first use a tool like **phpspec**
to describe the behaviour of an object you are about to write. Next you write just
enough code to meet that specification and finally you refactor this code.

SpecBDD and TDD
---------------

There is no real difference between SpecBDD and TDD. The value of using
an xSpec tool instead of a regular xUnit tool for TDD is **the language**. The early
adopters of TDD focused on behaviour and design of code. Over time the focus has
shifted towards verification and structure. BDD aims to shift the focus back by
removing the language of testing. The concepts and features of the tool will keep your
focus on the "right" things.

SpecBDD and StoryBDD
--------------------

StoryBDD tools like `Behat <http://behat.org>`_ help to understand and clarify the
domain. They help specify feature narratives, their needs, and what we mean by them.
With SpecBDD we are only focused on the how, in other words, the implementation.
You are specifying how your classes will achieve those features.

Only using story level BDD will not do enough to help you write the code for
the features well. Each feature is likely to need quite a lot of code. If
you only confirm that the whole feature works and also only refactor at that
point then you are working in large steps. SpecBDD tools guide you in the process
by letting you write the code in small steps. You only need to write the spec
and then the code for the next small part you want to work on and not the whole
feature.

StoryBDD and SpecBDD used together are an effective way to achieve customer-focused software.
