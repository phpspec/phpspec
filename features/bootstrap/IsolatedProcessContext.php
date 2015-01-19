<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Process\Process;

/**
 * Defines application features from the specific context.
 */
class IsolatedProcessContext implements Context, SnippetAcceptingContext
{
    private $lastOutput;

    /**
     * @Given I have started describing the :class class
     */
    public function iHaveStartedDescribingTheClass($class)
    {
        $process = new Process($this->buildPhpSpecCmd() . ' describe '. escapeshellarg($class));
        $process->run();

        expect($process->getExitCode())->toBe(0);
    }

    /**
     * @When I run phpspec and answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecAndAnswerWhenAskedIfIWantToGenerateTheCode($answer)
    {
        $process = new Process(
            "exec expect -c '\n" .
            "set timeout 10\n" .
            "spawn {$this->buildPhpSpecCmd()} run\n" .
            "expect \"Y/n\"\n" .
            "sleep 0.1\n" .
            "send \"$answer\n\"\n" .
            "sleep 1\n" .
            "interact\n" .
            "'"
        );

        $process->run();
        $this->lastOutput = $process->getOutput();

        expect($process->getErrorOutput())->toBe(null);
    }

    /**
     * @return string
     */
    protected function buildPhpSpecCmd()
    {
        return escapeshellcmd(__DIR__ . '/../../bin/phpspec');
    }

    /**
     * @Then the tests should be rerun
     */
    public function theTestsShouldBeRerun()
    {
        expect(substr_count($this->lastOutput, 'Y/n'))->toBe(2);
    }
}
