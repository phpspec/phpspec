<?php

namespace integration\PhpSpec\Console\Prompter;

use PhpSpec\Console\Prompter\Factory;
use Symfony\Component\Console\Helper\HelperSet;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @var int
     */
    private $reportingLevel;

    protected function setUp()
    {
        $this->reportingLevel = error_reporting();
        error_reporting($this->reportingLevel & ~E_USER_DEPRECATED);

        $this->input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $this->output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
    }

    /**
     * Symfony <2.5 case
     *
     * @requires function \Symfony\Component\Console\Helper\DialogHelper::askConfirmation
     * @test
     */
    function it_provides_a_dialog_prompter_when_only_dialoghelper_is_registered()
    {
        $dialogHelper = $this->getMock('Symfony\Component\Console\Helper\DialogHelper');

        $helperSet = new HelperSet(array(
            'dialog' => $dialogHelper
        ));

        $factory = new Factory($this->input, $this->output, $helperSet);
        $prompter = $factory->getPrompter();

        $this->assertInstanceOf('PhpSpec\Console\Prompter\Dialog', $prompter);

        $dialogHelper->expects($this->once())->method('askConfirmation');;
        $prompter->askConfirmation('Are you sure?');
    }

    /**
     * Symfony >=3.0 case
     *
     * @requires function \Symfony\Component\Console\Helper\QuestionHelper::ask
     * @test
     */
    function it_provides_a_question_prompter_when_only_questionhelper_is_registered()
    {
        $questionHelper = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper');

        $helperSet = new HelperSet(array(
            'question' => $questionHelper
        ));

        $factory = new Factory($this->input, $this->output, $helperSet);
        $prompter = $factory->getPrompter();
        $this->assertInstanceOf('PhpSpec\Console\Prompter\Question', $prompter);

        $questionHelper->expects($this->once())->method('ask');
        $prompter->askConfirmation('Are you sure?');
    }

    /**
     * Symfony >=2.5 <3.0 case
     *
     * @requires function \Symfony\Component\Console\Helper\QuestionHelper::ask
     * @requires function \Symfony\Component\Console\Helper\DialogHelper::askConfirmation
     * @test
     */
    function it_provides_a_question_prompter_when_both_prompters_are_registered()
    {
        $dialogHelper = $this->getMock('Symfony\Component\Console\Helper\DialogHelper');
        $questionHelper = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper');

        $helperSet = new HelperSet(array(
            'dialog' => $dialogHelper,
            'question' => $questionHelper
        ));

        $factory = new Factory($this->input, $this->output, $helperSet);
        $prompter = $factory->getPrompter();
        $this->assertInstanceOf('PhpSpec\Console\Prompter\Question', $prompter);

        $questionHelper->expects($this->once())->method('ask');
        $prompter->askConfirmation('Are you sure?');
    }

    protected function tearDown()
    {
        error_reporting($this->reportingLevel);
    }
}
