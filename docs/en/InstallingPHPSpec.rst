Installation
============

PHPSpec should be installed using the PEAR Installer. PEAR (PHP
Extension and Application Respository) is a simple, easy to use mechanism
for distributing and managing PEAR packages. If you have PHP installed,
chances are you already have a PEAR system ready to be utilised.

Installing PHPSpec with PEAR
----------------------------

PHPSpec is distributed primarily from its own dedicated PEAR
channel. The PEAR Channel system is quite simple to use and eases the
installation of PHPSpec in any scenario where you have access to your own
PEAR installation. Before commencing an installation, you first need to
"discover" the PHPSpec channel using the command:

.. code-block:: bash

    $ sudo pear channel-discover pear.phpspec.net

The newly discovered channel will have its details stored by
PEAR.

To install the package simply execute the following command:

.. code-block:: bash

    $ sudo pear install phpspec/PHPSpec

.. note::

    Development snapshots of PHPSpec are regularly updated in the
    develop branch at `http://github.com/phpspec/phpspec <http://github.com/phpspec/phpspec>`_.
    These snapshots are considered of dubious stability and should only be utilised for testing
    and feedback purposes. The installation of development snapshots is
    described in the "Installing PHPSpec Manually" section below.

Installing PHPSpec from a PEAR download
---------------------------------------

To install PHPSpec without using the PEAR channel system you can
select a download of the PEAR archive from `http://pear.phpspec.net/get <http://pear.phpspec.net/get>`_ and
by running the following command:

.. code-block:: bash

    $ sudo pear install PHPSpec-<version>.tar.gz

Installing PHPSpec Manually
---------------------------

To install PHPSpec manually, you can use the non pearified tarball called "PHPSpec-<version>.tar.gz" available for download from `http://pear.phpspec.net/get/nonpear <http://pear.phpspec.net/get/nonpear>`_. Extract to your preferred
location, and add the "src" directory to your php.ini include_path. You
will also need to copy the phpspec script for your system (*.bat refers to
a Windows friendly version) from the "scripts" directory to a location on
your system PATH. This script must be edited to provide the location of the
PHP binary executable as well as the path to the PHPSpec class files.
