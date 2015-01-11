<?php

namespace spec\PhpSpec\Console\Prompter;

use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Prophecy\Prophet;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FactorySpec extends ObjectBehavior
{
    function let(InputInterface $input, OutputInterface $output, HelperSet $helperSet)
    {
        $this->beConstructedWith($input, $output, $helperSet);
    }

    function it_returns_a_question_prompter_when_the_question_helper_is_available(HelperSet $helperSet)
    {
        $this->skipIfOldSymfonyVersion();

        $prophet = new Prophet();
        $questionHelper = $prophet->prophesize('Symfony\\Component\\Console\\Helper\\QuestionHelper');

        $helperSet->has('question')->willReturn(true);
        $helperSet->get('question')->willReturn($questionHelper);

        $this->getPrompter()->shouldHaveType('PhpSpec\\Console\\Prompter\\Question');
    }

    function it_returns_a_dialog_prompter_if_the_question_helper_is_unavailable(HelperSet $helperSet, DialogHelper $dialogHelper)
    {
        $helperSet->has('question')->willReturn(false);
        $helperSet->get('dialog')->willReturn($dialogHelper);

        $this->getPrompter()->shouldHaveType('PhpSpec\\Console\\Prompter\\Dialog');
    }

    /**
     * @throws SkippingException
     */
    protected function skipIfOldSymfonyVersion()
    {
        $questionHelperType = 'Symfony\\Component\\Console\\Helper\\QuestionHelper';
        if (!class_exists($questionHelperType)) {
            throw new SkippingException("Class '$questionHelperType'' does not exist in current Symfony version");
        }
    }
}
