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
    public function __construct(private TemplateInterface $template)
    {
    }

    public function create(ExampleEvent $event, Presenter $presenter): ReportFailedItem|ReportPassedItem|ReportPendingItem|ReportSkippedItem
    {
        return match ($result = $event->getResult()) {
            ExampleEvent::PASSED => new ReportPassedItem($this->template, $event),
            ExampleEvent::PENDING => new ReportPendingItem($this->template, $event),
            ExampleEvent::SKIPPED => new ReportSkippedItem($this->template, $event),
            ExampleEvent::FAILED, ExampleEvent::BROKEN => new ReportFailedItem($this->template, $event, $presenter),
            default => throw new InvalidExampleResultException(
                "Unrecognised example result $result"
            ),
        };
    }
}
