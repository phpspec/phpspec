Installation
============

**phpspec** is a php 5.3+ library that you'll have in your project
development environment. Before you begin, ensure that you have at least
PHP 5.3.3 installed.

Method #1 (Composer)
--------------------

The simplest way to install phpspec with all its dependencies is through
Composer.

First, create a ``composer.json`` file in your project's root directory:

.. code-block:: js

    {
        "require-dev": {
            "phpspec/phpspec": "~2.0"
        },
        "config": {
            "bin-dir": "bin"
        },
        "autoload": {"psr-0": {"": "src"}}
    }

Then install phpspec with the composer install command:

.. code-block:: bash

    $ composer install

Follow instructions on `the composer website <https://getcomposer.org/download/>`_
if you don't have it installed yet.

phpspec with its dependencies will be installed inside the ``vendor`` folder
and the phpspec executable will be linked into the ``bin`` folder.

In case your project already uses composer, simply add ``phpspec/phpspec``
to the ``require-dev`` section and run:

.. code-block:: bash

    $ composer update phpspec/phpspec
