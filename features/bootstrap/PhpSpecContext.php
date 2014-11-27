<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Console\ApplicationTester;
use PhpSpec\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

class PhpSpecContext implements Context
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

        $this->workDir = sprintf(
            '%s/%s/',
            sys_get_temp_dir(),
            uniqid('PhpSpecContext_')
        );
        $fs = new Filesystem();
        $fs->mkdir($this->workDir, 0777);
        chdir($this->workDir);
    }

    /**
     * @AfterScenario
     */
    public function removeWorkDir()
    {
        $fs = new Filesystem();
        $fs->remove($this->workDir);
    }

    /**
     * @When I run phpspec (non interactively)
     */
    public function iRunPhpspec()
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run('run --no-interaction', array('decorated' => false));
    }

    /**
     * @When /^(?:|I )run phpspec interactively$/
     */
    public function iRunPhpspecInteractively()
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run('run', array('interactive' => true, 'decorated' => false));
    }

    /**
     * @When /^(?:|I )run phpspec interactively with the "([^"]*)" option$/
     */
    public function iRunPhpspecInteractivelyWithTheOption($option)
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run('run --' . $option, array('interactive' => true, 'decorated' => false));
    }

    /**
     * @When /^(?:|I )run phpspec with the "([^"]*)" option$/
     */
    public function iRunPhpspecWithTheOption($option)
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run('run --no-interaction --' . $option);
    }

    /**
     * @When /^I run phpspec using the "([^"]*)" format$/
     */
    public function iRunPhpspecUsingTheFormat($format)
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run(sprintf('run --no-interaction -f%s', $format), array('decorated' => false));
    }

    /**
     * @When I run phpspec and answer :answer when asked if I want to generate the code
     * @When I run phpspec with the option :option and (I) answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecAndAnswer($answer, $option="")
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->putToInputStream(sprintf("%s\n", $answer));
        $this->applicationTester->run($option?'run --'.$option:'run', array('interactive' => true, 'decorated' => false));
    }

    /**
     * @When /^(?:|I )run phpspec with option (?P<key>--[a-z]+)=(?P<value>.+)$/
     */
    public function iRunPhpspecWithOption($key, $value)
    {
        $options = array(
            "--no-interaction",
            sprintf("%s=%s", $key, $value)
        );

        $command = sprintf("run %s", join(" ", $options));
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run($command, array('decorated' => false));
    }

    /**
     * @When /^(?:|I )start describing (?:|the )"(?P<class>[^"]*)" class$/
     * @When /^(?:|I )have started describing (?:|the )"(?P<class>[^"]*)" class$/
     */
    public function iStartDescribing($class)
    {
        $this->applicationTester = $this->createApplicationTester();
        $this->applicationTester->run(sprintf('describe %s --no-interaction', $class), array('decorated' => false));
    }

    /**
     * @Given /^(?:|the )(?:spec |class )file "(?P<file>[^"]+)" contains:$/
     */
    public function theFileContains($file, PyStringNode $string)
    {
        $this->saveFile($file, $string);
        require_once($file);
    }

    /**
     * @Given /^the config file contains:$/
     */
    public function theConfigFileContains(PyStringNode $string)
    {
        file_put_contents('phpspec.yml', $string->getRaw());
    }

    /**
     * @Given /^(?:|the )(?:bootstrap )file "(?P<file>[^"]+)" contains:$/
     */
    public function theBootstrapContains($file, PyStringNode $string)
    {
        $this->saveFile($file, $string);
    }

    /**
     * @Given /^there is no file (?P<file>missing.php)$/
     */
    public function thereIsNoFile($file)
    {
        if (file_exists($file)) {
            throw new \LogicException(sprintf('"%s" file already exists', $file));
        }
    }

    /**
     * @Then /^(?:|a )new spec should be generated in (?:|the )"(?P<file>[^"]*Spec.php)":$/
     * @Then /^(?:|a )new class should be generated in (?:|the )"(?P<file>[^"]+)":$/
     * @Then /^(?:|the )class in (?:|the )"(?P<file>[^"]+)" should contain:$/
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
     * @Then I should see:
     *
     * @param PyStringNode $message
     */
    public function iShouldSeeBlock(PyStringNode $message)
    {
        $this->iShouldSee((string)$message);
    }

    /**
     * @Then /^(?:|I )should not see "(?P<message>[^"]*)"$/
     */
    public function iShouldNotSee($message)
    {
        expect($this->applicationTester->getDisplay())->notToMatch('/'.preg_quote($message, '/').'/sm');
    }

    /**
     * @Then /^I should see valid junit output$/
     */
    public function iShouldSeeValidJunitOutput()
    {
        $dom = new \DOMDocument();
        $dom->loadXML($this->applicationTester->getDisplay());
        expect($dom->schemaValidate(__DIR__ . '/../../src/PhpSpec/Resources/schema/junit.xsd'))->toBe(true);
    }

    /**
     * @Then /^(?:|the )suite should pass$/
     */
    public function theSuiteShouldPass()
    {
        $stats = $this->getRunStats();

        expect($stats['examples'] > 0)->toBe(true);
        expect($stats['examples'])->toBe($stats['passed'] + $stats['skipped']);
        expect($this->applicationTester->getStatusCode())->toBe(0);
    }

    /**
     * @Then /^(\d+) examples? should have been skipped$/
     */
    public function exampleShouldHaveBeenSkipped($count)
    {
        $stats = $this->getRunStats();

        expect($stats['skipped'])->toBe(intval($count));
    }

    /**
     * @Then /^(\d+) examples? should have been run$/
     */
    public function exampleShouldHaveBeenRun($count)
    {
        $stats = $this->getRunStats();

        expect($stats['examples'])->toBe(intval($count));
    }

    /**
     * @Then the tests should be rerun
     */
    public function theTestsShouldBeRerun()
    {
        expect($this->applicationTester)->toHaveBeenRerun();
    }

    /**
     * @Then the tests should not be rerun
     */
    public function theTestsShouldNotBeRerun()
    {
        expect($this->applicationTester)->toNotHaveBeenRerun();
    }

    /**
     * @return array
     *
     * @throws \LogicException
     */
    private function getRunStats()
    {
        $output = $this->applicationTester->getDisplay();
        $matches = array();

        $regexp =
            '/.*'.
            '(?P<examples>\d+) examples?.*'.
            '\('.
            '(?:(?P<passed>\d+) passed)?.*?'.
            '(?:(?P<skipped>\d+) skipped)?.*?'.
            '(?:(?P<broken>\d+) broken)?.*?'.
            '(?:(?P<failed>\d+) failed)?'.
            '\)'.
            '.*/sm';

        if(!preg_match($regexp, $output, $matches)) {
            throw new \LogicException(sprintf('Could not determine the run result based on the output: %s', $output));
        }

        return array(
            'examples' => (int) $matches['examples'],
            'passed' => isset($matches['passed']) ? (int) $matches['passed'] : 0,
            'skipped' => isset($matches['skipped']) ? (int) $matches['skipped'] : 0,
            'broken' => isset($matches['broken']) ? (int) $matches['broken'] : 0,
            'failed' => isset($matches['failed']) ? (int) $matches['failed'] : 0,
        );
    }

    /**
     * @return ApplicationTester
     */
    private function createApplicationTester()
    {
        $application = new Application('2.1-dev');
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }

    /**
     * @Then I should not be prompted for code generation
     */
    public function iShouldNotBePromptedForCodeGeneration()
    {
        $this->iShouldNotSee('Do you want me to');
    }

    /**
     * @Then I should be prompted for code generation
     */
    public function iShouldBePromptedForCodeGeneration()
    {
        $this->iShouldSee('Do you want me to');
    }

    /**
     * @param $file
     * @param PyStringNode $string
     * @return void
     */
    private function saveFile($file, PyStringNode $string)
    {
        $dirname = dirname($file);
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        }

        file_put_contents($file, $string->getRaw());
    }

    /**
     * @Then the exit code should be :code
     */
    public function theExitCodeShouldBe($code)
    {
        expect($this->applicationTester->getStatusCode())->toBe((int)$code);
    }
}
