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

namespace PhpSpec\Ai\Papi;

use PapiAI\Core\Contracts\ProviderFactoryInterface;
use PapiAI\Core\Factory\ComposerDiscovery;
use PapiAI\Core\Factory\ProviderRegistry;
use PhpSpec\Ai\Contracts\ProviderCatalogueInterface;
use PhpSpec\Ai\Contracts\ProviderInterface;
use RuntimeException;

/**
 * @internal
 * The providers papi finds installed, built through papi's registry. The
 * registry is discovered the first time it is needed, and none is found
 * without papi itself.
 */
final class PapiCatalogue implements ProviderCatalogueInterface
{
    private ?ProviderRegistry $registry = null;

    public function installed(): array
    {
        $installed = [];

        foreach ($this->registry()?->installed() ?? [] as $factory) {
            $installed[$factory->name()] = $factory->package();
        }

        return $installed;
    }

    public function create(string $provider, array $settings): ProviderInterface
    {
        $registry = $this->registry() ?? throw new RuntimeException('AI features need papi-ai/papi-core, which is not installed.');

        return new PapiProvider($registry->create($provider, $settings));
    }

    private function registry(): ?ProviderRegistry
    {
        if (!interface_exists(ProviderFactoryInterface::class)) {
            return null;
        }

        return $this->registry ??= new ProviderRegistry(new ComposerDiscovery());
    }
}
