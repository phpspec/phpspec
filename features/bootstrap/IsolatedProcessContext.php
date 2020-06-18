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

    protected $executablePath = __DIR__ . '/../../bin/phpspec';

    /**
     * @Given I have started describing the :class class
     */
    public function iHaveStartedDescribingTheClass($class)
    {
        $process = $this->createPhpSpecProcess([
            'describe',
            escapeshellarg($class)
        ]);

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
        $env = array(
            'SHELL_INTERACTIVE' => true,
            'HOME' => getenv('HOME'),
            'PATH' => getenv('PATH'),
            'COLUMNS' => 80,
        );

        $this->process = $process = $this->createPhpSpecProcess(['run']);

        $process->setEnv($env);
        $process->setInput($answer);
        $process->run();
    }

    /**
     * @return string
     */
    protected function buildPhpSpecCmd()
    {
        if (!file_exists($this->executablePath)) {
            throw new \RuntimeException('Could not find phpspec executable at ' . $this->executablePath);
        }

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $cmd = escapeshellcmd($this->executablePath);
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
            throw new \Exception(sprintf('There was no error regarding a missing autoloader: %s', $this->process->getErrorOutput().$this->process->getOutput()));
        }
    }

    /**
     * @When I run phpspec
     */
    public function iRunPhpspec()
    {
        $process = $this->createPhpSpecProcess(['run']);
        $process->run();
        $this->lastOutput = $process->getOutput();
    }

    /**
     * @When I run phpspec with the :formatter formatter
     */
    public function iRunPhpspecWithThe($formatter)
    {
        $process = $this->createPhpSpecProcess([
            "--format=$formatter",
            "run"
        ]);
        $process->run();
        $this->lastOutput = $process->getErrorOutput().$process->getOutput();

    }

    /**
     * @Then I should see :message
     */
    public function iShouldSee($message)
    {
        if (strpos($this->lastOutput, $message) === false) {
            throw new \Exception("Missing message: $message\nActual: {$this->lastOutput}");
        }
    }

    /**
     * @Then the suite should pass
     */
    public function theSuiteShouldPass()
    {
        $exitCode = $this->process->getExitCode();
        if ($exitCode !== 0) {
            throw new \Exception(sprintf('Expected that tests will pass, but exit code was %s.', $exitCode));
        }
    }

    private function createPhpSpecProcess(array $arguments)
    {
        $command = $this->buildPhpSpecCmd() . ' ' . implode(' ', $arguments);

        if (method_exists(Process::class, 'fromShellCommandline')) {
            return Process::fromShellCommandline($command);
        }

        return new Process($command);
    }
}
