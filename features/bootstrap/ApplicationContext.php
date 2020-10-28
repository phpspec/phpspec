<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Fake\Prompter;
use Fake\ReRunner;
use PhpSpec\Console\Application;
use PhpSpec\Loader\StreamWrapper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Defines application features from the specific context.
 */
class ApplicationContext implements Context
{
    const JUNIT_XSD_PATH = '/src/PhpSpec/Resources/schema/junit.xsd';

    /**
     * @var Application
     */
    private $application;

    /**
     * @var integer
     */
    private $lastExitCode;

    /**
     * @var ApplicationTester
     */
    private $tester;

    /**
     * @var Prompter
     */
    private $prompter;

    /**
     * @var ReRunner
     */
    private $reRunner;

    /**
     * @beforeScenario
     */
    public function setupApplication()
    {
        StreamWrapper::register();

        $this->application = new Application('2.1-dev');
        $this->application->setAutoExit(false);
        $this->setFixedTerminalDimensions();

        $this->tester = new ApplicationTester($this->application);

        $this->setupReRunner();
        $this->setupPrompter();
        $this->resetShellVerbosity();
    }

    private function setFixedTerminalDimensions()
    {
        putenv('COLUMNS=130');
        putenv('LINES=30');
    }

    private function setupPrompter()
    {
        $this->prompter = new Prompter();

        $this->application->getContainer()->set('console.prompter', $this->prompter);
    }

    private function setupReRunner()
    {
        $this->reRunner = new ReRunner;
        $this->application->getContainer()->set('process.rerunner.platformspecific', $this->reRunner);
    }

    private function resetShellVerbosity()
    {
        putenv('SHELL_INTERACTIVE=1');
        putenv(sprintf('SHELL_VERBOSITY=%d', OutputInterface::VERBOSITY_NORMAL));
    }

    /**
     * @Given I have started describing the :class class
     * @Given I start describing the :class class
     */
    public function iDescribeTheClass($class)
    {
        $arguments = array(
            'command' => 'describe',
            'class' => $class
        );

        if ($this->tester->run($arguments, array('interactive' => false)) !== 0) {
            throw new \Exception('Test runner exited with an error');
        }
    }

    /**
     * @When I run phpspec (non interactively)
     * @When I run phpspec using the :formatter format
     * @When I run phpspec with the :option option
     * @When I run phpspec with :spec specs to run
     * @When /I run phpspec with option (?P<option>.*)/
     * @When /I run phpspec (?P<interactive>interactively)$/
     * @When /I run phpspec (?P<interactive>interactively) with the (?P<option>.*) option/
     */
    public function iRunPhpspec($formatter = null, $option = null, $interactive = null, $spec = null)
    {
        $arguments = array (
            'command' => 'run',
            'spec' => $spec
        );

        if ($formatter) {
            $arguments['--format'] = $formatter;
        }

        $this->addOptionToArguments($option, $arguments);

        $this->lastExitCode = $this->tester->run($arguments, array(
            'interactive' => (bool)$interactive,
            'decorated' => false,
        ));
    }

    /**
     * @When I run phpspec and answer :answer when asked if I want to generate the code
     * @When I run phpspec with the option :option and (I) answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecAndAnswerWhenAskedIfIWantToGenerateTheCode($answer, $option=null)
    {
        $this->runPhpSpecAndAnswerQuestions($answer, 1, $option);
    }

    /**
     * @When I run phpspec and answer :answer to (the) :amount questions
     */
    public function iRunPhpspecAndAnswerToBothQuestions($amount, $answer)
    {
        $this->runPhpSpecAndAnswerQuestions($answer, ($amount === 'both' ? 2 : 3));
    }

    /**
     * @param string  $answer
     * @param integer $times
     * @param string  $option
     */
    private function runPhpSpecAndAnswerQuestions($answer, $times, $option = null)
    {
        $arguments = array (
            'command' => 'run'
        );

        $this->addOptionToArguments($option, $arguments);

        $i = 0;
        while ($i++ < $times) {
            $this->prompter->setAnswer($answer=='y');
        }

        $this->lastExitCode = $this->tester->run($arguments, array('interactive' => true));
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
        $this->checkApplicationOutput((string)$output);
    }

