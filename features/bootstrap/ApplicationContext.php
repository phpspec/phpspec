<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Fake\DialogHelper;
use Fake\ReRunner;
use PhpSpec\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Defines application features from the specific context.
 */
class ApplicationContext implements Context
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var ApplicationTester
     */
    private $tester;

    /**
     * @var DialogHelper
     */
    private $dialogHelper;

    /**
     * @var ReRunner
     */
    private $reRunner;

    /**
     * @beforeScenario
     */
    public function setupApplication()
    {
        $this->application = new Application('2.1-dev');
        $this->application->setAutoExit(false);

        $this->tester = new ApplicationTester($this->application);
    }

    /**
     * @beforeScenario
     */
    public function setupDialogHelper()
    {
        $this->dialogHelper = new DialogHelper();

        $helperSet = $this->application->getHelperSet();
        $helperSet->set($this->dialogHelper);
    }

    /**
     * @beforeScenario
     */
    public function setupReRunner()
    {
        $this->reRunner = new ReRunner;
        $this->application->getContainer()->set('process.rerunner.platformspecific', $this->reRunner);
    }

    /**
     * @Given I have started describing the :class class
     * @Given I start describing the :class class
     */
    public function iDescribeTheClass($class)
    {
        $this->tester->run(array(
            'command' => 'describe',
            '--no-interaction' => true,
            'class' => $class
        ));

        expect($this->tester->getStatusCode())->toBe(0);
    }

    /**
     * @When I run phpspec (non interactively)
     * @When I run phpspec using the :formatter format
     * @When I run phpspec with the :option option
     * @When /I run phpspec with option (?P<option>.*)/
     * @When /I run phpspec (?P<interactive>interactively)$/
     * @When /I run phpspec (?P<interactive>interactively) with the (?P<option>.*) option/
     */
    public function iRunPhpspec($formatter = null, $option = null, $interactive=null)
    {
        $arguments = array (
            'command' => 'run'
        );

        if ($formatter) {
            $arguments['--format'] = $formatter;
        }

        $this->addOptionToArguments($option, $arguments);

        $this->tester->run($arguments, array('interactive'=> (bool)$interactive));
    }

    /**
     * @When I run phpspec and answer :answer when asked if I want to generate the code
     * @When I run phpspec with the option :option and (I) answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecAndAnswerWhenAskedIfIWantToGenerateTheCode($answer, $option=null)
    {
        $arguments = array (
            'command' => 'run'
        );

        $this->addOptionToArguments($option, $arguments);

        $this->dialogHelper->setAnswer($answer=='y');

        $this->tester->run($arguments, array('interactive'=>true));
    }

    /**
     * @param string $option
     * @param array $arguments
     */
    private function addOptionToArguments($option, array &$arguments)
    {
        if ($option) {
            if (preg_match('/(?P<option>[a-z-]+)=(?P<value>[a-z.]+)/', $option, $matches)) {
                $arguments[$matches['option']] = $matches['value'];
            } else {
                $arguments['--' . trim($option, '"')] = true;
            }
        }
    }

    /**
     * @Then I should see :output
     * @Then I should see:
     */
    public function iShouldSee($output)
    {
        expect(strpos($this->tester->getDisplay(), (string)$output))->toNotBe(false);
    }

    /**
     * @Then I should be prompted for code generation
     */
    public function iShouldBePromptedForCodeGeneration()
    {
        expect($this->dialogHelper)->toHaveBeenAsked();
    }

    /**
     * @Then I should not be prompted for code generation
     */
    public function iShouldNotBePromptedForCodeGeneration()
    {
        expect($this->dialogHelper)->toNotHaveBeenAsked();
    }

    /**
     * @Then the suite should pass
     */
    public function theSuiteShouldPass()
    {
        expect($this->tester->getStatusCode())->toBe(0);
    }

    /**
     * @Then :number example(s) should have been skipped
     */
    public function exampleShouldHaveBeenSkipped($number)
    {
        expect(strpos($this->tester->getDisplay(), "($number skipped)"))->toNotBe(false);
    }

    /**
     * @Then :number example(s) should have been run
     */
    public function examplesShouldHaveBeenRun($number)
    {
        expect(strpos($this->tester->getDisplay(), "$number examples"))->toNotBe(false);
    }

    /**
     * @Then the exit code should be :code
     */
    public function theExitCodeShouldBe($code)
    {
        expect($this->tester->getStatusCode())->toBeLike($code);
    }

    /**
     * @Then I should see valid junit output
     */
    public function iShouldSeeValidJunitOutput()
    {
        $dom = new \DOMDocument();
        $dom->loadXML($this->tester->getDisplay());
        expect($dom->schemaValidate(__DIR__ . '/../../src/PhpSpec/Resources/schema/junit.xsd'))->toBe(true);
    }

    /**
     * @Then the tests should be rerun
     */
    public function theTestsShouldBeRerun()
    {
        expect($this->reRunner)->toHaveBeenRerun();
    }

    /**
     * @Then the tests should not be rerun
     */
    public function theTestsShouldNotBeRerun()
    {
        expect($this->reRunner)->toNotHaveBeenRerun();
    }
}
