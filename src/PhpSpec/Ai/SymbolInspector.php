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

namespace PhpSpec\Ai;

use PhpSpec\CodeGeneration\ClassLocation;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 * Answers "what is this symbol, really?" for the AI: whether an FQCN is
 * autoloadable, where its file lives, and its actual public method signatures
 * from Reflection. Unlike read_file — which says "File not found" whether the
 * class is genuinely absent or merely fails to autoload — a symbol that does not
 * exist yet gets a clean, honest answer, so the model stops guessing at an API
 * or offering to create a class that is already there.
 */
final readonly class SymbolInspector
{
    private Filesystem $filesystem;

    public function __construct(
        private string $srcPath = 'src',
        private string $psr4Prefix = '',
        ?Filesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Describes a class/interface/trait by its fully-qualified name: a compact,
     * plain-text report the model can read as a tool result.
     */
    public function describe(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');

        // Referencing all three triggers autoloading; the disjunction also narrows
        // $fqcn to a class-string for the reflection that follows.
        if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn)) {
            return $this->presentReport($fqcn, new ReflectionClass($fqcn));
        }

        return $this->absentReport($fqcn);
    }

    /**
     * The report for a symbol that is not autoloadable — distinguishing "the file
     * is simply not there yet" from "the file exists but does not autoload",
     * which is a PSR-4 mismatch rather than a missing class.
     */
    private function absentReport(string $fqcn): string
    {
        $location = ClassLocation::for($fqcn, $this->srcPath, $this->psr4Prefix);

        if ($location->exists($this->filesystem)) {
            return sprintf(
                '%s is not autoloadable, though a source file exists at %s — likely a PSR-4 mapping mismatch, not a missing class.',
                $fqcn,
                $location->filePath(),
            );
        }

        return sprintf(
            '%s does not exist yet. There is nothing to read — start its spec with describe, or create the source with write_file.',
            $fqcn,
        );
    }

    /**
     * The report for a symbol that exists: its kind, file, and the real public
     * method signatures it declares.
     *
     * @param ReflectionClass<object> $reflection
     */
    private function presentReport(string $fqcn, ReflectionClass $reflection): string
    {
        $kind = $reflection->isInterface() ? 'interface' : ($reflection->isTrait() ? 'trait' : 'class');
        $filePath = $reflection->getFileName()
            ?: ClassLocation::for($fqcn, $this->srcPath, $this->psr4Prefix)->filePath();

        $lines = [sprintf('%s %s (%s)', $kind, $fqcn, $filePath)];

        $signatures = $this->publicSignatures($reflection);
        if ($signatures === []) {
            $lines[] = '  (no public methods declared)';
        }

        foreach ($signatures as $signature) {
            $lines[] = '  ' . $signature;
        }

        return implode("\n", $lines);
    }

    /**
     * The public method signatures the class itself declares (not inherited), so
     * the report shows the type's own surface without framework noise.
     *
     * @param ReflectionClass<object> $reflection
     * @return list<string>
     */
    private function publicSignatures(ReflectionClass $reflection): array
    {
        $signatures = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $signatures[] = self::signature($method);
        }

        return $signatures;
    }

    /**
     * Renders one method as a readable signature: parameters with their types and
     * the return type, as written in source.
     */
    private static function signature(ReflectionMethod $method): string
    {
        $params = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->hasType() ? (string) $parameter->getType() . ' ' : '';
            $params[] = $type . '$' . $parameter->getName();
        }

        $return = $method->hasReturnType() ? ': ' . (string) $method->getReturnType() : '';

        return sprintf('public function %s(%s)%s', $method->getName(), implode(', ', $params), $return);
    }
}
