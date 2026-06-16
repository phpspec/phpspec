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

use PhpSpec\StoryBDD\StoryBDDRegistry;

/**
 * Registers a Given step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional {string}/{int}/{word}/{*} placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function given(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::steps()->addStep($pattern, $fn);
}

/**
 * Registers a When step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function when(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::steps()->addStep($pattern, $fn);
}

/**
 * Registers a Then step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function then(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::steps()->addStep($pattern, $fn);
}

/**
 * Registers an And step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function step_and(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::steps()->addStep($pattern, $fn);
}

/**
 * Registers a But step definition with a pattern and closure.
 *
 * @param string $pattern step pattern with optional placeholders
 * @param Closure $fn closure to execute when the pattern matches; bound to StepWorld as $this
 * @return void
 */
function step_but(string $pattern, Closure $fn): void
{
    StoryBDDRegistry::steps()->addStep($pattern, $fn);
}

/**
 * Registers a hook to run before each feature.
 *
 * @param Closure $fn closure invoked before each feature executes
 * @return void
 */
function beforeFeature(Closure $fn): void
{
    StoryBDDRegistry::hooks()->addBeforeFeature($fn);
}

/**
 * Registers a hook to run before each scenario, bound to the StepWorld.
 *
 * @param Closure $fn closure invoked with StepWorld as $this before each scenario
 * @return void
 */
function beforeScenario(Closure $fn): void
{
    StoryBDDRegistry::hooks()->addBeforeScenario($fn);
}

/**
 * Registers a hook to run before each step, bound to the StepWorld.
 *
 * @param Closure $fn closure invoked with StepWorld as $this before each step
 * @return void
 */
function beforeStep(Closure $fn): void
{
    StoryBDDRegistry::hooks()->addBeforeStep($fn);
}
