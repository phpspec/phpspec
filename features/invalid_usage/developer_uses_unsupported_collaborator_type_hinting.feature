Feature: Developer uses unsupported collaborator type hinting
  As a developer
  I should be shown special exception when I declare collaborators with unsupported type hinting

  Scenario: Array collaborator type hinting
    Given the spec file "spec/InvalidUsage/InvalidUsageExample1/StorageSpec.php" contains:
      """
      <?php

      namespace spec\InvalidUsage\InvalidUsageExample1;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class StorageSpec extends ObjectBehavior
      {
          function it_can_store_data(array $data)
          {
              $this->store($data)->shouldReturn(true);
          }
      }

      """
    And the class file "src/InvalidUsage/InvalidUsageExample1/Storage.php" contains:
      """
      <?php

      namespace InvalidUsage\InvalidUsageExample1;

      class Storage
      {
          public function store(array $data)
          {
              return true;
          }
      }

      """
    When I run phpspec
    Then I should see:
      """
            collaborator cannot be array or callable: argument 0 defined in
            spec\InvalidUsage\InvalidUsageExample1\StorageSpec::it_can_store_data.
      """

  @php-version @php5.4
  Scenario: Callable collaborator type hinting
    Given the spec file "spec/InvalidUsage/InvalidUsageExample2/InvokerSpec.php" contains:
      """
      <?php

      namespace spec\InvalidUsage\InvalidUsageExample2;

      use PhpSpec\ObjectBehavior;
      use Prophecy\Argument;

      class InvokerSpec extends ObjectBehavior
      {
          function it_invokes_callable(callable $callback)
          {
              $this->invoke($callback)->shouldReturn(true);
          }
      }

      """
    And the class file "src/InvalidUsage/InvalidUsageExample2/Invoker.php" contains:
      """
      <?php

      namespace InvalidUsage\InvalidUsageExample2;

      class Invoker
      {
          public function invoke(callable $data, array $parameters = array())
          {
              return true;
          }
      }

      """
    When I run phpspec
    Then I should see:
      """
            collaborator cannot be array or callable: argument 0 defined in
            spec\InvalidUsage\InvalidUsageExample2\InvokerSpec::it_invokes_callable.
      """
