<?php

namespace spec\PhpSpec\Event;

use PhpSpec\ObjectBehavior;

class FileCreationEventSpec extends ObjectBehavior
{
    private $filepath = 'foo/bar.php';

    function let()
    {
        $this->beConstructedWith($this->filepath);
    }

    function it_should_be_a_symfony_event()
    {
        $this->shouldHaveType('PhpSpec\Event\BaseEvent');
    }

    function it_should_be_a_phpspec_event()
    {
        $this->shouldImplement('PhpSpec\Event\PhpSpecEvent');
    }

    function it_should_return_the_created_file_path()
    {
        $this->getFilePath()->shouldReturn($this->filepath);
    }
}