    /**
     * @Then I should be prompted for code generation
     */
    public function iShouldBePromptedForCodeGeneration()
    {
        if(!$this->prompter->hasBeenAsked()) {
            throw new \Exception('There was a missing prompt for code generation');
        }
    }

    /**
     * @Then I should see the error that :methodCall was not expected on :class
     */
    public function iShouldSeeTheErrorThatWasNotExpectedOn($methodCall, $class)
    {
        $this->checkApplicationOutput((string)$methodCall);
        $this->checkApplicationOutput((string)$this->normalize($class));

        $output = $this->tester->getDisplay();

        $containsOldProphecyMessage = strpos($output, 'was not expected') !== false;
        $containsNewProphecyMessage = strpos($output, 'Unexpected method call') !== false;

        if (!$containsOldProphecyMessage && !$containsNewProphecyMessage) {
            throw new \Exception('Was expecting error message about an unexpected method call');
        }
    }

    /**
     * @Then I should not be prompted for code generation
     */
    public function iShouldNotBePromptedForCodeGeneration()
    {
        if($this->prompter->hasBeenAsked()) {
            throw new \Exception('There was an unexpected prompt for code generation');
        }
    }

    /**
     * @Then the suite should pass
     */
    public function theSuiteShouldPass()
    {
        $this->theExitCodeShouldBe(0);
    }

    /**
     * @Then the suite should not pass
     */
    public function theSuiteShouldNotPass()
    {
        if ($this->lastExitCode === 0) {
            throw new \Exception('The application did not exit with an error code');
        }
    }

    /**
     * @Then :number example(s) should have been skipped
     */
    public function exampleShouldHaveBeenSkipped($number)
    {
        $this->checkApplicationOutput("($number skipped)");
    }

    /**
     * @Then :number example(s) should have been run
     */
    public function examplesShouldHaveBeenRun($number)
    {
        $this->checkApplicationOutput("$number examples");
    }

    /**
     * @Then the exit code should be :code
     */
    public function theExitCodeShouldBe($code)
    {
        if ($this->lastExitCode !== (int)$code) {
            throw new \Exception(sprintf(
                'The application existed with an unexpected code: expected: %s, actual: %s',
                $code,
                $this->lastExitCode
            ));
        }
    }

    /**
     * @Then I should see valid junit output
     */
    public function iShouldSeeValidJunitOutput()
    {
        $dom = new \DOMDocument();
        $dom->loadXML($this->tester->getDisplay());
        if (!$dom->schemaValidate(__DIR__ . '/../..' . self::JUNIT_XSD_PATH)) {
            throw new \Exception(sprintf(
                "Output was not valid JUnit XML"
            ));
        }
    }

    /**
     * @Then the tests should be rerun
     */
    public function theTestsShouldBeRerun()
    {
        if (!$this->reRunner->hasBeenReRun()) {
            throw new \Exception('The tests should have been rerun');
        }
    }

    /**
     * @Then the tests should not be rerun
     */
    public function theTestsShouldNotBeRerun()
    {
        if ($this->reRunner->hasBeenReRun()) {
            throw new \Exception('The tests should not have been rerun');
        }
    }

    /**
     * @Then I should be prompted with:
     */
    public function iShouldBePromptedWith(PyStringNode $question)
    {
        $stringQuestion = (string)$question;
        if (!$this->prompter->hasBeenAsked($stringQuestion)) {
            throw new \Exception("The prompt was not shown: $stringQuestion");
        }
    }

    /**
     * @Given I have started describing the :class class with the :config (custom) config
     * @Given I start describing the :class class with the :config (custom) config
     */
    public function iDescribeTheClassWithTheConfig($class, $config)
    {
        $arguments = array(
            'command' => 'describe',
            'class' => $class,
            '--config' => $config
        );

        if ($this->tester->run($arguments, array('interactive' => false)) !== 0) {
            throw new \Exception('Test runner exited with an error');
        }
    }

