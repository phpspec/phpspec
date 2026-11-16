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

namespace PhpSpec\Acceptance\Support;

use InvalidArgumentException;
use PhpSpec\Ai\Contracts\ProviderCatalogueInterface;
use PhpSpec\Ai\Contracts\ProviderInterface;

/**
 * The providers a scenario has installed when it says "Given a fake AI
 * provider": google alone, built as the fake provider whatever its settings.
 */
final class FakeProviderCatalogue implements ProviderCatalogueInterface
{
    public function installed(): array
    {
        return ['google' => 'papi-ai/google'];
    }

    public function create(string $provider, array $settings): ProviderInterface
    {
        if ($provider !== 'google') {
            throw new InvalidArgumentException(sprintf('Provider "%s" is not installed.', $provider));
        }

        return new FakeProvider();
    }
}
