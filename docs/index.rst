Quick Start
===========

Create a ``composer.json`` file:

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

Install **phpspec** with composer:

.. code-block:: bash

    curl http://getcomposer.org/installer | php
    php composer.phar install

Start writing specs:

.. code-block:: bash

    bin/phpspec desc Acme/Calculator

Learn more from :doc:`the documentation <manual/introduction>`.

.. toctree::
   :hidden:
   :maxdepth: 1

   manual/introduction
   manual/installation
   manual/getting-started
   manual/prophet-objects
   manual/let-and-letgo

.. toctree::
   :hidden:
   :maxdepth: 1

   cookbook/configuration
   cookbook/console
   cookbook/construction
   cookbook/matchers
   cookbook/templates
   cookbook/extensions
   cookbook/wrapped-objects
