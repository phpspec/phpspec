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

    private $lastOutput;

    /**
     * @Given I have started describing the :class class
     */
    public function iHaveStartedDescribingTheClass($class)
    {
        $command = sprintf('%s %s %s', $this->buildPhpSpecCmd(), 'describe', escapeshellarg($class));

        $process = new Process($command);

        $process->run();

        if ($process->getExitCode() !== 0) {
            throw new \Exception('The describe process ended with an error');
        }
    }

    /**
     * @When I run phpspec and answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecAndAnswerWhenAskedIfIWantToGenerateTheCode($answer)
    {
        $command = sprintf('%s %s', $this->buildPhpSpecCmd(), 'run');
        $env = array(
            'SHELL_INTERACTIVE' => true,
            'HOME' => getenv('HOME'),
            'PATH' => getenv('PATH'),
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
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $cmd = escapeshellcmd('' . __DIR__ . '/../../bin/phpspec');
        if ($isWindows) {
            $cmd = 'php ' . $cmd;
        }

        return $cmd;
    }

    /**
     * @Then the tests should be rerun
     */
    public function theTestsShouldBeRerun()
    {
        if (substr_count($this->process->getOutput(), 'specs') !== 2) {
            throw new \Exception('The tests were not rerun');
        }
    }

    /**
     * @Then I should see an error about the missing autoloader
     */
    public function iShouldSeeAnErrorAboutTheMissingAutoloader()
    {
        if (!preg_match('/autoload/', $this->process->getErrorOutput().$this->process->getOutput())) {
            throw new \Exception('There was no error regarding a missing autoloader:');
        }
    }

    /**
     * @When I run phpspec
     */
    public function iRunPhpspec()
    {
        $process = new Process(
            $this->buildPhpSpecCmd() . ' run'
        );
        $process->run();
        $this->lastOutput = $process->getOutput();
    }

    /**
     * @When I run phpspec with the :formatter formatter
     */
    public function iRunPhpspecWithThe($formatter)
    {
        $process = new Process(
            $this->buildPhpSpecCmd() . " --format=$formatter run"
        );
        $process->run();
        $this->lastOutput = $process->getErrorOutput().$process->getOutput();

    }

    /**
     * @Then I should see :message
     */
    public function iShouldSee($message)
    {
        if (strpos($this->lastOutput, $message) === false) {
            throw new \Exception("Missing message: $message");
        }
    }

}
