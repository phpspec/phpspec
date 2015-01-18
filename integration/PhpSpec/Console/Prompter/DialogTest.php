<?php

namespace integration\PhpSpec\Console\Prompter;

use PhpSpec\Console\Prompter\Dialog;

/**
 * @requires function \Symfony\Component\Console\Helper\DialogHelper::askConfirmation
 */
class DialogTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @var \Symfony\Component\Console\Helper\DialogHelper
     */
    private $dialogHelper;

    /**
     * @var \PhpSpec\Console\Prompter
     */
    private $prompter;

    protected function setUp()
    {
        $this->output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
        $this->dialogHelper = $this->getMockBuilder('Symfony\Component\Console\Helper\DialogHelper')
                                   ->disableOriginalConstructor()->getMock();

        $this->prompter = new Dialog($this->output, $this->dialogHelper);
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
        $this->dialogHelper->expects($this->once())
                           ->method('askConfirmation')
                           ->with($this->identicalTo($this->output), 'Are you sure?', true)
                           ->willReturn(true);

        $result = $this->prompter->askConfirmation('Are you sure?');

        $this->assertEquals(true, $result);
    }

}
