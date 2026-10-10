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

namespace PhpSpec\Specification;

/**
 * @internal
 * A skip() or pending() raised in a hook that runs after what it meant to
 * leave out: the example, step or scenario already ran, so the signal can only
 * be reported as an error that says where to call it instead.
 */
final readonly class LateSignal
{
    public function __construct(
        private PendingException|SkippedException $signal,
        private string $hook,
        private string $ran,
        private string $instead,
    ) {}

    public function sentence(): string
    {
        return sprintf(
            '%s() in %s comes after %s ran; call it in %s.',
            $this->signal instanceof SkippedException ? 'skip' : 'pending',
            $this->hook,
            $this->ran,
            $this->instead,
        );
    }

    public function signal(): PendingException|SkippedException
    {
        return $this->signal;
    }
}
