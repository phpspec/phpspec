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

use ArgumentCountError;
use Closure;
use LogicException;
use PhpSpec\ProjectRoot;

/**
 * @internal
 * What a doubled method takes, written into the double's method, so a call
 * no object of the real class could take is not answered: written in a spec,
 * it is refused with what to write instead; made by the code under spec, it
 * fails as PHP fails it on the real object.
 */
final readonly class Signature
{
    private const REMEDY = 'Write the arguments it is called with, any() for one whose value does not matter, or cetera() for the rest.';

    /**
     * @param string $class the doubled class or interface
     * @param int $required how many arguments the method requires
     * @param int|null $takes how many it takes at most, null when it is variadic
     * @param string $parameters the parameter list, as declared
     */
    public function __construct(
        private string $class,
        private string $method,
        private int $required,
        private ?int $takes,
        private string $parameters,
    ) {}

    /**
     * @param array<int|string, mixed> $arguments the arguments the call was made with
     * @throws LogicException when a spec wrote a call that can never be made
     * @throws ArgumentCountError when the code under spec made one
     */
    public function hold(array $arguments, string $file, int $line): void
    {
        if (ArrangingCode::includes($file)) {
            $this->holdWritten(array_values($arguments));

            return;
        }

        if (count($arguments) < $this->required) {
            throw new ArgumentCountError(sprintf(
                'Too few arguments to function %s(), %d passed in %s on line %d and %s %d expected',
                $this->name(),
                count($arguments),
                ProjectRoot::here()->relative($file),
                $line,
                $this->required === $this->takes ? 'exactly' : 'at least',
                $this->required,
            ));
        }
    }

    /**
     * @param list<mixed> $arguments
     */
    private function holdWritten(array $arguments): void
    {
        $cetera = $this->positionOf($arguments, static fn(ArgumentMatcher $matcher): bool => $matcher->isCetera());
        $noArgs = $this->positionOf($arguments, static fn(ArgumentMatcher $matcher): bool => $matcher->isNoArgs());

        if ($cetera !== null && $cetera < count($arguments) - 1) {
            throw new LogicException(sprintf('cetera() stands for the rest of the arguments to %s(), so nothing can follow it.', $this->name()));
        }

        if ($noArgs !== null && count($arguments) > 1) {
            throw new LogicException(sprintf('noArgs() stands for a call to %s() with no arguments, so nothing can go beside it.', $this->name()));
        }

        $written = $noArgs !== null ? 0 : $cetera ?? count($arguments);

        if ($cetera === null && $written < $this->required) {
            throw new LogicException(sprintf(
                '%s() takes %s%s (%s), so a call written with %s can never be made. %s',
                $this->name(),
                $this->required === $this->takes ? '' : 'at least ',
                $this->arguments($this->required),
                $this->parameters,
                $written === 0 ? 'none' : $written,
                self::REMEDY,
            ));
        }

        if ($this->takes !== null && $written > $this->takes) {
            throw new LogicException(sprintf('%s() takes at most %s (%s); the call is written with %d.', $this->name(), $this->arguments($this->takes), $this->parameters, $written));
        }
    }

    /**
     * @param list<mixed> $arguments
     * @param Closure(ArgumentMatcher): bool $is
     */
    private function positionOf(array $arguments, Closure $is): ?int
    {
        foreach ($arguments as $position => $argument) {
            if ($argument instanceof ArgumentMatcher && $is($argument)) {
                return $position;
            }
        }

        return null;
    }

    private function arguments(int $count): string
    {
        return $count === 1 ? '1 argument' : $count . ' arguments';
    }

    private function name(): string
    {
        return ltrim($this->class, '\\') . '::' . $this->method;
    }
}
