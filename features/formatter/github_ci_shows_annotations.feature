Feature: Github CI annotations

  If we are running in a Github Action we can emit extra strings to enhance the way results are displayed

  @isolated
  Scenario: Error messages shown inline
    Given the GITHUB_ACTIONS environment variable is set to true
    And the spec file "spec/Github/FailingSpec.php" contains:
       """
       <?php

       namespace spec\Github;

       use PhpSpec\ObjectBehavior;

       class FailingSpec extends ObjectBehavior
       {
           function it_is_equal()
           {
               $this->foo()->shouldReturn(true);
           }
       }
       """
    And the class file "src/Github/Failing.php" contains:
       """
       <?php

       namespace Github;

       class Failing
       {
           function foo() { return false; }
       }
       """
    When I run phpspec
    Then I should see:
       """
       ::error file=spec/Github/FailingSpec.php,line=9,col=1::Failed: Expected true, but got false
       """


