<?php

namespace PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\Message\Example;

class FatalFormatter extends ConsoleFormatter
{
  private $io;

  public function __construct(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
  {
    parent::__construct($presenter, $io, $stats);
    $this->io = $io;
  }

  public function displayFatal(Example $message)
  {
    $this->io->writeln($message->getExampleMessage());
  }
}
