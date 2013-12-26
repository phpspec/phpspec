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
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Formatter\Template as TemplateInterface;

/**
 * Class ReportItemFactory
 * @package PhpSpec\Formatter\Html
 */
class ReportItemFactory
{
    /**
     * @var Template
     */
    private $template;

    /**
     * @param TemplateInterface $template
     */
    public function __construct(TemplateInterface $template)
    {
        $this->template = $template ?: new Template;
    }

    /**
     * @param ExampleEvent $event
     * @param PresenterInterface $presenter
     * @return ReportFailedItem|ReportPassedItem|ReportPendingItem
     * @throws void
     */
    public function create(ExampleEvent $event, PresenterInterface $presenter = null)
    {
        switch($event->getResult()) {
            case ExampleEvent::PASSED:
                return new ReportPassedItem($this->template, $event);
            case ExampleEvent::PENDING:
                return new ReportPendingItem($this->template, $event);
            case ExampleEvent::FAILED:
            case ExampleEvent::BROKEN:
                return new ReportFailedItem($this->template, $event, $presenter);
            default:
                throw $this->invalidResultException($event->getResult());
        }
    }

    /**
     * @param $result
     * @throws InvalidExampleResultException
     */
    private function invalidResultException($result)
    {
        throw new InvalidExampleResultException(
            "Unrecognised example result $result"
        );
    }
}