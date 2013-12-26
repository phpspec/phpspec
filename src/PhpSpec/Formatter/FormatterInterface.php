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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\IO\IOInterface as IO;
use PhpSpec\Formatter\Presenter\PresenterInterface as Presenter;
use PhpSpec\Listener\StatisticsCollector;

/**
 * Interface FormatterInterface
 * @package PhpSpec\Formatter
 */
interface FormatterInterface extends EventSubscriberInterface
{
    /**
     * @param IO $io
     * @return mixed
     */
    public function setIO(IO $io);

    /**
     * @param Presenter $presenter
     * @return mixed
     */
    public function setPresenter(Presenter $presenter);

    /**
     * @param StatisticsCollector $stats
     * @return mixed
     */
    public function setStatisticsCollector(StatisticsCollector $stats);
}
