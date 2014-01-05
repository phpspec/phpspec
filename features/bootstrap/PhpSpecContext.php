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
     * @var integer
     */
    private $uniqueTokenCounter = 0;

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
     * @When /^(?:|I )start describing (?:|the )"(?P<class>[^"]*)" class$/
     * @When /^(?:|I )have started describing (?:|the )"(?P<class>[^"]*)" class$/
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
        $this->writeNewClassFile($file, (string)$string);
    }

    /**
     * @param string $file
     * @param string $string
     */
    private function writeNewClassFile($file, $string)
    {
        mkdir(dirname($file), 0777, true);

        file_put_contents($file, $string);

        require_once($file);
    }

    /**
     * @Given /^I have an example that contains:$/
     */
    public function iHaveAnExampleThatContains(PyStringNode $string)
    {
        $uniqueToken = ++$this->uniqueTokenCounter;
        $className = 'Class' . $uniqueToken . 'Spec';
        $file = 'spec' . DIRECTORY_SEPARATOR . $className . '.php';

        $template = <<<EOF
<?php

namespace spec;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class $className extends ObjectBehavior
{
$string
}

EOF;

        $this->writeNewClassFile($file, $template);
    }

    /**
     * @Given /^the object being specified contains:$/
     */
    public function theObjectBeingSpecifiedContains(PyStringNode $string)
    {
        $uniqueToken = $this->uniqueTokenCounter;
        $className = 'Class' . $uniqueToken;
        $file = 'src' . DIRECTORY_SEPARATOR . $className . '.php';

        $template = <<<EOF
<?php
class $className
{
$string
}

EOF;

        $this->writeNewClassFile($file, $template);
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
     * @Then /^(?:|the )suite should pass$/
     * @Then /^(?:|the )example should pass$/
     */
    public function theSuiteShouldPass()
    {
        $stats = $this->getRunStats();

        expect($stats['examples'] > 0)->toBe(true);
        expect($stats['examples'])->toBe($stats['passed']);
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
            'broken' => isset($matches['broken']) ? (int) $matches['broken'] : 0,
            'failed' => isset($matches['failed']) ? (int) $matches['failed'] : 0,
        );
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
