2.5.3 / 2016-09-26
==================

* [fixed] Accidental linebreaks in spec name are not allowed (@randompixel)
* [fixed] Throwable can be passed as instance to shouldThrow (@jameshalsall)
* [performance] Phar version now has an optimised autoloader

2.5.2 / 2016-09-04
==================

* [fixed] Exceptions are properly highlighted in error messages (@ciaranmcnulty)

2.5.1 / 2016-07-16
==================

* [fixed] Describing a class providing a namespace with leading backslash (@mheki)
* [fixed] bug where rerun test suite was uncoloured (@ciaranmcnulty)
* [fixed] Bug in DotFormatter when number of rows is multiple of column width (@bendavies)

2.5.0 / 2016-03-20
==================

* Fixed bug with typehints in classes defined in spec file
* Supports grouped Use statements
* Now shows path in error message when spec file doesn't contain a class
* Supports catching PHP 7 Errors in shouldThrow
* No longer attempts to generate methods with reserved names
* Fixed bug where bootstrapped classes could not be loaded after class generation
* Fixed bug where line numbers were incorrectly reported on PHP 7
* Fixed new methods being inserted incorrectly when strings included closing brace
* Dot formatter now shows spec count on last line

2.4.1 / 2016-01-01
==================

* Correctly handle nested class definitions 
* Correctly handle anonymous functions in code generation
* Fixed rerunning on Windows platform
* Fixed code generation on Windows platform
* Fixed issue with fatal errors being suppressed
* Handle underscores correctly when using PSR-4
* Fixed HTML formatter

2.4.0 / 2015-11-28
==================

* Improved docblock for beConstructedThrough()
* Handle and present fatal errors
* Fixed edge case with partial use statements
* Initial support for typehinted doubles in PHP7
* Specs can now be run by specifying a fully qualified class name
* New shouldContain matcher for strings
* Warning added when trying to typehint scalars or callable in spec
* No longer truncates strings when diffing arrays in verbose mode 
* New %resource_name% placeholder for generated specs
* Fixed case error in class name that triggered strictness warnings on some platforms
* Fixed undefined index error in some versions of Windows
* Clarified in composer that ext-tokenizer is required
* Supported installation with Symfony 3.0
* Fixed error when spec and src paths are the same
* New event is fired when phpspec creates a file
* Internal refactoring of Presenter objects

2.3.0 / 2015-09-07
==================

* Fixed bugs when generating methods in class with unusual whitespace
* Adds `duringInstantiation()` to more easily test constructor exceptions
* Adds `beConstructedThrough*()` and `beConstructed*()` shortcuts for named constructors
* Generated constructors are now placed at the start of the class
* Offers to make constructors private after generating a named constructor
* Shows a warning when a class is generated in a location that is not autoloadable
* Adds `%paths.config%` placeholder to allow config paths to be relative to config file
* Fixed invalid JUnit output in some non-EN locales

2.2.1 / 2015-05-30
==================

* Fix false positives in `shouldHaveKeyWithValue` matcher
* Fix fatal error in edge case when method call parameters don't match expectations

2.2.0 / 2015-04-18
==================

 * Better diffs when presenting unexpected method arguments
 * Better handling of methods delclared inside Traits when faking
 * Offer to generate interfaces for missing typehinted collaborators
 * Support for TAP format output
 * Remove deprecated usage of Symfony DialogHelper
 * New array `shouldHaveKeyWithValue` matcher
 * Clearer error message when specs have incorrect namespace prefix
 * Fix suite rerunning for HHVM
 * [BC break] The unused `ask` and `askAndValidate` methods on `Console\IO` have been removed

2.1.1 / 2015-01-09
==================

 * Smoother rendering for progress bar
 * Fixed progress bar for case where no examples are found
 * Tidier output alignment + block width
 * Removed deprecated calls to Yaml::parse
 * More accurate lower bounds for composer installation

2.1.0 / 2014-12-14
==================

  * Specify bootstrap file via configuration
  * Correct error codes while using --stop-on-failure
  * Better detection of empty specs
  * Fixed issue where non-spec files in spec folder caused errors
  * Better PSR-4 support
  * Allow objects to be instantiated via static factory methods
  * Automatic generation of return statements using '--fake'
  * Test suite is automatically rerun when classes or methods have been generated
  * Allow examples to mark themselves as skipped
  * PSR-4 support
  * PSR-0 locator now supports underscores correctly
  * Ability to specify a custom bootstrap file using '--bootstrap' (for autoloader registration etc)
  * Ability to have a personal .phpspec.yml in home folder
  * Progress bar grows from left to right and flickers less
  * Improved diffs for object comparison
  * Throw an exception when construction method is redefined
  * Non-zero exit code when dependencies are missing
  * Respect exit code of commands other than 'run'
  * Higher CLI verbosity levels are handled properly
  * Code Generation and Stop on Failure are configurable through phpspec.yml
  * Fixes for object instantiation changes in newer versions of PHP
  * PHP 5.6 support
  * Fixes for progress bar sometimes rounding up to 100% when not all specs passed
  * Support for non-standard Composer autoloader location
  * Improved hhvm support
  * Extensions can now register new command
  * Resource locator de-duplicates resources (supports custom locators in extensions)

2.0.1 / 2014-07-01
==================

  * Fixed the loading of the autoloader for projects using a custom composer vendor folder

2.0.0 / 2014-03-19
==================

  * Improve support to windows
  * Improve support to hhvm
  * Improve acceptance tests coverage with Behat
  * Revamped junit formatter
  * Fixed #269 Problem with exception masking and generation for not found class
  * HHVM is officially supported
  * Add psr0 validator
  * Remove Nyan from core
  * Added an exception if the specified config file does not exist
  * Fixed a problem with generating a constructor when it is first time added
  * Improved help
  * Fixed the suite runner in fast machines
  * Fixed the Prophecy constraint as the new release is 1.1
  * Refactored formatters to be defined as services
  * Fixed the invocation of methods expecting an argument passed by reference
  * Fixed the instantiation of the wrapped object in shouldThrow
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
  * Add collaborator constructor setter
  * Fix couple of bugs in Prophecy integration layer
  * New (old) dot formatter
  * Prevent loading of unexisting PHP files
  * Fix typos in the error messages
  * Bump required Prophecy version to 1.0.1
  * Support non-string values with ArrayContain matcher
  * Create `src` folder if does not exist
  * Fix stack trace and matchers failure printing

2.0.0beta1 / 2013-04-29
=======================

  * Initial release

