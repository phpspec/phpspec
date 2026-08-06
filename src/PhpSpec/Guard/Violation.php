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

namespace PhpSpec\Guard;

/**
 * @internal
 * One piece of logic this session wrote and no example reaches.
 *
 * Named by its member where the source says which one it is, because
 * "App\Basket::applyCoupon is untested" is something a reader can act on and
 * "src/App/Basket.php lines 34 to 37" is something they have to go and look up.
 */
final readonly class Violation
{
    /**
     * @param string $file the file, project-relative
     * @param list<int> $lines the untested lines it added
     * @param string|null $member the member they sit in, when the source names one
     */
    public function __construct(
        public string $file,
        public array $lines,
        public ?string $member = null,
    ) {}

    /**
     * What to do about it, in the words of the cycle it broke.
     */
    public function remedy(): string
    {
        return $this->member !== null
            ? sprintf('Write an example for %s, then make it pass.', $this->member)
            : sprintf('Write an example that reaches %s, then make it pass.', $this->file);
    }

    /**
     * What went wrong, as one sentence.
     */
    public function summary(): string
    {
        return $this->member !== null
            ? sprintf('New logic in %s is untested.', $this->member)
            : sprintf('New logic in %s is untested.', $this->at());
    }

    /**
     * Where it is, as a location a reader or an editor can open.
     */
    public function at(): string
    {
        return $this->file . ':' . ($this->lines[0] ?? 0);
    }

    /**
     * @return array{file: string, lines: list<int>, member: string|null, remedy: string}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'lines' => $this->lines,
            'member' => $this->member,
            'remedy' => $this->remedy(),
        ];
    }
}
