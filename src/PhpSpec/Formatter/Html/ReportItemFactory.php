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

class ReportItemFactory
{
    public function __construct(
        private TemplateInterface $template
    )
    {
    }

    public function create(ExampleEvent $event, Presenter $presenter) : ReportFailedItem|ReportPassedItem|ReportPendingItem|ReportSkippedItem
    {
        switch ($result = $event->getResult()) {
            case ExampleEvent::PASSED:
                return new ReportPassedItem($this->template, $event);
            case ExampleEvent::PENDING:
                return new ReportPendingItem($this->template, $event);
            case ExampleEvent::SKIPPED:
                return new ReportSkippedItem($this->template, $event);
            case ExampleEvent::FAILED:
            case ExampleEvent::BROKEN:
                return new ReportFailedItem($this->template, $event, $presenter);
            default:
                throw new InvalidExampleResultException(
                    "Unrecognised example result $result"
                );
        }
    }
}
