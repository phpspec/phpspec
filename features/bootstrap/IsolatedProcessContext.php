<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Helper\IsolatedProcess;

/**
 * Defines application features from the specific context.
 */
class IsolatedProcessContext implements Context, SnippetAcceptingContext
{
    /**
     * @var Helper\IsolatedProcess
     */
    private $process;

    /**
     * @Given I have started describing the :class class
     */
    public function iHaveStartedDescribingTheClass($class)
    {
        $command = sprintf('%s %s %s', $this->buildPhpSpecCmd(), 'describe', escapeshellarg($class));

        $process = new IsolatedProcess($command);

        expect($process->run())->toBe(0);
    }

    /**
     * @When I run phpspec and answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecAndAnswerWhenAskedIfIWantToGenerateTheCode($answer)
    {
        $command = sprintf('%s %s', $this->buildPhpSpecCmd(), 'run');
        $env = array(
            'SHELL_INTERACTIVE' => true,
            'HOME' => $_SERVER['HOME']
        );

        $this->process = new IsolatedProcess($command, $env);

        $this->process->open();
        $this->process->sendInput($answer);
        $this->process->close();
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
        expect(substr_count($this->process->getOutput(), 'specs'))->toBe(2);
    }

    /**
     * @Then I should see an error about the missing autoloader
     */
    public function iShouldSeeAnErrorAboutTheMissingAutoloader()
    {
        expect($this->process->getError())->toMatch('/autoload/');
    }
}
