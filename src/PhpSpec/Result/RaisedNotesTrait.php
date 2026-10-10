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

namespace PhpSpec\Result;

/**
 * @internal
 * The PHP warnings, deprecations and notices an example or a step raised while
 * it ran, each kept under its kind.
 */
trait RaisedNotesTrait
{
    /** @var list<array{severity: int, message: string, file: string, line: int}> */
    private array $warnings = [];

    /** @var list<array{severity: int, message: string, file: string, line: int}> */
    private array $deprecations = [];

    /** @var list<array{severity: int, message: string, file: string, line: int}> */
    private array $notices = [];

    /**
     * Files each note raised while this ran under its kind, once however often
     * it was raised.
     *
     * @param list<array{severity: int, message: string, file: string, line: int}> $notes
     */
    public function raised(array $notes): void
    {
        $unique = [];
        foreach ($notes as $note) {
            $unique[$note['message'] . ':' . $note['file'] . ':' . $note['line']] = $note;
        }

        $this->warnings = $this->ofSeverity($unique, E_WARNING, E_USER_WARNING);
        $this->deprecations = $this->ofSeverity($unique, E_DEPRECATED, E_USER_DEPRECATED);
        $this->notices = $this->ofSeverity($unique, E_NOTICE, E_USER_NOTICE);
    }

    /**
     * @param list<array{severity: int, message: string, file: string, line: int}> $warnings
     */
    public function setWarnings(array $warnings): void
    {
        $this->warnings = $warnings;
    }

    /**
     * @return list<array{severity: int, message: string, file: string, line: int}>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /**
     * @param list<array{severity: int, message: string, file: string, line: int}> $deprecations
     */
    public function setDeprecations(array $deprecations): void
    {
        $this->deprecations = $deprecations;
    }

    /**
     * @return list<array{severity: int, message: string, file: string, line: int}>
     */
    public function getDeprecations(): array
    {
        return $this->deprecations;
    }

    public function hasDeprecations(): bool
    {
        return $this->deprecations !== [];
    }

    /**
     * @param list<array{severity: int, message: string, file: string, line: int}> $notices
     */
    public function setNotices(array $notices): void
    {
        $this->notices = $notices;
    }

    /**
     * @return list<array{severity: int, message: string, file: string, line: int}>
     */
    public function getNotices(): array
    {
        return $this->notices;
    }

    public function hasNotices(): bool
    {
        return $this->notices !== [];
    }

    /**
     * @param array<string, array{severity: int, message: string, file: string, line: int}> $notes
     * @return list<array{severity: int, message: string, file: string, line: int}>
     */
    private function ofSeverity(array $notes, int ...$severities): array
    {
        return array_values(array_filter($notes, static fn(array $note): bool => in_array($note['severity'], $severities, true)));
    }
}
