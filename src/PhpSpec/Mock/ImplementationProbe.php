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

namespace PhpSpec\Mock;

use PhpSpec\Process\ChildPhp;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 * Whether PHP lets a plain class implement a built-in interface, which only a
 * class compiled against it can tell: an extension may refuse one, and that
 * refusal is a fatal no running php survives. So a throwaway class is compiled
 * in a child php, once per interface. The extensions bundled with PHP set no
 * such rule beyond the ones a double handles itself, so their interfaces are
 * taken without asking.
 */
final class ImplementationProbe
{
    public const KNOWN_EXTENSIONS = ['Core', 'standard', 'SPL', 'Reflection', 'date', 'dom', 'json', 'random', 'session'];

    private const PROBE = 'PhpSpecProbe';

    /** @var array<string, string|null> by interface */
    private array $refusals = [];

    /**
     * @param list<string> $knownExtensions the extensions whose interfaces are taken without asking
     */
    public function __construct(
        private readonly array $knownExtensions = self::KNOWN_EXTENSIONS,
        private readonly ChildPhp $php = new ChildPhp(),
    ) {}

    /**
     * Why PHP refuses a plain class implementing the interface, in PHP's words,
     * or null when it takes one.
     *
     * @param class-string $interface
     */
    public function refusal(string $interface): ?string
    {
        if (array_key_exists($interface, $this->refusals)) {
            return $this->refusals[$interface];
        }

        $reflection = new ReflectionClass($interface);
        $fatal = in_array($reflection->getExtensionName(), $this->knownExtensions, true) ? null : $this->php->fatalOf($this->implementing($reflection));

        return $this->refusals[$interface] = $fatal === null ? null : str_replace(' ' . self::PROBE, '', $fatal);
    }

    /**
     * @param ReflectionClass<object> $interface
     */
    private function implementing(ReflectionClass $interface): string
    {
        $methods = array_map(fn(ReflectionMethod $method): string => $this->method($method, $interface->getName()), $interface->getMethods());

        return sprintf('class %s implements \\%s { %s }', self::PROBE, $interface->getName(), implode(' ', $methods));
    }

    private function method(ReflectionMethod $method, string $interface): string
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = 'mixed ' . ($parameter->isPassedByReference() ? '&' : '') . ($parameter->isVariadic() ? '...$' . $parameter->getName() : '$' . $parameter->getName() . ' = null');
        }

        $returned = $method->getReturnType() ?? $method->getTentativeReturnType();
        $type = $returned === null ? '' : ': ' . preg_replace('/\bself\b/', '\\' . $interface, (string) $returned);

        return sprintf(
            'public %sfunction %s%s(%s)%s { throw new \\Exception(); }',
            $method->isStatic() ? 'static ' : '',
            $method->returnsReference() ? '&' : '',
            $method->getName(),
            implode(', ', $parameters),
            $type,
        );
    }
}
