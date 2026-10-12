<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Command\Run;

use Symfony\Component\Console\Input\InputInterface as Input;

/**
 * @internal
 * The values run's options were given, checked before anything runs. A value
 * PhpSpec cannot take would otherwise run as something else: a coverage
 * minimum of "abc" as 0%, an order it does not know as the default, an empty
 * report path as no report.
 */
final readonly class OptionValues
{
    /** What each option naming a place to read or write takes. */
    private const PLACES = [
        'coverage-html' => 'a directory to write to',
        'coverage-clover' => 'a file to write to',
        'coverage-json' => 'a file to write to',
        'coverage-src' => 'the source directory to cover',
    ];

    public function __construct(private Input $input) {}

    /**
     * What is wrong with the first value the run cannot take, or null when it
     * can take them all.
     */
    public function problem(): ?string
    {
        return $this->order()
            ?? $this->seed()
            ?? $this->profile()
            ?? $this->coverageMinimum()
            ?? $this->workers()
            ?? $this->filter()
            ?? $this->places()
            ?? $this->reportFiles();
    }

    /**
     * Whether the run shuffles: asked for by --order random, or by a --seed
     * given with no order named.
     */
    public function shuffles(): bool
    {
        $order = $this->input->getOption('order');

        return $order === 'random' || ($order === null && $this->input->getOption('seed') !== null);
    }

    private function order(): ?string
    {
        $order = $this->input->getOption('order');

        return $order === null || in_array($order, ['default', 'random'], true) ? null : $this->refused('--order', 'default or random', $order);
    }

    private function seed(): ?string
    {
        $seed = $this->input->getOption('seed');

        if (!is_string($seed)) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $seed) !== 1) {
            return $this->refused('--seed', 'a whole number', $seed);
        }

        return $this->input->getOption('order') === 'default' ? '--seed shuffles a random run, and --order default does not shuffle.' : null;
    }

    private function profile(): ?string
    {
        $profile = $this->input->getOption('profile');

        return !is_string($profile) || preg_match('/^[1-9]\d*$/', $profile) === 1 ? null : $this->refused('--profile', 'a number of examples, 1 or more', $profile);
    }

    private function coverageMinimum(): ?string
    {
        $minimum = $this->input->getOption('coverage-min');

        if (!is_string($minimum) || (is_numeric($minimum) && (float) $minimum >= 0 && (float) $minimum <= 100)) {
            return null;
        }

        return $this->refused('--coverage-min', 'a percentage from 0 to 100', $minimum);
    }

    /**
     * A --parallel value that is not a number is a path the option swallowed,
     * and runs as one; a number has to be a count of workers.
     */
    private function workers(): ?string
    {
        $parallel = $this->input->getOption('parallel');

        return is_string($parallel) && ctype_digit($parallel) && (int) $parallel < 1 ? $this->refused('--parallel', 'a number of workers, 1 or more', $parallel) : null;
    }

    private function filter(): ?string
    {
        $filter = $this->input->getOption('filter');

        return is_string($filter) && trim($filter) === '' ? $this->refused('--filter', 'the text to select by', $filter) : null;
    }

    private function places(): ?string
    {
        foreach (self::PLACES as $option => $takes) {
            $place = $this->input->getOption($option);

            if (is_string($place) && trim($place) === '') {
                return $this->refused('--' . $option, $takes, $place);
            }
        }

        return null;
    }

    private function reportFiles(): ?string
    {
        foreach ((array) $this->input->getOption('out') as $file) {
            if (is_string($file) && trim($file) === '') {
                return $this->refused('--out', 'a file to write the report to', $file);
            }
        }

        return null;
    }

    private function refused(string $option, string $takes, string $value): string
    {
        return sprintf('%s takes %s, not "%s".', $option, $takes, $value);
    }
}
