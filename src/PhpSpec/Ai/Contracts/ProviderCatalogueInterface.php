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

namespace PhpSpec\Ai\Contracts;

use InvalidArgumentException;
use RuntimeException;

/**
 * The AI providers a project has installed, which phpspec builds by name.
 */
interface ProviderCatalogueInterface
{
    /**
     * @return array<string, string> each installed provider's name, and the package that ships it
     *
     * @throws InvalidArgumentException when an installed package cannot provide one
     */
    public function installed(): array;

    /**
     * @param array<string, mixed> $settings the provider's own settings, keyed as the configuration names them
     *
     * @throws InvalidArgumentException when the provider is not installed, or refuses its settings
     * @throws RuntimeException when no provider can be installed at all
     */
    public function create(string $provider, array $settings): ProviderInterface;
}
