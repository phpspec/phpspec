2.0.1 / 2014-07-01
==================

  * Fixed the loading of the autoloader for projects using a custom composer vendor folder

2.0.0 / 2014-03-19
==================

  * Improve support to windows
  * Improve support to hhvm
  * Improve acceptance tests coverage with Behat

2.0.0-RC4 / 2014-02-21
======================

  * Revamped junit formatter
  * Fixed #269 Problem with exception masking and generation for not found class
  * HHVM is officially supported
  * Add psr0 validator
  * Remove Nyan from core
  * Added an exception if the specified config file does not exist
  * Fixed a problem with generating a constructor when it is first time added
  * Improved help
  * Fixed the suite runner in fast machines

2.0.0-RC3 / 2014-01-01
======================

  * Fixed the Prophecy constraint as the new release is 1.1
  * Refactored formatters to be defined as services

2.0.0-RC2 / 2013-12-30
======================

  * Fixed the invocation of methods expecting an argument passed by reference
  * Fixed the instantiation of the wrapped object in shouldThrow

2.0.0-RC1 / 2013-12-26
======================

  * Bump the Prophecy requirement to ``~1.0.5@dev``
  * Added a JUnit formatter
  * Added the ``--stop-on-failure`` option
  * Fixed the support of the ``--no-interaction`` option
  * Added more events to add extension points
  * Added the number of specs in the console output
  * Fixed the handling of Windows line endings in the StringEngine and in reading doc comments
  * Added extension points in the template loading
  * Added a constructor generator
  * Added a HTML formatter
  * Added a nyan cat formatter

2.0.0beta4 / 2013-05-19
=======================

  * Add collaborator constructor setter
  * Fix couple of bugs in Prophecy integration layer
  * New (old) dot formatter

2.0.0beta3 / 2013-05-01
=======================

  * Prevent loading of unexisting PHP files
  * Fix typos in the error messages

2.0.0beta2 / 2013-04-30
=======================

  * Bump required Prophecy version to 1.0.1
  * Support non-string values with ArrayContain matcher
  * Create `src` folder if does not exist
  * Fix stack trace and matchers failure printing

2.0.0beta1 / 2013-04-29
=======================

  * Initial release
