<?php

namespace integration\PhpSpec\Console\Prompter;

use PhpSpec\Console\Prompter\Question;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @requires function \Symfony\Component\Console\Helper\QuestionHelper::ask
 */
class QuestionTest extends TestCase
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

    protected function setUp() : void
    {
        $this->input = $this->createMock('Symfony\Component\Console\Input\InputInterface');
        $this->output = $this->createMock('Symfony\Component\Console\Output\OutputInterface');
        $this->questionHelper = $this->createMock('Symfony\Component\Console\Helper\QuestionHelper');

        $this->prompter = new Question($this->input, $this->output, $this->questionHelper);
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
