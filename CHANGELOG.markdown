PHPSpec 1.2
===========

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
