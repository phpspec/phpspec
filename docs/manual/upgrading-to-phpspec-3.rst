Upgrading to phpspec 3
======================

phpspec 3.0 is still in development, but here is a guide to upgrading a test
suite or an extension, based on BC-breaking changes made so far.

Upgrading for Users
-------------------

If you are using 3rd party **phpspec** extensions, you may have to increase the
version numbers for those as well.

As PHP 5.5 and below are no longer supported language versions, you will need
to upgrade to PHP 5.6 or 7.0+ to use **phpspec** 3.

Where you have used `@param` annotations for spec examples, to indicate the
required type for a collaborator, you will need to remove these and use
explicit typehinting in the method signature instead. For example:

.. code-block:: php

    /**
     * @param \stdClass $collaborator
     */
    function it_does_something_with_a_stdclass($collaborator)

Change to:

.. code-block:: php

    function it_does_something_with_a_stdclass(\stdClass $collaborator)

Upgrading for Extension Authors
-------------------------------

Several interfaces have been renamed in **phpspec** 3.0.  Here is a quick guide to
changes you will need to make in your code.

- `PhpSpec\Console\IO` is now `PhpSpec\Console\ConsoleIO`
- `PhpSpec\IO\IOInterface` is now `PhpSpec\IO\IO`
- `PhpSpec\Locator\ResourceInterface` is now `PhpSpec\Locator\Resource`
- `PhpSpec\Locator\ResourceLocatorInterface` is now
  `PhpSpec\Locator\ResourceLocator`
- `PhpSpec\Formatter\Presenter\PresenterInterface` is now
  `PhpSpec\Formatter\Presenter\Presenter`
- `PhpSpec\CodeGenerator\Generator\GeneratorInterface` is now
  `PhpSpec\CodeGenerator\Generator\Generator`
- `PhpSpec\Extension\ExtensionInterface` is now `PhpSpec\Extension`
- `Phpspec\CodeAnalysis\AccessInspectorInterface` is now `Phpspec\CodeAnalysis\AccessInspector`
- `Phpspec\Event\EventInterface` is now `Phpspec\Event\PhpSpecEvent`
- `PhpSpec\Formatter\Presenter\Differ\EngineInterface` is now `PhpSpec\Formatter\Presenter\Differ\DifferEngine`
- `PhpSpec\Matcher\MatcherInterface` is now `PhpSpec\Matcher\Matcher`

Other things to bear in mind:

- `PhpSpec\CodeGenerator\Generator\PromptingGenerator` now has a different
  method signature for its constructor.
- `PhpSpec\Matcher\ThrowMatcher` now has a different method signature for its
  constructor.
