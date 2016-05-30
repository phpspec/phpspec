<?php

namespace spec\PhpSpec\Console;

use PhpSpec\Console\Manager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ManagerSpec extends ObjectBehavior
{
    function it_can_provide_the_input(InputInterface $input)
    {
        $this->setInput($input);
        $this->getInput()->shouldBe($input);
    }

    function it_can_provide_the_output(OutputInterface $output)
    {
        $this->setOutput($output);
        $this->getOutput()->shouldBe($output);
    }

    function it_can_provide_the_question_helper(QuestionHelper $questionHelper)
    {
        $this->setQuestionHelper($questionHelper);
        $this->getQuestionHelper()->shouldBe($questionHelper);
    }
}
