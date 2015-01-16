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

namespace PhpSpec\Formatter;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;

class TapFormatter extends ConsoleFormatter
{

    const VERSION = 'TAP version 13';

    const OK = 'ok %d';

    const NOT_OK = 'not ok %d';

    const DESC = ' - %s: %s';

    const SKIP = ' # SKIP %s';

    const TODO = ' # TODO %s';

    const BROKEN = ' # BROKEN';

    const PLAN = '1..%d';

    /**
     * @var int
     */
    private $examplesCount = 0;

    /**
     * @var string
     */
    private $currentSpecificationTitle;

    /**
     * @param SuiteEvent $event
     */
    public function beforeSuite(SuiteEvent $event)
    {
        $this->getIO()->writeln(self::VERSION);
    }

    /**
     * @param SpecificationEvent $event
     */
    public function beforeSpecification(SpecificationEvent $event)
    {
        $this->currentSpecificationTitle = $event->getSpecification()->getTitle();
    }

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event)
    {
        $this->examplesCount++;
        $desc = sprintf(
            self::DESC,
            $this->currentSpecificationTitle,
            preg_replace('/^it /', '', $event->getExample()->getTitle())
        );

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $result = sprintf(self::OK, $this->examplesCount) . $desc;
                break;
            case ExampleEvent::PENDING:
                $todo = sprintf(self::TODO, $this->getExceptionMessage($event));
                $result = sprintf(self::OK, $this->examplesCount) . $desc . $todo;
                break;
            case ExampleEvent::SKIPPED:
                $skip = sprintf(self::SKIP, $this->getExceptionMessage($event));
                $result = sprintf(self::OK, $this->examplesCount) . $desc . $skip;
                break;
            case ExampleEvent::FAILED:
                $result = sprintf(self::NOT_OK, $this->examplesCount) . $desc;
                break;
            case ExampleEvent::BROKEN:
                $result = sprintf(self::NOT_OK, $this->examplesCount) . $desc . self::BROKEN;
                break;
        }

        $this->getIO()->writeln($result);
    }

    /**
     * @param SuiteEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
        $this->getIO()->writeln(sprintf(
            self::PLAN,
            $this->getStatisticsCollector()->getEventsCount()
        ));
    }

    /**
     * @param ExampleEvent $event
     * @return string
     */
    protected function getExceptionMessage(ExampleEvent $event, $depth = null)
    {
        if (null === $exception = $event->getException()) {
            return '';
        }

        return str_replace(array("\n", "\r"), " \\ ", $exception->getMessage());
    }

}
