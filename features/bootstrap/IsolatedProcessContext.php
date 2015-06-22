<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Defines application features from the specific context.
 */
class IsolatedProcessContext implements Context, SnippetAcceptingContext
{
    /**
<<<<<<< HEAD
     * @var Process
=======
     * @var Filesystem
     */
    private $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @beforeSuite
>>>>>>> Parse error capture
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

    /**
     * @When I run phpspec
     */
    public function iRunPhpspec()
    {
        $process = new Process(
            $this->buildPhpSpecCmd() . ' run spec/Message/Fatal/ParseSpec.php'
        );
        $process->run();
        $this->lastOutput = $process->getOutput();
    }

    /**
     * @Then I should see :message
     */
    public function iShouldSee($message)
    {
        expect(strpos($this->lastOutput, $message))->toNotBe(false);
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
     * @Then I should see :message
     */
    public function iShouldSee($message)
    {
        var_dump($this->lastOutput);
        expect(strpos($this->lastOutput, $message))->toNotBe(false);
    }

    /**
     * @Given the isolated file :file contains:
     */
    public function theFileContains($file, PyStringNode $contents)
    {
        $this->filesystem->dumpFile($file, (string)$contents);
    }

}
