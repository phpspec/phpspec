<?php

namespace spec\PhpSpec\Console\Prompter;

use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Prophecy\Prophet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class QuestionSpec extends ObjectBehavior
{
    /**
     * @var QuestionHelper
     */
    private $questionHelper;

    function let(InputInterface $input, OutputInterface $output)
    {
        $questionHelperType = 'Symfony\\Component\\Console\\Helper\\QuestionHelper';
        if (!class_exists($questionHelperType)) {
            throw new SkippingException("Class '$questionHelperType'' does not exist in current Symfony version");
        }

        $prophet = new Prophet();
        $this->questionHelper = $prophet->prophesize('Symfony\\Component\\Console\\Helper\\QuestionHelper');

        $this->beConstructedWith($input, $output, $this->questionHelper);
    }

    function it_is_a_prompter()
    {
        $this->shouldHaveType('PhpSpec\Console\Prompter');
    }

    function it_can_ask_for_confirmation_and_return_result(
        InputInterface $input, OutputInterface $output
    )
    {
        $this->questionHelper->ask(Argument::cetera())->willReturn(true);

        $result = $this->askConfirmation('Are you sure?');

        $this->questionHelper->ask($input->getWrappedObject(), $output->getWrappedObject(), Argument::that(
            function(ConfirmationQuestion $question) {
                return $question->getQuestion() == 'Are you sure?';
            }
        ))->shouldHaveBeenCalled();
        $result->shouldBe(true);
    }
}
