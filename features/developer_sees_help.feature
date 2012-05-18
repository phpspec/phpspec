Feature: Developer sees help
  As a developer
  In order to know which options are available in PHPSpec
  I want a command line help option
  
  Scenario: Long option
  When I use the command "phpspec --help"
  Then I should see
  """
  Usage: phpspec (FILE|DIRECTORY) + [options]
      
      -b, --backtrace          Enable full backtrace
      -c, --colour, --color    Enable color in the output
      -e, --example STRING     Run examples whose full nested names include STRING
      -f, --formater FORMATTER Choose a formatter
                                [p]rogress (default - dots)
                                [d]ocumentation (group and example names)
                                [h]tml
                                [j]unit
                                custom formatter class name
      --bootstrap FILENAME     Specify a bootstrap file to run before the tests
      -h, --help               You're looking at it
      --fail-fast              Abort the run on first failure.
      --version                Show version
  
  
  """

  Scenario: Short option
  When I use the command "phpspec -h"
  Then I should see
  """
  Usage: phpspec (FILE|DIRECTORY) + [options]
      
      -b, --backtrace          Enable full backtrace
      -c, --colour, --color    Enable color in the output
      -e, --example STRING     Run examples whose full nested names include STRING
      -f, --formater FORMATTER Choose a formatter
                                [p]rogress (default - dots)
                                [d]ocumentation (group and example names)
                                [h]tml
                                [j]unit
                                custom formatter class name
      --bootstrap FILENAME     Specify a bootstrap file to run before the tests
      -h, --help               You're looking at it
      --fail-fast              Abort the run on first failure.
      --version                Show version
  
  
  """
  
  Scenario: No option
  When I use the command "phpspec"
  Then I should see
  """
  phpspec: No spec file given
  
  """