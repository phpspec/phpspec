---
title: "Upgrading to phpspec 3"
weight: 90
---

Here is a guide to upgrading a test suite or an extension, based on
BC-breaking changes made in phpspec 3.

Upgrading for Users
-------------------

If you are using 3rd party **phpspec** extensions, you may have to
increase the version numbers for those as well.

As PHP 5.5 and below are no longer supported language versions, you will
need to upgrade to PHP 5.6 or 7.0+ to use **phpspec** 3.

Where you have used _@param_ annotations for spec examples, to indicate
the required type for a collaborator, you will need to remove these and
use explicit typehinting in the method signature instead. For example:

```php
/**
 * @param \stdClass $collaborator
 */
function it_does_something_with_a_stdclass($collaborator)
```

Change to:

```php
function it_does_something_with_a_stdclass(\stdClass $collaborator)
```

Extension configured in your `phpspec.yml` needs to be changed from:

```yaml
some_extension_config: foo

extensions:
    - SomeExtension
    - SomeOtherExtension
```

To:

```yaml
extensions:
    SomeExtension:
        some_config: foo

    SomeOtherExtension: ~
```

Upgrading for Extension Authors
-------------------------------

Several interfaces have been renamed in **phpspec** 3.0. Here is a quick
guide to changes you will need to make in your code.

-   `PhpSpec\Console\IO` is now `PhpSpec\Console\ConsoleIO`
-   `PhpSpec\IO\IOInterface` is now `PhpSpec\IO\IO`
-   `PhpSpec\Locator\ResourceInterface` is now
    `PhpSpec\Locator\Resource`
-   `PhpSpec\Locator\ResourceLocatorInterface` is now
    `PhpSpec\Locator\ResourceLocator`
-   `PhpSpec\Formatter\Presenter\PresenterInterface` is now
    `PhpSpec\Formatter\Presenter\Presenter`
-   `PhpSpec\CodeGenerator\Generator\GeneratorInterface` is now
    `PhpSpec\CodeGenerator\Generator\Generator`
-   `PhpSpec\Extension\ExtensionInterface` is now `PhpSpec\Extension`
-   `Phpspec\CodeAnalysis\AccessInspectorInterface` is now
    `Phpspec\CodeAnalysis\AccessInspector`
-   `Phpspec\Event\EventInterface` is now `Phpspec\Event\PhpSpecEvent`
-   `PhpSpec\Formatter\Presenter\Differ\EngineInterface` is now
    `PhpSpec\Formatter\Presenter\Differ\DifferEngine`
-   `PhpSpec\Matcher\MatcherInterface` is now `PhpSpec\Matcher\Matcher`
-   `PhpSpec\Matcher\MatchersProviderInterface` is now
    `PhpSpec\Matcher\MatchersProvider`
-   `PhpSpec\SpecificationInterface` is now `PhpSpec\Specification`
-   `PhpSpec\Runner\Maintainer\MaintainerInterface` is now
    `PhpSpec\Runner\Maintainer\Maintainer`

Some methods have a different signature:

-   `PhpSpec\CodeGenerator\Generator\PromptingGenerator#__construct`'s
    third and fourth arguments are now mandatory
-   `PhpSpec\Matcher\ThrowMatcher#__construct`'s third argument is now
    mandatory
-   `PhpSpec\Extension#load` has now an additional mandatory
    `array $params` argument.

A few methods have been renamed in **phpspec** 3.0:

-   `PhpSpec\ServiceContainer#set` is now
    `PhpSpec\ServiceContainer#define`
-   `PhpSpec\ServiceContainer#setShared` is now
    `PhpSpec\ServiceContainer#define`

Other things to bear in mind:

-   `PhpSpec\ServiceContainer` is now an interface (available
    implementation: `PhpSpec\ServiceContainer\IndexedServiceContainer`)
-   `PhpSpec\ServiceContainer\ServiceContainer#getByPrefix` has been
    replaced by `PhpSpec\ServiceContainer\ServiceContainer#getByTag`.
    Tags can be set via
    `PhpSpec\ServiceContainer\ServiceContainer#define`'s third argument

