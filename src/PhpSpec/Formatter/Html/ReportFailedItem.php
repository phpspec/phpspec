<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Formatter\Template as TemplateInterface;

class ReportFailedItem
{
    private static int $failingExamplesCount = 1;

    public function __construct(
        private TemplateInterface $template,
        private ExampleEvent $event,
        private Presenter $presenter)
    {
    }

    public function write(int $index): void
    {
        $code = $this->presenter->presentException($this->event->getException(), true);
        $this->template->render(
            Template::DIR.'/Template/ReportFailed.html',
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

    private function formatBacktrace() : string
    {
        $backtrace = '';
        foreach ($this->event->getBacktrace() as $step) {
            if (isset($step['line']) && isset($step['file'])) {
                $backtrace .= "#{$step['line']} {$step['file']}";
                $backtrace .= "<br />";
                $backtrace .= PHP_EOL;
            }
        }

        return rtrim($backtrace, "<br />".PHP_EOL);
    }
}
