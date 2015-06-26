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
    /**
     * @var string
     */
    private $lastOutput;

    /**
     * @var string
     */
    private $lastError;

    /**
     * @Given I have started describing the :class class
     */
    public function iHaveStartedDescribingTheClass($class)
    {
        $descriptors = array(
            array('pipe', 'r'),
            array('pipe', 'w')
        );

        $process = proc_open(
            sprintf('%s %s %s', $this->buildPhpSpecCmd(), 'describe', escapeshellarg($class)),
            $descriptors,
            $pipes
        );

        expect(proc_close($process))->toBe(0);
    }

    /**
     * @When I run phpspec and answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecAndAnswerWhenAskedIfIWantToGenerateTheCode($answer)
    {
        $descriptors = array(
            array('pipe', 'r'),
            array('pipe', 'w'),
            array('pipe', 'w')
        );

        $env = array('SHELL_INTERACTIVE' => true);

        $process = proc_open(
            sprintf('%s %s', $this->buildPhpSpecCmd(), 'run'),
            $descriptors,
            $pipes,
            null,
            $env
        );

        fwrite($pipes[0], $answer);
        fclose($pipes[0]);

        $this->lastOutput = stream_get_contents($pipes[1]);
        $this->lastError = stream_get_contents($pipes[2]);

        proc_close($process);
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
        expect(substr_count($this->lastOutput, 'specs'))->toBe(2);
    }

    /**
     * @Then I should see an error about the missing autoloader
     */
    public function iShouldSeeAnErrorAboutTheMissingAutoloader()
    {
        expect($this->lastError)->toMatch('/autoload/');
    }
}
