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

namespace PhpSpec\Util;

use PhpSpec\Exception\Util\NamedMethodNotFoundException;

final class TokenizedCodeWriter implements CodeWriter
{
    /**
     * @param string $class
     * @param string $method
     * @return string
     */
    public function insertMethodFirstInClass($class, $method)
    {
        $tokenizedPhp = token_get_all($class);

        if ($this->classHasNoMethods($tokenizedPhp)) {
            return $this->writeAtEndOfClass($class, $method, $tokenizedPhp);
        }

        $index = $this->offsetForDocblock($tokenizedPhp, $this->findIndexOfFirstMethod($tokenizedPhp));
        $line = $tokenizedPhp[$index][2];

        return $this->insertStringBeforeLine($class, $method, $line);
    }

    /**
     * @param string $class
     * @param string $method
     * @return string
     */
    public function insertMethodLastInClass($class, $method)
    {
        $tokenizedPhp = token_get_all($class);

        if ($this->classHasNoMethods($tokenizedPhp)) {
            return $this->writeAtEndOfClass($class, $method, $tokenizedPhp);
        }

        $lastIndex = $this->findIndexOfLastMethodEnd($tokenizedPhp);
        $line = $tokenizedPhp[$lastIndex][2];

        return $this->insertStringAfterLine($class, $method, $line);
    }

    /**
     * @param string $class
     * @param string $methodName
     * @param string $method
     * @return string
     */
    public function insertAfterMethod($class, $methodName, $method)
    {
        $tokenizedPhp = token_get_all($class);

        $index = $this->findIndexOfNamedMethodEnd($tokenizedPhp, $methodName);
        $line = $tokenizedPhp[$index][2];

        return $this->insertStringAfterLine($class, $method, $line);
    }

    /**
     * @param array $tokens
     * @return int
     */
    private function findIndexOfLastMethodEnd(array $tokens)
    {
        $index = $this->findIndexOfLastMethod($tokens);
        return $this->findIndexOfMethodEnd($tokens, $index);
    }

    /**
     * @param array $tokens
     * @param int $index
     * @return int
     */
    private function findIndexOfMethodEnd(array $tokens, $index)
    {
        $braceCount = 0;

        for ($i = $index, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if ('{' === $token) {
                $braceCount++;
            }

            if ('}' === $token) {
                $braceCount--;
                if ($braceCount === 0) {
                    return $i + 1;
                }
            }
        }
    }

    /**
     * @param array $tokens
     * @return int
     */
    private function findIndexOfFirstMethod(array $tokens)
    {
        for ($i = 0, $max = count($tokens); $i < $max; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) {
                return $i;
            }
        }
    }

    /**
     * @param array $tokens
     * @return int
     */
    private function findIndexOfLastMethod(array $tokens)
    {
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) {
                return $i;
            }
        }
    }

    /**
     * @param string $target
     * @param string $toInsert
     * @param int $line
     * @param bool $leadingNewline
     * @return string
     */
    private function insertStringAfterLine($target, $toInsert, $line, $leadingNewline = true)
    {
        $lines = explode("\n", $target);
        $lastLines = array_slice($lines, $line);
        $toInsert = trim($toInsert, "\n\r");
        if ($leadingNewline) {
            $toInsert = "\n" . $toInsert;
        }
        array_unshift($lastLines, $toInsert);
        array_splice($lines, $line, count($lines), $lastLines);
        return implode("\n", $lines);
    }

    /**
     * @param string $target
     * @param string $toInsert
     * @param int $line
     * @return string
     */
    private function insertStringBeforeLine($target, $toInsert, $line)
    {
        $line--;
        $lines = explode("\n", $target);
        $lastLines = array_slice($lines, $line);
        array_unshift($lastLines, trim($toInsert, "\n\r") . "\n");
        array_splice($lines, $line, count($lines), $lastLines);
        return implode("\n", $lines);
    }

    /**
     * @param array $tokens
     * @param int $index
     * @return int
     */
    private function offsetForDocblock(array $tokens, $index)
    {
        $allowedTokens = array(
            T_FINAL,
            T_ABSTRACT,
            T_PUBLIC,
            T_PRIVATE,
            T_PROTECTED,
            T_STATIC,
            T_WHITESPACE
        );

        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                return $index;
            }

            if (in_array($token[0], $allowedTokens)) {
                continue;
            }

            if ($token[0] === T_DOC_COMMENT) {
                return $i;
            }

            return $index;
        }
    }

    /**
     * @param array $tokens
     * @param string $methodName
     * @return int
     */
    private function findIndexOfNamedMethodEnd(array $tokens, $methodName)
    {
        $index = $this->findIndexOfNamedMethod($tokens, $methodName);
        return $this->findIndexOfMethodEnd($tokens, $index);
    }

    /**
     * @param array $tokens
     * @param string $methodName
     * @return int
     * @throws NamedMethodNotFoundException
     */
    private function findIndexOfNamedMethod(array $tokens, $methodName)
    {
        $searching = false;

        for ($i = 0, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                $searching = true;
            }

            if (!$searching) {
                continue;
            }

            if ($token[0] === T_STRING) {
                if ($token[1] === $methodName) {
                    return $i;
                }

                $searching = false;
            }
        }

        throw new NamedMethodNotFoundException('Target method not found');
    }

    /**
     * @param array $tokens
     * @return bool
     */
    private function classHasNoMethods(array $tokens)
    {
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $class
     * @param string $method
     * @param array $tokens
     * @return string
     */
    private function writeAtEndOfClass($class, $method, array $tokens)
    {
        $searching = false;
        $searchPattern = array();

        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if ($token === '}') {
                $searching = true;
            }

            if (!$searching) {
                continue;
            }

            if (is_array($token) && $token[1] === PHP_EOL) {
                $line = $token[2];
                return $this->insertStringAfterLine($class, $method, $line, false);
            }

            array_unshift($searchPattern, is_array($token)? $token[1] : $token);

            if ($token === '{') {
                $search = implode('', $searchPattern);
                $position = strpos($class, $search) + strlen($search) - 1;
                return substr_replace($class, "\n" . $method . "\n", $position, 0);
            }
        }
    }
}
