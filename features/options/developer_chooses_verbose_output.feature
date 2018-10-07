Feature: Developer chooses verbosity output
  As a Developer
  I want to set the verbose setting option
  In order to specify how console output bheaves on failure

  Scenario: config verbosity used if console verbosity not quiet
    Given the config file contains:
      """
      verbose: true
      """
    Given the spec file "spec/Verbose/SpecExample1/ConfigVerbosityConsoleNotSettedSpec.php" contains:
      """
      <?php

      namespace spec\Verbose\SpecExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ConfigVerbosityConsoleNotSettedSpec extends ObjectBehavior
      {
          function it_fails()
          {
              $this->getValue()->shouldReturn([0, 1]);
          }
      }

      """
    And the class file "src/Verbose/SpecExample1/ConfigVerbosityConsoleNotSetted.php" contains:
      """
      <?php

      namespace Verbose\SpecExample1;

      class ConfigVerbosityConsoleNotSetted
      {
          public function getValue()
          {
              return [0];
          }
      }

      """
    When I run phpspec
    Then Output should contains:
      """
      expected [array:2], but got [array:1].

      """
    And Output should contains:
      """
      -    1 => 1,
      """

  Scenario: config verbosity false
    Given the spec file "spec/Verbose/SpecExample2/ConfigVerbosityConsoleNotSettedSpec.php" contains:
      """
      <?php

      namespace spec\Verbose\SpecExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ConfigVerbosityConsoleNotSettedSpec extends ObjectBehavior
      {
          function it_fails()
          {
              $this->getValue()->shouldReturn([0, 1]);
          }
      }

      """
    And the class file "src/Verbose/SpecExample2/ConfigVerbosityConsoleNotSetted.php" contains:
      """
      <?php

      namespace Verbose\SpecExample2;

      class ConfigVerbosityConsoleNotSetted
      {
          public function getValue()
          {
              return [0];
          }
      }

      """
    When I run phpspec
    Then Output should contains:
      """
      expected [array:2], but got [array:1].

      """
    And Output should not contains:
      """
      -    1 => 1,
      """

  Scenario: config verbosity override if console verbosity is quiet
    Given the config file contains:
      """
      verbose: true
      """
    Given the spec file "spec/Verbose/SpecExample3/ConfigVerbosityConsoleNotSettedSpec.php" contains:
      """
      <?php

      namespace spec\Verbose\SpecExample3;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class ConfigVerbosityConsoleNotSettedSpec extends ObjectBehavior
      {
          function it_fails()
          {
              $this->getValue()->shouldReturn([0, 1]);
          }
      }

      """
    And the class file "src/Verbose/SpecExample3/ConfigVerbosityConsoleNotSetted.php" contains:
      """
      <?php

      namespace Verbose\SpecExample3;

      class ConfigVerbosityConsoleNotSetted
      {
          public function getValue()
          {
              return [0];
          }
      }

      """
    When I run phpspec with the "quiet" option
    Then Output should not be shown