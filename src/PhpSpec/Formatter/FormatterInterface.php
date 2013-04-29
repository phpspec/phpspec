<?php

namespace PhpSpec\Formatter;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\Console\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface as Presenter;
use PhpSpec\Listener\StatisticsCollector;

interface FormatterInterface extends EventSubscriberInterface
{
    public function setIO(IO $io);
    public function setPresenter(Presenter $presenter);
    public function setStatisticsCollector(StatisticsCollector $stats);
}
