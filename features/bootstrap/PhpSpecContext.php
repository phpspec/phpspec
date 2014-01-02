<?php

use Behat\Behat\Context\BehatContext;
use Behat\Gherkin\Node\PyStringNode;
use PhpSpec\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

class PhpSpecContext extends BehatContext
{
    /**
     * @var string|null
     */
    private $workDir = null;

    /**
     * @var ApplicationTester|null
     */
    private $applicationTester = null;

    /**
     * @BeforeScenario
     */
    public function createWorkDir()
    {
        $this->workDir = sys_get_temp_dir().'/'.uniqid('PhpSpecContext_').'/';

        mkdir($this->workDir, 0777, true);
        chdir($this->workDir);
    }

    /**
     * @AfterScenario
     */
    public function removeWorkDir()
    {
        system('rm -rf '.$this->workDir);
    }

    /**
     * @When /^(?:|I )run phpspec$/
     */
    public function iRunPhpspec()
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run('run --no-interaction');
    }

    /**
     * @When /^(?:|I )run phpspec and answer "(?P<answer>[^"]*)" when asked if I want to generate the code$/
     */
    public function iRunPhpspecAndAnswer($answer)
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->putToInputStream(sprintf("%s\n", $answer));
        $this->applicationTester->run('run', array('interactive' => true));
    }

    /**
     * @When /^(?:|I )start(?:|ed) describing (?:|the )"(?P<class>[^"]*)" class$/
     */
    public function iStartDescribing($class)
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run(sprintf('describe %s --no-interaction', $class));
    }

    /**
     * @Given /^(?:|the )(?:spec |class )file "(?P<file>[^"]+)" contains:$/
     */
    public function theFileContains($file, PyStringNode $string)
    {
        mkdir(dirname($file), 0777, true);

        file_put_contents($file, $string->getRaw());

        require_once($file);
    }

    /**
     * @Then /^(?:|a )new spec should be generated in (?:|the )"(?P<file>[^"]*Spec.php)":$/
     * @Then /^(?:|a )new class should be generated in (?:|the )"(?P<file>[^"]+)":$/
     */
    public function aNewSpecificationShouldBeGeneratedInTheSpecFile($file, PyStringNode $string)
    {
        if (!file_exists($file)) {
            throw new \LogicException(sprintf('"%s" file was not created', $file));
        }

        expect(file_get_contents($file))->toBe($string->getRaw());
    }

    /**
     * @Then /^(?:|I )should see "(?P<message>[^"]*)"$/
     */
    public function iShouldSee($message)
    {
        expect($this->applicationTester->getDisplay())->toMatch('/'.preg_quote($message, '/').'/sm');
    }

    /**
     * @Then /^(?:|the )example should pass$/
     */
    public function theExampleShouldPass()
    {
        $this->iShouldSee('1 example (1 passed)');
    }

    /**
     * @return ApplicationTester
     */
    private function createApplicationTester()
    {
        $application = new Application('2.0-dev');
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
