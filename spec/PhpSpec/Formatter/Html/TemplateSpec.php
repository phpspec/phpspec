<?php

namespace spec\PhpSpec\Formatter\Html;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TemplateSpec extends ObjectBehavior
{
    function it_renders_the_string_as_is()
    {
        $this->render('text')->shouldReturn('text');
    }

    function it_renders_a_variable()
    {
        $this->render('hello {name}', array('name' => 'Chuck Norris'))
           ->shouldReturn('hello Chuck Norris');
    }

    function it_works_for_many_instances_of_vars()
    {
        $this->render('{name}! {greeting}, {name}', array(
            'name' => 'Chuck',
            'greeting' => 'hello'
        ))->shouldReturn('Chuck! hello, Chuck');
    }

    function it_renders_a_file()
    {
        $tempFile = __DIR__ . "/_files/TemplateRenderFixture.tpl";
        mkdir(__DIR__ . "/_files");
        file_put_contents($tempFile, 'hello, {name}');

        $this->render($tempFile, array('name' => 'Chuck'))
            ->shouldReturn('hello, Chuck');
    }

    function letgo()
    {
        if (file_exists(__DIR__ . "/_files/TemplateRenderFixture.tpl")) {
            unlink(__DIR__ . "/_files/TemplateRenderFixture.tpl");
            rmdir(__DIR__ . "/_files");            
        }
    }
}
