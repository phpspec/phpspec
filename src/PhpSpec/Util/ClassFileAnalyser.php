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

use PhpSpec\Exception\Generator\NamedMethodNotFoundException;
use PhpSpec\Exception\Generator\NoMethodFoundInClass;

final class ClassFileAnalyser
{
    private $tokenLists = array();

    /**
     * @param string $class
     * @return int
     */
    public function getStartLineOfFirstMethod($class)
    {
        $tokens = $this->getTokensForClass($class);
        $index = $this->offsetForDocblock($tokens, $this->findIndexOfFirstMethod($tokens));
        return $tokens[$index][2];
    }

    /**
     * @param string $class
     * @return int
     */
    public function getEndLineOfLastMethod($class)
    {
        $tokens = $this->getTokensForClass($class);
        $index = $this->findEndOfLastMethod($tokens, $this->findIndexOfClassEnd($tokens));
        return $tokens[$index][2];
    }

    /**
     * @param string $class
     * @return bool
     */
    public function classHasMethods($class)
    {
        foreach ($this->getTokensForClass($class) as $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $class
     * @param string $methodName
     * @return int
     */
    public function getEndLineOfNamedMethod($class, $methodName)
    {
        $tokens = $this->getTokensForClass($class);

        $index = $this->findIndexOfNamedMethodEnd($tokens, $methodName);
        return $tokens[$index][2];
    }

    /**
     * @param array $tokens
     * @return int
     */
    private function findIndexOfFirstMethod(array $tokens)
    {
        for ($i = 0, $max = count($tokens); $i < $max; $i++) {
            if ($this->tokenIsFunction($tokens[$i])) {
                return $i;
            }
        }
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
     * @param $class
     * @return array
     */
    private function getTokensForClass($class)
    {
        $hash = md5($class);

        if (!in_array($hash, $this->tokenLists)) {
            $this->tokenLists[$hash] = token_get_all($class);
        }

        return $this->tokenLists[$hash];
    }

    /**
     * @param array $tokens
     * @param string $methodName
     * @return int
     */
    private function findIndexOfNamedMethodEnd(array $tokens, $methodName)
    {
        $index = $this->findIndexOfNamedMethod($tokens, $methodName);
        return $this->findIndexOfMethodOrClassEnd($tokens, $index);
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
     * @param int $index
     * @return int
     */
    private function findIndexOfMethodOrClassEnd(array $tokens, $index)
    {
        $braceCount = 0;

        for ($i = $index, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if ('{' === $token) {
                $braceCount++;
                continue;
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
     * @param mixed $token
     * @return bool
     */
    private function tokenIsFunction($token)
    {
        return is_array($token) && $token[0] === T_FUNCTION;
    }

    /**
     * @param array $tokens
     * @return int
     */
    private function findIndexOfClassEnd(array $tokens)
    {
        $classTokens = array_filter($tokens, function ($token) {
            return is_array($token) && $token[0] === T_CLASS;
        });
        $classTokenIndex = key($classTokens);
        return $this->findIndexOfMethodOrClassEnd($tokens, $classTokenIndex) - 1;
    }

    /**
     * @param array $tokens
     * @param int $index
     * @return int
     */
    public function findEndOfLastMethod(array $tokens, $index)
    {
        for ($i = $index - 1; $i > 0; $i--) {
            if ($tokens[$i] == "}") {
                return $i + 1;
            }
        }
        throw new NoMethodFoundInClass();
    }
}
