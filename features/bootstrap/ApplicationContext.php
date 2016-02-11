<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Fake\Prompter;
use Fake\ReRunner;
use Matcher\ApplicationOutputMatcher;
use Matcher\ValidJUnitXmlMatcher;
use PhpSpec\Console\Application;
use PhpSpec\Loader\StreamWrapper;
use PhpSpec\Matcher\MatchersProviderInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Defines application features from the specific context.
 */
class ApplicationContext implements Context, MatchersProviderInterface
{
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

        $this->tester = new ApplicationTester($this->application);

        $this->setupReRunner();
        $this->setupPrompter();
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

        expect($this->tester->run($arguments, array('interactive' => false)))->toBe(0);
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
        $arguments = array (
            'command' => 'run'
        );

        $this->addOptionToArguments($option, $arguments);

        $this->prompter->setAnswer($answer=='y');

        $this->lastExitCode = $this->tester->run($arguments, array('interactive' => true));
    }

    /**
     * @When I run phpspec and answer :answer to both questions
     */
    public function iRunPhpspecAndAnswerToBothQuestions($answer)
    {
        $arguments = array (
            'command' => 'run'
        );

        $this->prompter->setAnswer($answer=='y');
        $this->prompter->setAnswer($answer=='y');

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
        expect($this->tester)->toHaveOutput((string)$output);
    }

    /**
     * @Then I should be prompted for code generation
     */
    public function iShouldBePromptedForCodeGeneration()
    {
        expect($this->prompter)->toHaveBeenAsked();
    }

    /**
     * @Then I should not be prompted for code generation
     */
    public function iShouldNotBePromptedForCodeGeneration()
    {
        expect($this->prompter)->toNotHaveBeenAsked();
    }

    /**
     * @Then the suite should pass
     */
    public function theSuiteShouldPass()
    {
        expect($this->lastExitCode)->toBeLike(0);
    }

    /**
     * @Then the suite should not pass
     */
    public function theSuiteShouldNotPass()
    {
        expect($this->lastExitCode)->notToBeLike(0);
    }

    /**
     * @Then :number example(s) should have been skipped
     */
    public function exampleShouldHaveBeenSkipped($number)
    {
        expect($this->tester)->toHaveOutput("($number skipped)");
    }

    /**
     * @Then :number example(s) should have been run
     */
    public function examplesShouldHaveBeenRun($number)
    {
        expect($this->tester)->toHaveOutput("$number examples");
    }

    /**
     * @Then the exit code should be :code
     */
    public function theExitCodeShouldBe($code)
    {
        expect($this->lastExitCode)->toBeLike($code);
    }

    /**
     * @Then I should see valid junit output
     */
    public function iShouldSeeValidJunitOutput()
    {
        expect($this->tester)->toHaveOutputValidJunitXml();
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

    /**
     * @Then I should be prompted with:
     */
    public function iShouldBePromptedWith(PyStringNode $question)
    {
        expect($this->prompter)->toHaveBeenAsked((string)$question);
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

        expect($this->tester->run($arguments, array('interactive' => false)))->toBe(0);
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
     * Custom matchers
     *
     * @return array
     */
    public function getMatchers()
    {
        return array(
            new ApplicationOutputMatcher(),
            new ValidJUnitXmlMatcher()
        );
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
}
