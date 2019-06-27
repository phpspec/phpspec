<?php

namespace spec\PhpSpec\Formatter\Html;

use PhpSpec\ObjectBehavior;

use Symfony\Component\Console\Input\InputInterface;

class HtmlIOSpec extends ObjectBehavior
{
    function let(InputInterface $input)
    {
        $this->beConstructedWith($input);
    }
}
