Getting started with **phpspec**
================================

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

Learn more from :doc:`the documentation <docs/introduction>`.
