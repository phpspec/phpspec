Feature: Use the TAP formatter
  So that I can get non-XML parseable results
  As a Developer
  I need to be able to use a TAP formatter

  Scenario: A spec which causes various result states
    Given the spec file "spec/Formatter/TapExample1/TapSpec.php" contains:
      """
      <?php

      namespace spec\Formatter\TapExample1;

      use PhpSpec\ObjectBehavior;
      use PhpSpec\Exception\Example\SkippingException;

      class TapSpec extends ObjectBehavior
      {
          function it_is_most_definitely_pending()
          {
          }

          function it_is_most_definitely_passing()
          {
              $this->fire('pass')->shouldReturn('pass');
          }

          function it_is_most_definitely_failing()
          {
              $this->fire('fail')->shouldReturn('pass');
          }

          function it_is_most_definitely_broken()
          {
              $this->fire('broken')->shouldReturn('pass');
          }

          function it_is_most_definitely_skipping()
          {
              throw new SkippingException('php is not installed');
          }
      }
      """
    And the class file "src/Formatter/TapExample1/Tap.php" contains:
      """
      <?php

      namespace Formatter\TapExample1;

      use PhpSpec\Exception\Example\ErrorException;

      class Tap
      {
          public function fire($stuff)
          {
              switch ($stuff) {
                  case 'pass':
                      return 'pass';
                  case 'fail':
                      return 'fail';
                  case 'broken':
                      throw new ErrorException('error','something terrible occurred','foo.php',99);
              }
          }
      }
      """
    When I run phpspec using the "tap" format
    Then I should see:
      """
      TAP version 13
      not ok 1 - Formatter\TapExample1\Tap: is most definitely pending # TODO todo: write pending example
        ---
        severity: todo
        ...
      ok 2 - Formatter\TapExample1\Tap: is most definitely passing
      not ok 3 - Formatter\TapExample1\Tap: is most definitely failing
        ---
        message: 'Expected "pass", but got "fail".'
        severity: fail
        ...
      not ok 4 - Formatter\TapExample1\Tap: is most definitely broken
        ---
        message: 'error: something terrible occurred in foo.php line 99'
        severity: fail
        ...
      ok 5 - Formatter\TapExample1\Tap: is most definitely skipping # SKIP skipped: php is not installed
      1..5

      """
