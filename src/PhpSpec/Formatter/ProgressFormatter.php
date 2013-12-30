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
use PhpSpec\Event\ExampleEvent;

class ProgressFormatter extends BasicFormatter
{
    public function afterExample(ExampleEvent $event)
    {
        $io = $this->getIO();
        $stats = $this->getStatisticsCollector();

        $total  = $stats->getEventsCount();
        $counts = $stats->getCountsHash();

        $percents = array_map(function ($count) use ($total) {
            return round($count / ($total / 100), 0);
        }, $counts);
        $lengths  = array_map(function ($percent) {
            return round($percent / 2, 0);
        }, $percents);

        $size = 50;
        asort($lengths);
        $progress = array();
        foreach ($lengths as $status => $length) {
            $text   = $percents[$status].'%';
            $length = ($size - $length) >= 0 ? $length : $size;
            $size   = $size - $length;

            if ($io->isDecorated()) {
                if ($length > strlen($text) + 2) {
                    $text = str_pad($text, $length, ' ', STR_PAD_BOTH);
                } else {
                    $text = str_pad('', $length, ' ');
                }

                $progress[$status] = sprintf("<$status-bg>%s</$status-bg>", $text);
            } else {
                $progress[$status] = str_pad(
                    sprintf('%s: %s', $status, $text), 15, ' ', STR_PAD_BOTH
                );
            }
        }
        krsort($progress);

        $this->printException($event, 2);
        if ($io->isDecorated()) {
            $io->writeTemp(implode('', $progress).' '.$total);
        } else {
            $io->writeTemp('/'.implode('/', $progress).'/  '.$total.' examples');
        }
    }

    public function afterSuite(SuiteEvent $event)
    {
        $io = $this->getIO();
        $stats = $this->getStatisticsCollector();

        $io->freezeTemp();
        $io->writeln();

        $io->writeln(sprintf("%d specs", $stats->getTotalSpecs()));

        $counts = array();
        foreach ($stats->getCountsHash() as $type => $count) {
            if ($count) {
                $counts[] = sprintf('<%s>%d %s</%s>', $type, $count, $type, $type);
            }
        }
        $count = $stats->getEventsCount();
        $plural = $count !== 1 ? 's' : '';
        $io->write(sprintf("%d example%s ", $count, $plural));
        if (count($counts)) {
            $io->write(sprintf("(%s)", implode(', ', $counts)));
        }

        $io->writeln(sprintf("\n%sms", round($event->getTime() * 1000)));
    }
}
