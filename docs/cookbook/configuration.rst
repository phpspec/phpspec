Configuration
=============

Some things in phpspec can be configured in a ``phpspec.yml`` or
``phpspec.yml.dist`` file in the root of your project (the directory where you
run the ``phpspec`` command).

Suites
------

In phpspec, you can group specification files by a certain namespace/path in a
*suite*. For each suite, you have a couple of configuration settings:

* ``namespace`` - The namespace of the classes. This is used for generating
  spec files, locating them and generating code;
* ``spec_prefix`` [**default**: ``spec``] - The namespace prefix for
  specifications. The complete namespace for specifications is
  ``%spec_prefix%\%namespace%``;
* ``src_path`` [**default**: ``src``] - The path to store the generated
  classes. Paths are relative to the location of the config file. Directories
  will be created if they do not exists. This does not include the namespace
  directories;
* ``spec_path`` [**default**: ``.``] - The path of the specifications. This
  does not include the spec prefix or namespace.

Some examples:

.. code-block:: yaml

    suites:
        acme_suite:
            namespace: Acme\TheLib
            spec_prefix: acme_spec

        # shortcut for
        # my_suite:
        #     namespace: The\Namespace
        my_suite: The\Namespace

To run a suite, you have to use the namespace of the specification classes or
the classes it tests. For instance, the above ``acme_suite`` will be used when
running ``phpspec run Acme\TheLib``, ``phpspec run spec\Acme\TheLib`` or
any classes in that namespace (e.g. ``phpspec run Acme\TheLib\Section\Foo``).

Formatter
---------

You can also set another default formatter instead of ``progress``. The
``--format`` option of the command can override this setting. To set the
formatter, use ``formatter.name``:

.. code-block:: yaml

    formatter.name: pretty

Available formatters are:

* progress (default)
* html/h
* pretty
* junit
* dot

Options
-------

You can turn off code generation in your config file by setting ``code_generation``:

.. code-block:: yaml

    code_generation: false

You can also set your tests to stop on failure by setting ``stop_on_failure``:

.. code-block:: yaml

    stop_on_failure: true

Extensions
----------

To register phpspec extensions, use the ``extensions`` option. This is an
array of extension classes:

.. code-block:: yaml

    extensions:
        - PhpSpec\Symfony2Extension\Extension
