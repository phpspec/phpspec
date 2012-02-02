<?php

use Behat\Behat\Exception\PendingException,
    Behat\Gherkin\Node\PyStringNode;

require_once 'SpecContext.php';

class CommandContext extends SpecContext
{
    /**
     * @When /^I use the command "([^"]*)"$/
     */
    public function iUseTheCommand($command)
    {
        $this->output = $this->spec(shell_exec($command));
    }

    /**
     * @Then /^I should see$/
     */
    public function iShouldSee(PyStringNode $output)
    {
        $this->output->should->be($output->__toString());
    }
}