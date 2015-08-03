<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Symfony\Component\Process\Process;

/**
 * Defines application features from the specific context.
 */
class IsolatedProcessContext implements Context, SnippetAcceptingContext
{
    /**
     * @var Process
     */
    private $process;

    /**
     * @Given I have started describing the :class class
     */
    public function iHaveStartedDescribingTheClass($class)
    {
        $command = sprintf('%s %s %s', $this->buildPhpSpecCmd(), 'describe', escapeshellarg($class));

        $process = new Process($command);

        $process->run();

        expect($process->getExitCode())->toBe(0);
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

        $this->process = $process = new Process($command);

        $process->setEnv($env);
        $process->setInput($answer);
        $process->run();
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
        expect($this->process->getErrorOutput())->toMatch('/autoload/');
    }
}
