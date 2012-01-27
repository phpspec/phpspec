PHPSpec 1.3
===========

PHPSpec 1.3.0
-------------

* SpecHelper.php is now the default bootstrap
* Fixed loading from configuration
* Fixed for broken custom matchers
* Added Junit Formatter
* Added haveKey matcher
* Added the bootstrap option
* Fixed predicate always passing
* Added ArrayVal as an interceptor
* Fixed #30 passing array to spec triggers autoloader
* Updated Japanese version of documentation
* Using DIRECTORY_SEPARATOR constant to take care of OS differences
* Missing put function in the Html formatter sends the output to the StdOut

PHPSpec 1.2
===========

PHPSpec 1.2.2
-------------

* Restored predicates
* Restored ability to intercept properties
* Included the case where spec description may have more than one digit together
* Added the RSpec MIT license in the HTML formatter template

PHPSpec 1.2.1
-------------

* Fixed backtrace with phpspec file not being printed even if the -b flag was pass
* Fixed -c creating ansii colour on html formatter
* Fixed example runner not displaying group names with -e
* Added -e, --example to the usage/help message

PHPSpec 1.2.0
-------------

* Added the --example|-e EXAMPLE flag to run single examples
* Added filter and validate utils
* Moved from phing to ant
* Remove phpspec.bat temporarially. Needs fix
* Using Symfony UniversalAutoloader in phpspec script
* Moved Zend context to phpspec-zend repository
* Moved tests from PHPUnit to PHPSpec
* Added the --fail-fast to stop on first failure/error/exception
* Added the --fail-fast to stop on first failure/error/exception
* Added the --formater|-f [h]tml flag and the HTML formatter
* Added Zend controller context matchers: redirect and redirectTo
* Added the --backtrace|-b flag to display full backtrace
* Removed autospec option from usage
* Document formatter supports namespaces
* Added support for .specignore
* Used the same export method from throwException for beAnInstanceOf to replace
  double backslash for one when printing
* Fixed cyclomatic complexity in class loader
