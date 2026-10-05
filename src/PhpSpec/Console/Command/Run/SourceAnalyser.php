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

namespace PhpSpec\Console\Command\Run;

/**
 * @internal
 * Reads spec source files to extract method signatures and expected return values.
 * Used by the code generation pipeline to determine argument counts and --fake return expressions.
 */
final class SourceAnalyser
{
    /**
     * Extracts the number of arguments passed to a method call at the given source location.
     *
     * @param string $file the source file path
     * @param int $line the line number containing the method call
     * @param string $methodName the method name to look for
     * @return int the number of arguments (0 if not determinable)
     */
    public function extractArgumentCount(string $file, int $line, string $methodName): int
    {
        $sourceLine = $this->sourceLine($file, $line);

        if (preg_match('/(?:->|::)' . preg_quote($methodName, '/') . '\(([^)]*)\)/', $sourceLine, $m)) {
            return $this->countArguments($m[1]);
        }

        return 0;
    }

    /**
     * Whether the method call at the given source location is a static one,
     * `Name::method()`, `self::method()` or `static::method()`, so the stub
     * generated for it can be called the way the spec calls it.
     */
    public function isStaticCall(string $file, int $line, string $methodName): bool
    {
        return preg_match('/[\w\\\\$]::' . preg_quote($methodName, '/') . '\(/', $this->sourceLine($file, $line)) === 1;
    }

    private function sourceLine(string $file, int $line): string
    {
        if (!file_exists($file)) {
            return '';
        }

        $lines = file($file);

        return $lines === false ? '' : ($lines[$line - 1] ?? '');
    }

    /**
     * The value a toBe() expectation wants of the method call at the given
     * location, for --fake to return: only when the call itself is what
     * expect() was given. A method whose result the expectation went on to
     * call something on has no value of its own to fake.
     *
     * @param string $file the spec file path
     * @param int $line the error line number
     * @param string $methodName the undefined method name
     * @return string|null the expected return expression, or null when there is none to take
     */
    public function extractExpectedReturnValue(string $file, int $line, string $methodName): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        // The statement that starts on the error line, to its semicolon.
        $statement = '';
        for ($i = max(0, $line - 1); $i < min(count($lines), $line + 5); $i++) {
            $statement .= $lines[$i];
            if (str_contains($lines[$i], ';')) {
                break;
            }
        }

        $call = '(?:->|::)' . preg_quote($methodName, '/') . '\((?:[^()]|\([^()]*\))*\)';
        if (preg_match('/' . $call . '\)\s*->toBe\((.+)\)\s*;/s', $statement, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Searches backward from a given line to find a "new ClassName" assignment for a variable.
     * Also matches let("varName", fn() => new ClassName()) and $this->varName patterns.
     *
     * @param array<string> $lines the source file lines
     * @param int $beforeLine the line index to search backward from
     * @param string $varName the variable name to resolve (without $)
     * @return string|null the fully qualified class name if found, or null
     */
    public function resolveVariableClass(array $lines, int $beforeLine, string $varName): ?string
    {
        $shortName = null;

        for ($i = $beforeLine; $i >= 0; $i--) {
            // Match: $var = new ClassName(...)
            if (preg_match('/\$' . preg_quote($varName, '/') . '\s*=\s*new\s+([A-Za-z0-9_\\\\]+)/', $lines[$i], $m)) {
                $shortName = $m[1];
                break;
            }
            // Match: let("var", fn() => new ClassName(...))
            if (preg_match('/let\s*\(\s*["\']' . preg_quote($varName, '/') . '["\']\s*,\s*fn\s*\(.*?\)\s*=>\s*new\s+([A-Za-z0-9_\\\\]+)/', $lines[$i], $m)) {
                $shortName = $m[1];
                break;
            }
        }

        if ($shortName === null) {
            return null;
        }

        // Already fully qualified
        if (str_contains($shortName, '\\')) {
            return $shortName;
        }

        // Resolve short name via use imports
        return $this->resolveUseImport($lines, $shortName);
    }

    /**
     * Resolves a short class name to its FQCN using use-import statements in the file.
     *
     * @param array<string> $lines the source file lines
     * @param string $shortName the short class name
     * @return string the FQCN if a use import is found, otherwise the short name as-is
     */
    public function resolveUseImport(array $lines, string $shortName): string
    {
        foreach ($lines as $line) {
            if (preg_match('/^use\s+([A-Za-z0-9_\\\\]+\\\\' . preg_quote($shortName, '/') . ')\s*;/', trim($line), $m)) {
                return $m[1];
            }
        }
        return $shortName;
    }

    /**
     * Counts comma-separated arguments in a string, respecting nested parentheses, brackets, and braces.
     *
     * @param string $argsStr the raw arguments string (contents between parentheses)
     * @return int the number of arguments
     */
    public function countArguments(string $argsStr): int
    {
        $argsStr = trim($argsStr);
        if ($argsStr === '') {
            return 0;
        }
        $depth = 0;
        $count = 1;
        for ($i = 0; $i < strlen($argsStr); $i++) {
            $ch = $argsStr[$i];
            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
            } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
            } elseif ($ch === ',' && $depth === 0) {
                $count++;
            }
        }
        return $count;
    }
}
