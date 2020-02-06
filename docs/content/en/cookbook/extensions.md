Extensions
==========

Extensions can add functionality to **phpspec**, such as, integration
with a particular framework. See below for some example extensions.

Installation
------------

Individual extensions will have their own documentation that you can
follow. Usually you can install an extension by adding it to your
`composer.json` file and updating your vendors.

Configuration
-------------

You will need to tell **phpspec** that you want to use the extension.
You can do this by adding it to the config file:

```yaml
extensions:
    MageTest\PhpSpec\MagentoExtension\Extension: ~
```

You can pass options to the extension as well:

```yaml
extensions:
    MageTest\PhpSpec\MagentoExtension\Extension:
        mage_locator:
            src_path: public/app/code
            spec_path: spec/public/app/code
```

See the Configuration Cookbook &lt;/cookbook/configuration&gt; for more
about config files.

Example extensions
------------------

### Framework Integration

> -   [Symfony2](https://github.com/phpspec/Symfony2Extension)
> -   [Magento](https://github.com/MageTest/MageSpec)
> -   [Laravel](https://github.com/BenConstable/phpspec-laravel)

### Code generation

> -   [Typehinted
>     Methods](https://github.com/ciaranmcnulty/phpspec-typehintedmethods)
> -   [Example
>     Generation](https://github.com/richardmiller/ExemplifyExtension)
> -   [SpecGen](https://github.com/memio/spec-gen)

### Additional Formatters

> -   [Nyan Formatters](https://github.com/phpspec/nyan-formatters)

### Metrics

> -   [Code
>     coverage](https://github.com/friends-of-phpspec/phpspec-code-coverage)

### Matchers

> -   [Coduo matcher
>     extension](https://github.com/coduo/phpspec-matcher-extension)
> -   [Array Contains matcher
>     extension](https://github.com/jameshalsall/phpspec-array-contains-matchers)
> -   [Collection of custom
>     matchers](https://github.com/karriereat/phpspec-matchers)

### Miscellaneous

> -   [Prepare](https://github.com/coduo/phpspec-prepare-extension)
> -   [Data
>     provider](https://github.com/coduo/phpspec-data-provider-extension)
> -   [Behat Integration](https://github.com/richardmiller/BehatSpec)
> -   [Example skipping through
>     annotation](https://github.com/akeneo/PhpSpecSkipExampleExtension)
> -   [Annotation](https://github.com/drupol/phpspec-annotation)

