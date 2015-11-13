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

use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\PendingException;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\Message\CurrentExampleTracker;

class ConsoleFormatter extends BasicFormatter implements FatalPresenter
{
    /**
     * @var IO
     */
    private $io;

    /**
     * @param PresenterInterface  $presenter
     * @param IO                  $io
     * @param StatisticsCollector $stats
     */
    public function __construct(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        parent::__construct($presenter, $io, $stats);
        $this->io = $io;
    }

    /**
     * @return IO
     */
    protected function getIO()
    {
        return $this->io;
    }

    /**
     * @param ExampleEvent $event
     */
    protected function printException(ExampleEvent $event)
    {
        if (null === $exception = $event->getException()) {
            return;
        }

        if ($exception instanceof PendingException) {
            $this->printSpecificException($event, 'pending');
        } elseif ($exception instanceof SkippingException) {
            if ($this->io->isVerbose()) {
                $this->printSpecificException($event, 'skipped ');
            }
        } elseif (ExampleEvent::FAILED === $event->getResult()) {
            $this->printSpecificException($event, 'failed');
        } else {
            $this->printSpecificException($event, 'broken');
        }
    }

    /**
     * @param ExampleEvent $event
     * @param string $type
     */
    protected function printSpecificException(ExampleEvent $event, $type)
    {
        $title = str_replace('\\', DIRECTORY_SEPARATOR, $event->getSpecification()->getTitle());
        $message = $this->getPresenter()->presentException($event->getException(), $this->io->isVerbose());

        foreach (explode("\n", wordwrap($title, $this->io->getBlockWidth(), "\n", true)) as $line) {
            $this->io->writeln(sprintf('<%s-bg>%s</%s-bg>', $type, str_pad($line, $this->io->getBlockWidth()), $type));
        }

        $this->io->writeln(sprintf(
            '<lineno>%4d</lineno>  <%s>- %s</%s>',
            $event->getExample()->getFunctionReflection()->getStartLine(),
            $type,
            $event->getExample()->getTitle(),
            $type
        ));
        $this->io->writeln(sprintf('<%s>%s</%s>', $type, lcfirst($message), $type), 6);
        $this->io->writeln();
    }

    public function displayFatal(CurrentExampleTracker $currentExample, $error)
    {
        if (
            (null !== $error && $currentExample->getCurrentExample()) ||
            (is_null($currentExample->getCurrentExample()) && defined('HHVM_VERSION'))
        ) {
            ini_set('display_errors', "stderr");
            $failedOpen = ($this->io->isDecorated()) ? '<failed>' : '';
            $failedClosed = ($this->io->isDecorated()) ? '</failed>' : '';
            $failedCross = ($this->io->isDecorated()) ? '✘' : '';

            $this->io->writeln("$failedOpen$failedCross Fatal error happened while executing the following $failedClosed");
            $this->io->writeln("$failedOpen    {$currentExample->getCurrentExample()} $failedClosed");
            $this->io->writeln("$failedOpen    {$error['message']} in {$error['file']} on line {$error['line']} $failedClosed");
        }
    }
}
