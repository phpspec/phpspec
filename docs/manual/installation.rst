Installation
============

**phpspec** is a php 5.3+ library that you'll have in your project
development environment. Before you begin, ensure that you have at least
PHP 5.3.3 installed.

Method #1 (Composer)
--------------------

The simplest way to install phpspec with all its dependencies is through
Composer.

If you don't have Composer installed yet, follow the instructions on `the composer website <https://getcomposer.org/download/>`__

First, create a ``composer.json`` file in your project's root directory (``composer init``):

**Example composer.json with psr-0 autoloading**

.. code-block:: js

    {
        "autoload": {"psr-0": {"": "src"}}
    }

Then install phpspec with the ``composer require`` command:

.. code-block:: bash
    
    $ # This will ensure you get the latest stable version and
    $ # add the proper entry to your composer.json's require-dev section
    $ composer require phpspec/phpspec --dev

Phpspec with its dependencies will be installed inside the ``vendor`` folder
and the phpspec executable will be linked into the ``bin`` folder.

You may now use ``vendor/bin/phpspec``. 

*Optionally set up an alias* such as ``alias spec='vendor/bin/phpspec'`` to enable usage such as ``spec run``, ``spec describe``, etc...

**To update phpspec/phpspec**

.. code-block:: bash
    
    $ composer update phpspec/phpspec
