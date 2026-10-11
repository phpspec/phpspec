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
use TypeError;

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
     * @param list<array{string, string, Closure|null}> $typed each parameter's name, its type as declared, and a closure taking that type alone, null when untyped
     */
    public function __construct(
        private string $class,
        private string $method,
        private int $required,
        private ?int $takes,
        private string $parameters,
        private array $typed = [],
    ) {}

    /**
     * @param array<int|string, mixed> $arguments the arguments the call was made with
     * @param bool $strict whether the file the call was made from declares strict types
     * @return list<mixed> the arguments as the real method would be given them, coerced where the calling file allows
     * @throws LogicException when a spec wrote a call that can never be made
     * @throws ArgumentCountError|TypeError when the code under spec made one
     */
    public function hold(array $arguments, string $file, int $line, bool $strict): array
    {
        $arguments = array_values($arguments);
        $written = ArrangingCode::includes($file);

        if ($written) {
            $this->holdWritten($arguments);
        } elseif (count($arguments) < $this->required) {
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

        return $this->typedAsTaken($arguments, $written, $strict, $file, $line);
    }

    /**
     * @param list<mixed> $arguments
     */
    private function holdWritten(array $arguments): void
    {
        $cetera = $this->ceteraIn($arguments);

        if ($cetera !== null && $cetera < count($arguments) - 1) {
            throw new LogicException(sprintf('cetera() stands for the rest of the arguments to %s(), so nothing can follow it.', $this->name()));
        }

        $written = $cetera ?? count($arguments);

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
     * Each argument held to its parameter's type the way PHP holds it, strictly
     * where the calling file declares strict types, else coerced; a matcher
     * stands for values and is held to nothing.
     *
     * @param list<mixed> $arguments
     * @return list<mixed>
     */
    private function typedAsTaken(array $arguments, bool $written, bool $strict, string $file, int $line): array
    {
        foreach ($arguments as $position => $argument) {
            $parameter = $this->typed[$position] ?? ($this->takes === null && $this->typed !== [] ? $this->typed[count($this->typed) - 1] : null);

            if ($parameter === null || $parameter[2] === null || $argument instanceof ArgumentMatcher) {
                continue;
            }

            try {
                $arguments[$position] = $strict ? (new StrictCall())($parameter[2], $argument) : ($parameter[2])($argument);
            } catch (TypeError) {
                $wrong = sprintf('%s(): Argument #%d ($%s) must be of type %s, %s given', $this->name(), $position + 1, $parameter[0], $parameter[1], get_debug_type($argument));

                if ($written) {
                    throw new LogicException($wrong . ', so a call written with it can never be made.');
                }

                throw new TypeError(sprintf('%s, called in %s on line %d', $wrong, ProjectRoot::here()->relative($file), $line));
            }
        }

        return $arguments;
    }


    /**
     * @param list<mixed> $arguments
     */
    private function ceteraIn(array $arguments): ?int
    {
        foreach ($arguments as $position => $argument) {
            if ($argument instanceof ArgumentMatcher && $argument->isCetera()) {
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
