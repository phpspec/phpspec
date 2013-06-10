<?php

namespace PhpSpec\Formatter;

use PhpSpec\Event\ExampleEvent;

class HtmlFormatter extends BasicFormatter
{
    const PASSED_TEMPLATE_OUTPUT = '          <dd class="example passed">%s</dd>';
    public function afterExample(ExampleEvent $event)
    {
        $io = $this->getIO();

        switch($event->getResult()) {
            case ExampleEvent::PASSED :
                $io->write(sprintf(static::PASSED_TEMPLATE_OUTPUT, $event->getTitle()));
                break;
        }
    }
}
