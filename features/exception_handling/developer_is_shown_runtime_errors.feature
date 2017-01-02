Feature: Developer is shown runtime errors
  As a developer
  To debug fatal errors better
  I should be shown errors but the rest of the suite should run

  Scenario: Runtime error in class being specified
    Given the spec file "spec/Message/Fatal/RuntimeSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal;

      use PhpSpec\ObjectBehavior;

      class RuntimeSpec extends ObjectBehavior
      {
          function it_breaks()
          {
              $this->broken();
          }

          function it_passes()
          {
              $this->passing();
          }
      }
      """
    And the class file "src/Message/Fatal/Runtime.php" contains:
      """
      <?php

      namespace Message\Fatal;

      class Runtime
      {
          public function broken()
          {
              foo();
          }

          public function passing()
          {
          }
      }

      """
    When I run phpspec
    Then I should see "1 passed, 1 broken"

  Scenario: Runtime error in spec
    Given the spec file "spec/Message/Fatal2/RuntimeSpec.php" contains:
      """
      <?php

      namespace spec\Message\Fatal2;

      use PhpSpec\ObjectBehavior;

      class RuntimeSpec extends ObjectBehavior
      {
          function it_breaks()
          {
              foo();
          }

          function it_passes()
          {
              $this->passing();
          }
      }
      """
    And the class file "src/Message/Fatal2/Runtime.php" contains:
      """
      <?php

      namespace Message\Fatal2;

      class Runtime
      {
          public function passing()
          {
          }
      }

      """
    When I run phpspec
    Then I should see "1 passed, 1 broken"
