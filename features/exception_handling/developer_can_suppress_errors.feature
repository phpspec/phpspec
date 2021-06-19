Feature: Developer uses error control operator "@"
  As a Developer
  I want to be able to use the error control operator "@"
  In order to suppress certain errors and still have my specs passing

  Scenario: An unsuppressed error will cause a valid spec failure
    Given the spec file "spec/Runner/ErrorSuppression1/ErrorControlSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ErrorSuppression1;

      use PhpSpec\ObjectBehavior;

      class ErrorControlSpec extends ObjectBehavior
      {
          function it_returns_string()
          {
              $this->notSuppressing()->shouldBe('it works!');
          }
      }

      """
    And the class file "src/Runner/ErrorSuppression1/ErrorControl.php" contains:
      """
      <?php

      namespace Runner\ErrorSuppression1;

      class ErrorControl
      {
          public function notSuppressing(): string
          {
              trigger_error('Nope!', E_USER_WARNING);

              return 'it works!';
          }
      }

      """
    When I run phpspec
    Then I should see "1 broken"

  Scenario: A suppressed error will not cause a spec failure
    Given the spec file "spec/Runner/ErrorSuppression2/ErrorControlSpec.php" contains:
      """
      <?php

      namespace spec\Runner\ErrorSuppression2;

      use PhpSpec\ObjectBehavior;

      class ErrorControlSpec extends ObjectBehavior
      {
          function it_returns_string()
          {
              $this->suppressing()->shouldBe('it works!');
          }
      }

      """
    And the class file "src/Runner/ErrorSuppression2/ErrorControl.php" contains:
      """
      <?php

      namespace Runner\ErrorSuppression2;

      class ErrorControl
      {
          public function suppressing(): string
          {
              @trigger_error('Nope!', E_USER_WARNING);

              return 'it works!';
          }
      }

      """
    When I run phpspec
    Then the suite should pass
