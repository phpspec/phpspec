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

namespace PhpSpec;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * @internal
 * A directory of its own for one run: created, not just named, so no other
 * process can share it, its name random rather than drawn from the clock,
 * which concurrent processes read alike.
 */
final readonly class TemporaryDirectory
{
    private function __construct(public string $path) {}

    /**
     * @param string|null $under the directory to make it in, the system's temporary one when null
     */
    public static function create(string $prefix, ?string $under = null): self
    {
        $parent = rtrim($under ?? sys_get_temp_dir(), '/\\');

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $path = $parent . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));

            if ((new ByReturnValue())->call(static fn(): bool => mkdir($path, 0777))) {
                return new self($path);
            }
        }

        throw new RuntimeException(sprintf('No directory could be made under "%s".', $parent));
    }

    /**
     * Removes the directory and everything in it.
     */
    public function remove(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());

                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($this->path);
    }
}
