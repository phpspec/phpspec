<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Process\Process;

/**
 * Defines application features from the specific context.
 */
class IsolatedProcessContext implements Context
{
    private $lastOutput;

    /**
     * @beforeSuite
     */
    public static function checkDependencies()
    {
        chdir(sys_get_temp_dir());
        if (!@`which expect`) {
            throw new \Exception('Smoke tests require the `expect` command line application');
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
        $this->writeAutoloader(getcwd());

        $process = new Process(
            "exec expect -c '\n" .
            "set timeout 10\n" .
            "spawn {$this->buildPhpSpecCmd()} run\n" .
            "expect \"Y/n\"\n" .
            "send \"$answer\\n\"\n" .
            "expect \"ms\"\n" .
            "interact'"
        );

        $process->run();
        $this->lastOutput = $process->getOutput();

        expect((bool)$process->getErrorOutput())->toBe(false);
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
     * @param $dir
     */
    private function writeAutoloader($dir)
    {
        copy(__DIR__ . '/autoloader/autoload.php', $dir . '/vendor/autoload.php');
    }
}
