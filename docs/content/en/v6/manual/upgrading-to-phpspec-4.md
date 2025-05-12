---
title: "Upgrading to phpspec 4"
weight: 80
---

Here is a guide to upgrading a test suite or an extension, based on
BC-breaking changes made in phpspec 4.

Upgrading for Users
-------------------

If you are using 3rd party **phpspec** extensions, you may have to
increase the version numbers for those as well.

As PHP 5 is no longer supported language versions, you will need to
upgrade to PHP 7 to use **phpspec** 4.

Most methods in PhpSpec now have static type hints and return types,
this will affect you when you are overriding behaviour from a parent
class or implementing an interface.

If you are providing inline matchers in your specs you will need to
provide the array type hint:

```php
function getMatchers()
{
    // return some matchers
}
```

Change to:

```php
function getMatchers() : array
{
    // return some matchers
}
```

If you are providing custom matchers, you will need to conform to the
type hint changes in the Matcher interface.

Upgrading for Extension Authors
-------------------------------

Many PhpSpec interfaces and internal classes have had scalar typehints
and return typehinting added. You will need to update your
implementations of these interfaces to the new method signature.