    /**
     * @Given there is a PSR-:namespaceType namespace :namespace configured for the :source folder
     */
    public function thereIsAPsrNamespaceConfiguredForTheFolder($namespaceType, $namespace, $source)
    {
        if (!is_dir(__DIR__ . '/src')) {
            mkdir(__DIR__ . '/src');
        }
        require_once __DIR__ .'/autoloader/fake_autoload.php';
    }


    /**
     * @When I run phpspec with the :config (custom) config and answer :answer when asked if I want to generate the code
     */
    public function iRunPhpspecWithConfigAndAnswerIfIWantToGenerateTheCode($config, $answer)
    {
        $arguments = array (
            'command' => 'run',
            '--config' => $config
        );

        $this->prompter->setAnswer($answer=='y');

        $this->lastExitCode = $this->tester->run($arguments, array('interactive' => true));
    }

    /**
     * @When I run phpspec with the spec :spec
     */
    public function iRunPhpspecWithTheSpec($spec)
    {
        $arguments = array (
            'command' => 'run',
            1 => $spec
        );

        $this->lastExitCode = $this->tester->run($arguments, array('interactive' => false));
    }

    /**
     * @When I run phpspec with the spec :spec and the config :config
     */
    public function iRunPhpspecWithTheSpecAndTheConfig($spec, $config)
    {
        $arguments = array (
            'command' => 'run',
            1 => $spec,
            '--config' => $config
        );

        $this->lastExitCode = $this->tester->run($arguments, array('interactive' => false));
    }

    private function checkApplicationOutput($output)
    {
        $expected = $this->normalize($output);
        $actual = $this->normalize($this->tester->getDisplay(true));
        if (strpos($actual, $expected) === false) {
            throw new \Exception(sprintf(
                "Application output did not contain expected '%s'. Actual output:\n'%s'" ,
                $expected,
                $this->tester->getDisplay()
            ));
        }
    }

    private function normalize($string)
    {
        $string = preg_replace('/\([0-9]+ms\)/', '', $string);
        $string = str_replace("\r", '', $string);
        $string = preg_replace('#(Double\\\\.+?\\\\P)\d+#u', '$1', $string);
        $string = preg_replace('/\(\s+("[^"]*")\s+\)/', '($1)', $string);

        return $string;
    }

    /**
     * @Then I should not be prompted for more questions
     */
    public function iShouldNotBePromptedForMoreQuestions()
    {
        if ($this->prompter->hasUnansweredQuestions()) {
            throw new \Exception(
                'Not all questions were answered. This might lead into further code generation not reflected in the scenario.'
            );
        }
    }

    /**
     * @Then I should an error about invalid class name :className to generate spec for
     */
    public function iShouldAnErrorAboutImpossibleSpecGenerationForClass($className)
    {
        $this->checkApplicationOutput("I cannot generate spec for '$className' because class");
        $this->checkApplicationOutput('name contains reserved keyword');
    }

    /**
     * @Then The output should contain:
     */
    public function outputShouldContain(PyStringNode $expectedOutputPart)
    {
        $this->checkApplicationOutput("$expectedOutputPart");
    }

    /**
     * @Then Output should not be shown
     */
    public function outputShouldNotBeShown()
    {
        $outputLen = strlen($this->normalize($this->tester->getDisplay(true)));
        if ($outputLen) {
            throw new \Exception(
                'Output was shown when not expected.'
            );
        }
    }

    /**
     * @Then The output should not contain:
     */
    public function outputShouldNotContain(PyStringNode $expectedOutputPart)
    {
        $expected = $this->normalize($expectedOutputPart);
        $actual = $this->normalize($this->tester->getDisplay(true));
        if (strpos($actual, $expected) !== false) {
            throw new \Exception(sprintf(
                "Application output did contain not expected '%s'. Actual output:\n'%s'" ,
                $expected,
                $this->tester->getDisplay()
            ));
        }
    }
}
