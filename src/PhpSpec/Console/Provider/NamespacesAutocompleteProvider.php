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

namespace PhpSpec\Console\Provider;

use PhpSpec\Locator\ResourceLocator;
use PhpSpec\Locator\SrcPathLocator;
use PhpSpec\Util\Token;
use Symfony\Component\Finder\Finder;

final class NamespacesAutocompleteProvider
{
    private array $paths = [];

    public function __construct(
        private Finder $finder,
        array $locators
    )
    {
        foreach ($locators as $locator) {
            if ($locator instanceof SrcPathLocator) {
                $this->paths[] = $locator->getFullSrcPath();
            }
        }
    }

    /**
     * Get namespaces from paths.
     *
     * @return list<string>
     */
    public function getNamespaces() : array
    {
        $namespaces = [];
        foreach ($this->finder->files()->name('*.php')->in($this->paths) as $phpFile) {
            $tokens = Token::getAll($phpFile->getContents());
            foreach ($tokens as $index => $token) {
                if (!$token->hasType(T_NAMESPACE)) {
                    continue;
                }

                $shift = 2;
                $namespace = '';

                while (!$tokens[$index + $shift]->equals(';')) {
                    $namespace .= $tokens[$index + $shift]->asString();
                    $shift++;
                }

                $namespaceParts = explode('\\', $namespace);
                $namespace = '';

                foreach ($namespaceParts as $part) {
                    $namespace .= $part;
                    $namespace .= '\\';
                    $namespaces[] = $namespace;
                }

                break;
            }
        }

        return array_unique($namespaces);
    }
}
