<?php

namespace integration\PhpSpec\Console\Prompter;

use PhpSpec\Console\Manager as ConsoleManager;
use PhpSpec\Console\Prompter\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @requires function \Symfony\Component\Console\Helper\QuestionHelper::ask
 */
class QuestionTest extends \PHPUnit_Framework_TestCase
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
     * @var \Symfony\Component\Console\Helper\QuestionHelper
     */
    private $questionHelper;

    /**
     * @var \PhpSpec\Console\Prompter
     */
    private $prompter;

    protected function setUp()
    {
        $this->input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $this->output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
        $this->questionHelper = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper');

        $consoleManager = new ConsoleManager();
        $consoleManager->setInput($this->input);
        $consoleManager->setOutput($this->output);
        $consoleManager->setQuestionHelper($this->questionHelper);

        $this->prompter = new Question($consoleManager);
    }

    /**
     * @test
     */
    function it_is_a_prompter()
    {
        $this->assertInstanceOf('PhpSpec\Console\Prompter', $this->prompter);
    }

    /**
     * @test
     */
    function it_can_ask_a_question_and_return_the_result()
    {
        $this->questionHelper->expects($this->once())
                           ->method('ask')
                           ->with(
                               $this->identicalTo($this->input),
                               $this->identicalTo($this->output),
                               $this->equalTo(new ConfirmationQuestion('Are you sure?', true))
                           )
                           ->willReturn(true);

        $result = $this->prompter->askConfirmation('Are you sure?');

        $this->assertEquals(true, $result);
    }

}
