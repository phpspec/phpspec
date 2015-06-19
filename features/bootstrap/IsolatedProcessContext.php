<?php

use Behat\Behat\Tester\Exception\PendingException;
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
    private $lastOutput;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @beforeSuite
     */
    public static function checkDependencies()
    {
        chdir(sys_get_temp_dir());
        if (!@`which expect`) {
            throw new \Exception('Smoke tests require the `expect` command line application. Try running with --profile=no-smoke');
        }
    }

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
            "send \"$answer\n\"\n" .
            "expect \"Y/n\"\n" .
            "interact\n" .
            "'"
        );

        $process->run();
        $this->lastOutput = $process->getOutput();
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
        expect($this->lastOutput)->toMatch('/autoload/');
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
     * @Given the isolated spec :file contains:
     */
    public function theIllformedFileContains($file, PyStringNode $contents)
    {
        $this->filesystem->dumpFile($file, (string)$contents);
        eval("@include($file)");
    }

    /**
     * @Then I should see :message
     */
    public function iShouldSee($message)
    {
        expect(strpos($this->lastOutput, $message))->toNotBe(false);
    }

    /**
     * @Then I should see the following parse error :message
     */
    public function iShouldSeeTheFollowingParseError($message)
    {
        $local = error_get_last();
        expect(strpos($local['message'], $message))->toNotBe(false);
    }

}
