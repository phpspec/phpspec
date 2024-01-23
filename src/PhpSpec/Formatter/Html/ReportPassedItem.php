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
use PhpSpec\Formatter\Template as TemplateInterface;

class ReportPassedItem
{
    public function __construct(
        private TemplateInterface $template,
        private ExampleEvent $event
    )
    {
    }

    public function write(): void
    {
        $this->template->render(Template::DIR.'/Template/ReportPass.html', array(
            'title' => $this->event->getTitle()
        ));
    }
}
