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

namespace PhpSpec\CodeGeneration;

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;

/**
 * @internal
 * Adds method stubs to existing class or interface files. Supports generating empty method
 * bodies, method bodies with return expressions (--fake mode), and filling in existing
 * empty method bodies with return expressions.
 */
final class MethodStubGenerator
{
    private readonly Filesystem $filesystem;

    /**
     * @param SourceLayout $layout where a class's file lives, when it is not loaded
     * @param Filesystem $filesystem filesystem abstraction for testability
     */
    public function __construct(private readonly SourceLayout $layout = new SourceLayout(), ?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Generates a new method stub and inserts it before the closing brace of the class or interface.
     * For interfaces, generates a method signature only. For classes, generates a full method body,
     * optionally with a return expression.
     *
     * @param string $className the fully qualified class or interface name
     * @param string $methodName the method name to generate
     * @param int $argumentCount number of arguments to include in the signature
     * @param string|null $returnExpression PHP expression to return (--fake mode), or null for empty body
     * @param bool $static whether the method was called statically, and is declared so
     * @return string confirmation message
     * @throws RuntimeException if the source file is not found, the method already exists, or the closing brace is missing
     */
    public function generate(string $className, string $methodName, int $argumentCount = 0, ?string $returnExpression = null, bool $static = false): string
    {
        $filePath = $this->resolveFilePath($className);

        if (!$this->filesystem->exists($filePath)) {
            throw new RuntimeException("Source file not found: '$filePath'");
        }

        $content = $this->filesystem->read($filePath);

        if (str_contains($content, "function $methodName(")) {
            throw new RuntimeException("Method '$methodName' already exists in '$filePath'");
        }

        $params = [];
        for ($i = 1; $i <= $argumentCount; $i++) {
            $params[] = '$argument' . $i;
        }
        $paramString = implode(', ', $params);

        $isInterface = (bool) preg_match('/\binterface\s+\w+/', $content);
        $signature = ($static ? 'public static function ' : 'public function ') . "$methodName($paramString)";

        if ($isInterface) {
            $stub = "    $signature;\n";
        } elseif ($returnExpression !== null) {
            $stub = "    $signature\n    {\n        return $returnExpression;\n    }\n";
        } else {
            $stub = "    $signature\n    {\n    }\n";
        }

        $lastBrace = strrpos($content, '}');
        if ($lastBrace === false) {
            throw new RuntimeException("Could not find class closing brace in '$filePath'");
        }

        // A first member sits right under the opening brace; a later one is
        // set apart from the member before it by one empty line.
        if (preg_match('/\b(?:class|interface|trait)\s+\w+[^{]*\{/', $content, $opening, PREG_OFFSET_CAPTURE) === 1) {
            $bodyStart = $opening[0][1] + strlen($opening[0][0]);
            if (trim(substr($content, $bodyStart, $lastBrace - $bodyStart)) !== '') {
                $stub = "\n" . $stub;
            }
        }

        $newContent = substr($content, 0, $lastBrace) . $stub . substr($content, $lastBrace);
        $this->filesystem->write($filePath, $newContent);

        return "Method '$methodName()' generated in '$filePath'";
    }

    /**
     * Fills an existing empty method body with a return expression.
     * Used in --fake mode to add hardcoded return values derived from spec expectations.
     *
     * @param string $className the fully qualified class name
     * @param string $methodName the method whose empty body to fill
     * @param string $returnExpression the PHP expression to insert as the return value
     * @return string confirmation message
     * @throws RuntimeException if the source file is not found or the method does not have an empty body
     */
    public function fillEmptyMethod(string $className, string $methodName, string $returnExpression): string
    {
        $filePath = $this->resolveFilePath($className);

        if (!$this->filesystem->exists($filePath)) {
            throw new RuntimeException("Source file not found: '$filePath'");
        }

        $content = $this->filesystem->read($filePath);

        // Match an empty method body: function methodName(...) {\n    \n    }
        $pattern = '/(function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*\{)\s*(})/s';
        if (!preg_match($pattern, $content)) {
            throw new RuntimeException("Method '$methodName' in '$filePath' does not have an empty body");
        }

        $newContent = preg_replace(
            $pattern,
            '$1' . "\n        return $returnExpression;\n    " . '$2',
            $content,
            1,
        ) ?? $content;

        $this->filesystem->write($filePath, $newContent);

        return "Method '$methodName()' filled in '$filePath'";
    }

    /**
     * Checks whether the given class has a method with an empty body.
     *
     * @param string $className the fully qualified class name
     * @param string $methodName the method name to check
     * @return bool true if the method exists with an empty body
     */
    public function hasEmptyMethod(string $className, string $methodName): bool
    {
        $filePath = $this->resolveFilePath($className);

        if (!$this->filesystem->exists($filePath)) {
            return false;
        }

        $content = $this->filesystem->read($filePath);

        $pattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*\{\s*}/s';
        return (bool) preg_match($pattern, $content);
    }

    private function resolveFilePath(string $className): string
    {
        return ClassLocation::for($className, $this->layout)->filePath();
    }
}
