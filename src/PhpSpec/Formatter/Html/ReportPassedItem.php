<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Template as TemplateInterface;

class ReportPassedItem
{
    private $template;
    private $event;

    public function __construct(TemplateInterface $template, ExampleEvent $event)
    {
        $this->template = $template;
        $this->event = $event;
    }

    public function write()
    {
        $this->template->render(Template::DIR . '/Template/ReportPass.html', array(
            'title' => $this->event->getTitle()
        ));
    }
}