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

namespace PhpSpec\Extensions;

/**
 * Base class for listener extensions. All event methods have empty defaults.
 * Override only the methods you need.
 */
abstract class ListenerExtension
{
    /**
     * Called before the suite begins running.
     *
     * @return void
     */
    public function beforeSuite(): void {}

    /**
     * Called after the entire suite has finished running.
     *
     * @return void
     */
    public function afterSuite(): void {}

    /**
     * Called before a specification file begins running.
     *
     * @param string $title The specification title or path
     *
     * @return void
     */
    public function beforeSpec(string $title): void {}

    /**
     * Called after a specification file has finished running.
     *
     * @param string $title The specification title
     *
     * @return void
     */
    public function afterSpec(string $title): void {}

    /**
     * Called before an individual example begins running.
     *
     * @param string $title The example title
     *
     * @return void
     */
    public function beforeExample(string $title): void {}

    /**
     * Called after an individual example has finished running, regardless of outcome.
     *
     * @param string $title    The example title
     * @param float  $duration The example execution time in seconds
     *
     * @return void
     */
    public function afterExample(string $title, float $duration): void {}

    /**
     * Called when an example passes.
     *
     * @param string $title The example title
     *
     * @return void
     */
    public function onPass(string $title): void {}

    /**
     * Called when an example fails.
     *
     * @param string $title   The example title
     * @param string $message The failure message
     *
     * @return void
     */
    public function onFail(string $title, string $message): void {}

    /**
     * Called when an example encounters an error.
     *
     * @param string $title   The example title
     * @param string $message The error message
     *
     * @return void
     */
    public function onError(string $title, string $message): void {}

    /**
     * Called when an example is marked as pending.
     *
     * @param string $title The example title
     *
     * @return void
     */
    public function onPending(string $title): void {}

    /**
     * Called when an example is skipped.
     *
     * @param string $title The example title
     *
     * @return void
     */
    public function onSkipped(string $title): void {}
}
