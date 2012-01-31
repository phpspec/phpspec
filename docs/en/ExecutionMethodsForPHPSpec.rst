Execution Methods for PHPSpec
=============================

PHPSpec offers two distinct modes for executing specs or examples. The
method most used throughout this manual requires using the command line
``phpspec`` operation from a console like bash or MS-DOS.
An alternative method is available for executing specs by opening a .php URL
in your preferred web browser. This alternative can output in either plain
text or HTML as determined by various options.

The Console Runner
------------------

Using PHPSpec from the console is the default option which requires
no additional work from your perspective. Simply write your specs,
navigate to their location on the command line, and issue a command
like:

.. code-block:: bash

    $ phpspec --recursive

This simple command recursively scans the current directory, and all
child directories, for spec files and executes all the examples each spec
file contains. The simplicity of this method makes using the console the
most obvious choice. If you're using a Unix console then you even get some
coloured output!

The ``phpspec`` console command has a number of
useful options which will be expanded substantially as development of
subsequent versions progresses. Each option usually has both a full length
version, and a shorter single character version. A table of the currently
available options is presented below.

PHPSpec Console Options
-----------------------

+--------------------+--------------------------+--------------------------------------------------------+
| Fulltext Variant   | Single Character Variant | Explanation                                            |
+====================+==========================+========================================================+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --backtrace        |           -b             | Enable full backtrace                                  |
+--------------------+--------------------------+--------------------------------------------------------+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --colour, --color  |           -c             | Enable color in the output                             |
+--------------------+--------------------------+--------------------------------------------------------+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --example STRING   |           -e             | Run examples whose full nested names include STRING    |
+--------------------+--------------------------+--------------------------------------------------------+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --formatter        |           -f             | Use one of the available formatters to format output   |
|                    |                          | as either Standard progress (default - dots), Specdox  |
|                    |                          | or HTML output.                                        |
+--------------------+--------------------------+--------------------------------------------------------+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --help             |           -h             | Print options                                          |
+--------------------+--------------------------+--------------------------------------------------------+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --fail-fast        |           None           | Abort the run on the first failure                     |
+--------------------+--------------------------+--------------------------------------------------------+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --recursive        |           -r             | Recursively search the current directory and all child |
|                    |                          | directories for specs, and execute all spec files and  |
|                    |                          | their examples as found.                               |
+--------------------+--------------------------+--------------------------------------------------------+
|                    |                          |                                                        |
+--------------------+--------------------------+--------------------------------------------------------+
| --version          |           None           | Show version                                           |
+--------------------+--------------------------+--------------------------------------------------------+

The HTTP Runner
---------------

The HTTP Runner is not an immediately available mode. Instead you
need to do a small amount of setup within a PHP file which you intend
calling from a web browser. This is actually a really simple task, and the
available options for utilising a HTTP runner are identical to the options
available for the Console Runner as outlined above.

Here's a simple example of a HTTP Runner stored to a file called
AllSpecs.php.

.. code-block:: html+php

    <?php

    require_once 'PHPSpec/Loader/UniversalClassLoader.php';
    $loader = new \PHPSpec\Loader\UniversalClassLoader();
    $loader->registerNamespace('PHPSpec', '/usr/share/pear');
    $loader->register();

    $options = array('specsDir', '--formatter', 'html');
    $specs = new \PHPSpec\PHPSpec($options);
    $specs->execute();

The ``\PHPSpec\Runner\Cli\Runner`` class is
actually used internally by ``\PHPSpec\PHPSpec`` so what we're doing here
is pretty simple. All we're really doing is duplicating the internal work
the Cli Runner performs within a PHP file we can visit from a web browser.
First of all, we include the base
``PHPSpec/Loader/UniversalClassLoader.php`` file. Since
PHPSpec takes advantage of Symfony universal autoloader, there's no need to
include any other PHPSpec files. Secondly, we setup the desired options in
an array as values. The options are typical for a complete execution of all
specs. PHPSpec will recursively search the given directory and all child
directories for specs to execute, it will output specdoc formatted
specifications in plain text along with the results, and it will use the
HTML reporter to output HTML. Finally we call
``PHPSpec\PHPSpec`` ``execute()``
method. And that's all there is to it! If you only want to execute a
sub-directory of your specs, you can pass that directory as first element
in the ``$options`` array (recursive searching does not
traverse parent directories).
