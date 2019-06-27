Feature: Developer chooses verbosity output
  As a Developer
  I want to set the verbose setting option
  In order to specify how console output bheaves on failure

  Scenario: config verbosity used if console verbosity not quiet
    Given the config file contains:
      """
      verbose: true
      """
    Given the spec file "spec/Verbose/SpecExample1/ConfigVerbosityConsoleNotSetSpec.php" contains:
      """
      <?php

      namespace spec\Verbose\SpecExample1;

      use PhpSpec\ObjectBehavior;

      class ConfigVerbosityConsoleNotSetSpec extends ObjectBehavior
      {
          function it_fails()
          {
              $this->getValue()->shouldReturn([0, 1]);
          }
      }

      """
    And the class file "src/Verbose/SpecExample1/ConfigVerbosityConsoleNotSet.php" contains:
      """
      <?php

      namespace Verbose\SpecExample1;

      class ConfigVerbosityConsoleNotSet
      {
          public function getValue()
          {
              return [0];
          }
      }

      """
    When I run phpspec
    Then The output should contain:
      """
      expected [array:2], but got [array:1].

      """
    And The output should contain:
      """
      -    1 => 1,
      """

  Scenario: config verbosity not set
    Given the spec file "spec/Verbose/SpecExample2/ConfigVerbosityNotSetConsoleNotSetSpec.php" contains:
      """
      <?php

      namespace spec\Verbose\SpecExample2;

      use PhpSpec\ObjectBehavior;

      class ConfigVerbosityNotSetConsoleNotSetSpec extends ObjectBehavior
      {
          function it_fails()
          {
              $this->getValue()->shouldReturn([0, 1]);
          }
      }

      """
    And the class file "src/Verbose/SpecExample2/ConfigVerbosityNotSetConsoleNotSet.php" contains:
      """
      <?php

      namespace Verbose\SpecExample2;

      class ConfigVerbosityNotSetConsoleNotSet
      {
          public function getValue()
          {
              return [0];
          }
      }

      """
    When I run phpspec
    Then The output should contain:
      """
      expected [array:2], but got [array:1].

      """
    And The output should not contain:
      """
      -    1 => 1,
      """

  Scenario: config verbosity set to true overriden if console verbosity is quiet
    Given the config file contains:
      """
      verbose: true
      """
    Given the spec file "spec/Verbose/SpecExample3/ConsoleQuietVerbosityOverrideConfigVerbositySpec.php" contains:
      """
      <?php

      namespace spec\Verbose\SpecExample3;

      use PhpSpec\ObjectBehavior;

      class ConsoleQuietVerbosityOverrideConfigVerbositySpec extends ObjectBehavior
      {
          function it_fails()
          {
              $this->getValue()->shouldReturn([0, 1]);
          }
      }

      """
    And the class file "src/Verbose/SpecExample3/ConsoleQuietVerbosityOverrideConfigVerbosity.php" contains:
      """
      <?php

      namespace Verbose\SpecExample3;

      class ConsoleQuitenessOverrideConfigVerbosity
      {
          public function getValue()
          {
              return [0];
          }
      }

      """
    When I run phpspec with the "quiet" option
    Then Output should not be shown

  Scenario: config verbosity set to false overriden if console verbosity set
    Given the config file contains:
      """
      verbose: false
      """
    Given the spec file "spec/Verbose/SpecExample4/ConsoleVerbosityOverrideConfigVerbosityFalseSpec.php" contains:
      """
      <?php

      namespace spec\Verbose\SpecExample4;

      use PhpSpec\ObjectBehavior;

      class ConsoleVerbosityOverrideConfigVerbosityFalseSpec extends ObjectBehavior
      {
          function it_fails()
          {
              $this->getValue()->shouldReturn([0, 1]);
          }
      }

      """
    And the class file "src/Verbose/SpecExample4/ConsoleVerbosityOverrideConfigVerbosityFalse.php" contains:
      """
      <?php

      namespace Verbose\SpecExample4;

      class ConsoleVerbosityOverrideConfigVerbosityFalse
      {
          public function getValue()
          {
              return [0];
          }
      }

      """
    When I run phpspec with the "verbose" option
    Then The output should contain:
      """
      -    1 => 1,
      """