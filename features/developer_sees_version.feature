Feature: Developer sees version
  As a developer
  In order to know which version of PHPSpec is being run
  I want a command line option that allows just that
  
  Scenario: Long option
  When I use the command "phpspec --version"
  Then I should see the current version