Upgrading to PhpSpec 4
======================

Here is a guide to upgrading a test suite or an extension, based on BC-breaking changes made in phpspec 4.

Upgrading for Users
-------------------

If you are using 3rd party **phpspec** extensions, you may have to increase the version numbers for those as well.

As PHP 5 is no longer supported language versions, you will need to upgrade to PHP 7 to use **phpspec** 4.

Upgrading for Extension Authors
-------------------------------

Several internal interfaces have had scalar typehints and return typehinting added.  This includes:

- ``PhpSpec\CodeAnalysis\AccessInspector``
- ``PhpSpec\CodeAnalysis\NamespaceResolver``
- ``PhpSpec\CodeAnalysis\TypeHintRewriter.php``
