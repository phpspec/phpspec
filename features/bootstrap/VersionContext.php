<?php

use Behat\Behat\Exception\PendingException;

require_once 'SpecContext.php';

class VersionContext extends SpecContext
{
    /**
     * @When /^I use the command "([^"]*)"$/
     */
    public function iUseTheCommand($command)
    {
        $this->output = $this->spec(shell_exec($command));
    }

    /**
     * @Then /^I should see the current version$/
     */
    public function iShouldSeeTheCurrentVersion()
    {
        $this->output->should->be(\PHPSpec\Runner\Cli\Runner::VERSION . PHP_EOL);
    }
}