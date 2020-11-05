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

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\IO\IO;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GithubFormatter implements EventSubscriberInterface
{
    /**
     * @var IO
     */
    private $io;

    /**
     * @var string
     */
    private $basePath;
    /**
     * @var array
     */
    private $errorEvents = [];

    public function __construct(IO $io, string $basePath)
    {
        $this->io = $io;
        $this->basePath = $basePath;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'afterExample' => 'logError',
            'afterSuite' => 'printErrors'
        ];
    }

    public function logError(ExampleEvent $event)
    {
        if ($event->getResult() !== ExampleEvent::FAILED
         && $event->getResult() !== ExampleEvent::BROKEN) {
            return;
        }

        $this->errorEvents[] = $event;
    }

    public function printErrors(SuiteEvent $suiteEvent)
    {
        if ($this->errorEvents) {
            $this->io->write("\n");
        }

        foreach ($this->errorEvents as $event) {
            $this->io->write(
                sprintf(
                    "::error file=%s,line=%d,col=1::%s: %s\n",
                    $this->getSpecFilename($event),
                    $event->getExample()->getLineNumber(),
                    $event->getResult() === ExampleEvent::FAILED ? 'Failed' : 'Broken',
                    $this->escapeMessage($event->getMessage())
                )
            );
        }
    }

    private function getSpecFilename(ExampleEvent $event)
    {
        $specFilename = $event->getSpecification()->getResource()->getSpecFilename();

        if (strpos($specFilename, $this->basePath) === 0) {
            $specFilename = ltrim(substr($specFilename, strlen($this->basePath)), '/');
        }

        return $specFilename;
    }

    private function escapeMessage(string $message) : string
    {
        return strtr($message, ["%" => "%25", "\r" => '%0D', "\n" => '%0A']);
    }
}
