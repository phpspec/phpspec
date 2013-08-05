<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Presenter\PresenterInterface as Presenter;
use PhpSpec\Formatter\Template as TemplateInterface;

class ReportFailedItem
{
    private $template;
    private $event;
    static private $failingExamplesCount = 1;
    private $presenter;

    public function __construct(TemplateInterface $template, ExampleEvent $event, Presenter $presenter)
    {
        $this->template = $template;
        $this->event = $event;
        $this->presenter = $presenter;
    }

    public function write($index)
    {
        $code = $this->presenter->presentException($this->event->getException(), true);
        $this->template->render(Template::DIR . '/Template/ReportFailed.html',
            array(
                'title' => htmlentities(strip_tags($this->event->getTitle())),
                'message' => htmlentities(strip_tags($this->event->getMessage())),
                'backtrace' => $this->formatBacktrace(),
                'code' => $code,
                'index' => self::$failingExamplesCount++,
                'specification' => $index
            )
        );
    }

    private function formatBacktrace()
    {
        $backtrace = '';
        foreach ($this->event->getBacktrace() as $step) {
            if (isset($step['line']) && isset($step['file'])) {
                $backtrace .= "#{$step['line']} {$step['file']}";
                $backtrace .= "<br />";
                $backtrace .= PHP_EOL;
            }
        }
        return rtrim($backtrace, "<br />" . PHP_EOL);
    }
}