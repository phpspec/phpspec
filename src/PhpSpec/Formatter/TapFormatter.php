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
use Symfony\Component\Yaml\Yaml;

class TapFormatter extends ConsoleFormatter
{

    const VERSION = 'TAP version 13';

    const OK = 'ok %d';

    const NOT_OK = 'not ok %d';

    const DESC = ' - %s: %s';

    const SKIP = ' # SKIP %s';

    const TODO = ' # TODO %s';

    const PLAN = '1..%d';

    const MESSAGE = "---\nmessage: %s\n...";

    const SEVERITY = "\n---\nseverity: %s\n...";

    const UNDEFINED_RESULT = -1;

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
            preg_replace('/^its? /', '', $event->getExample()->getTitle())
        );

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $result = sprintf(self::OK, $this->examplesCount) . $desc;
                break;
            case ExampleEvent::PENDING:
                $message = $this->getResultData($event, $event->getResult());
                $result = sprintf(self::NOT_OK, $this->examplesCount) . $desc . $message;
                break;
            case ExampleEvent::SKIPPED:
                $message = sprintf(self::SKIP, $this->getResultData($event));
                $result = sprintf(self::OK, $this->examplesCount) . $desc . $message;
                break;
            case ExampleEvent::BROKEN:
            case ExampleEvent::FAILED:
                $message = $this->getResultData($event, $event->getResult());
                $result = sprintf(self::NOT_OK, $this->examplesCount) . $desc . "\n" . $message;
                break;
            default:
                $message = $this->getResultData($event, self::UNDEFINED_RESULT);
                $result = sprintf(self::NOT_OK, $this->examplesCount) . $desc . "\n" . $message;
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
     * Format message as two-space indented YAML when needed outside of a
     * SKIP or TODO directive.
     *
     * @param ExampleEvent $event
     * @param int $result
     * @return string
     */
    private function getResultData(ExampleEvent $event, $result = null)
    {
        if (null === $result) {
            return $this->stripNewlines($event->getException()->getMessage());
        }
        switch ($result) {
            case ExampleEvent::PENDING:
                $message = sprintf(self::TODO, $this->stripNewlines($event->getException()->getMessage()))
                    . $this->indent(sprintf(self::SEVERITY, Yaml::dump('todo')));
                break;
            case self::UNDEFINED_RESULT:
                $message = 'The example result type was unknown to formatter';
                $message = $this->indent(
                    sprintf(
                        self::MESSAGE . self::SEVERITY,
                        Yaml::dump($message),
                        Yaml::dump('fail')
                    )
                );
                break;
            default:
                $message = $this->indent(
                    sprintf(
                        self::MESSAGE . self::SEVERITY,
                        Yaml::dump($event->getException()->getMessage()),
                        Yaml::dump('fail')
                    )
                );
        }
        return $message;
    }

    /**
     * @param string $string
     * @return string
     */
    private function stripNewlines($string)
    {
        return str_replace(array("\r\n", "\n", "\r"), ' / ', $string);
    }

    /**
     * @param string $string
     * @return string
     */
    private function indent($string)
    {
        return preg_replace(
            array("%(^[^\n\r])%", "%([\n\r]{1,2})%", "%\s+...[\n\r]{1,2}\s+---%"),
            array("  \\1", "\\1  ", ""),
            $string
        );
    }
}
