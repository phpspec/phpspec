Quick Start
===========

Create a ``composer.json`` file:

.. code-block:: js

    {
        "require-dev": {
            "phpspec/phpspec": "^4.0"
        },
        "config": {
            "bin-dir": "bin"
        },
        "autoload": {"psr-4": {"App\\": "src"}}
    }

Follow the instructions on this page to install composer: `<https://getcomposer.org/download/>`_.  

Install **phpspec** with composer:

.. code-block:: bash

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
   manual/upgrading-to-phpspec-4
   manual/upgrading-to-phpspec-3

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
