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
use RuntimeException;

final class ClassFileAnalyser
{
    private array $tokenLists = [];

    public function getStartLineOfFirstMethod(string $class): int
    {
        $tokens = $this->getTokensForClass($class);
        $index = $this->offsetForDocblock($tokens, $this->findIndexOfFirstMethod($tokens));

        return $tokens[$index]->getLine() ?? throw new RuntimeException("Could not find start line of first method");
    }

    public function getEndLineOfLastMethod(string $class): int
    {
        $tokens = $this->getTokensForClass($class);
        $index = $this->findEndOfLastMethod($tokens, $this->findIndexOfClassEnd($tokens));

        return $tokens[$index]->getLine() ?? throw new RuntimeException("Could not find end line of last method");
    }

    public function classHasMethods(string $class): bool
    {
        foreach ($this->getTokensForClass($class) as $token) {
            if ($token->hasType(T_FUNCTION)) {
                return true;
            }
        }

        return false;
    }

    public function getEndLineOfNamedMethod(string $class, string $methodName): int
    {
        $tokens = $this->getTokensForClass($class);

        $index = $this->findIndexOfNamedMethodEnd($tokens, $methodName);

        return $tokens[$index]->getLine() ?? throw new RuntimeException("Could not find end line of named method");
    }

    /** @param list<Token> $tokens */
    private function findIndexOfFirstMethod(array $tokens): int
    {
        foreach ($tokens as $i => $token) {
            if ($token->hasType(T_FUNCTION)) {
                return $i;
            }
        }

        throw new RuntimeException('Could not find index of first method');
    }

    /** @param list<Token> $tokens */
    private function offsetForDocblock(array $tokens, int $indexToSearchBefore): int
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

        for ($i = $indexToSearchBefore - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if ($token->isInTypes($allowedTokens)) {
                continue;
            }

            if ($token->hasType(T_DOC_COMMENT)) {
                return $i;
            }

            return $indexToSearchBefore;
        }

        throw new RuntimeException('Could not find index of for docblock');
    }

    /** @return list<Token> */
    private function getTokensForClass(string $class): array
    {
        $hash = md5($class);

        if (!\in_array($hash, $this->tokenLists)) {
            $this->tokenLists[$hash] = Token::getAll($class);
        }

        return $this->tokenLists[$hash];
    }


    /** @param list<Token> $tokens */
    private function findIndexOfNamedMethodEnd(array $tokens, string $methodName): int
    {
        $index = $this->findIndexOfNamedMethod($tokens, $methodName);
        return $this->findIndexOfMethodOrClassEnd($tokens, $index);
    }

    /**
     * @param list<Token> $tokens
     *
     * @throws NamedMethodNotFoundException
     */
    private function findIndexOfNamedMethod(array $tokens, string $methodName): int
    {
        $searchingAfterFunctionKeyword = false;

        foreach ($tokens as $i => $token) {
            $token = $tokens[$i];

            if ($token->hasType(T_FUNCTION)) {
                $searchingAfterFunctionKeyword = true;
            }

            if (!$searchingAfterFunctionKeyword) {
                continue;
            }

            if ($token->hasType(T_STRING)) {
                if ($token->equals($methodName)) {
                    return $i;
                }

                $searchingAfterFunctionKeyword = false;
            }
        }

        throw new NamedMethodNotFoundException('Target method not found');
    }

    /** @param list<Token> $tokens */
    private function findIndexOfMethodOrClassEnd(array $tokens, int $index): int
    {
        $braceCount = 0;

        for ($i = $index, $max = \count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if ($token->equals('{')) {
                $braceCount++;
                continue;
            }

            if ($token->equals('}')) {
                $braceCount--;
                if ($braceCount === 0) {
                    return $i + 1;
                }
            }
        }

        throw new RuntimeException('Could not find last method or class end');
    }

    /** @param list<Token> $tokens */
    private function findIndexOfClassEnd(array $tokens): int
    {
        $classTokens = array_filter($tokens, function ($token) {
            return $token->hasType(T_CLASS);
        });
        $classKeywordIndex = key($classTokens);

        return $this->findIndexOfMethodOrClassEnd($tokens, $classKeywordIndex) - 1;
    }

    /** @param list<Token> $tokens */
    public function findEndOfLastMethod(array $tokens, int $index): int
    {
        for ($i = $index - 1; $i > 0; $i--) {
            if ($tokens[$i]->equals("}")) {
                return $i + 1;
            }
        }
        throw new NoMethodFoundInClass();
    }
}
