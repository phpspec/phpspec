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

namespace PhpSpec\CodeAnalysis;

use PhpSpec\Loader\Transformer\TypeHintIndex;

final class TokenizedTypeHintRewriter implements TypeHintRewriter
{
    const STATE_DEFAULT = 0;
    const STATE_READING_CLASS = 1;
    const STATE_READING_FUNCTION = 2;
    const STATE_READING_ARGUMENTS = 3;
    const STATE_READING_FUNCTION_BODY = 4;

    private $state = self::STATE_DEFAULT;

    private $currentClass;
    private $currentFunction;
    private $currentBodyLevel;

    private $typehintTokens = array(
        T_WHITESPACE, T_STRING, T_NS_SEPARATOR
    );

    /**
     * @var TypeHintIndex
     */
    private $typeHintIndex;
    /**
     * @var NamespaceResolver
     */
    private $namespaceResolver;

    /**
     * @param TypeHintIndex $typeHintIndex
     * @param NamespaceResolver $namespaceResolver
     */
    public function __construct(TypeHintIndex $typeHintIndex, NamespaceResolver $namespaceResolver)
    {
        $this->typeHintIndex = $typeHintIndex;
        $this->namespaceResolver = $namespaceResolver;
    }

    /**
     * @param string $classDefinition
     *
     * @return string
     */
    public function rewrite($classDefinition)
    {
        $this->namespaceResolver->analyse($classDefinition);

        $this->reset();
        $tokens = $this->stripTypeHints(token_get_all($classDefinition));
        $tokensToString = $this->tokensToString($tokens);

        return $tokensToString;
    }

    private function reset()
    {
        $this->state = self::STATE_DEFAULT;
        $this->currentClass = '';
        $this->currentFunction = '';
    }

    /**
     * @param array $tokens
     * @return array $tokens
     */
    private function stripTypeHints($tokens)
    {
        foreach ($tokens as $index => $token) {
            if ($this->isToken($token, '{')) {
                $this->currentBodyLevel++;
            }
            elseif ($this->isToken($token, '}')) {
                $this->currentBodyLevel--;
            }

            switch ($this->state) {
                case self::STATE_READING_ARGUMENTS:
                    if (')' == $token) {
                        $this->state = self::STATE_READING_CLASS;
                    }
                    elseif ($this->tokenHasType($token, T_VARIABLE)) {
                        $this->extractTypehints($tokens, $index, $token);
                    }
                    break;
                case self::STATE_READING_FUNCTION:
                    if ('(' == $token) {
                        $this->state = self::STATE_READING_ARGUMENTS;
                    }
                    elseif ($this->tokenHasType($token, T_STRING) && !$this->currentFunction) {
                        $this->currentFunction = $token[1];
                    }
                    break;
                case self::STATE_READING_CLASS:
                    if ('{' == $token && $this->currentFunction) {
                        $this->state = self::STATE_READING_FUNCTION_BODY;
                        $this->currentBodyLevel = 1;
                    }
                    elseif ('}' == $token && $this->currentClass) {
                        $this->state = self::STATE_DEFAULT;
                        $this->currentClass = null;
                    }
                    elseif ($this->tokenHasType($token, T_STRING) && !$this->currentClass && $this->shouldExtractTokensOfClass($token[1])) {
                        $this->currentClass = $token[1];
                    }
                    elseif ($this->tokenHasType($token, T_FUNCTION) && $this->currentClass) {
                        $this->state = self::STATE_READING_FUNCTION;
                    }
                    break;
                case self::STATE_READING_FUNCTION_BODY:
                    if ('}' == $token && $this->currentBodyLevel === 0) {
                        $this->currentFunction = '';
                        $this->state = self::STATE_READING_CLASS;
                    }

                    break;
                default:
                    if ($this->tokenHasType($token, T_CLASS)) {
                        $this->state = self::STATE_READING_CLASS;
                    }
            }
        }

        return $tokens;
    }

    /**
     * @param array $tokens
     * @return string
     */
    private function tokensToString($tokens)
    {
        return join('', array_map(function ($token) {
            return is_array($token) ? $token[1] : $token;
        }, $tokens));
    }

    /**
     * @param array $tokens
     * @param integer $index
     * @param array $token
     */
    private function extractTypehints(&$tokens, $index, $token)
    {
        $typehint = '';
        for ($i = $index - 1; in_array($tokens[$i][0], $this->typehintTokens); $i--) {
            $typehint = $tokens[$i][1] . $typehint;

            if (T_WHITESPACE !== $tokens[$i][0]) {
                unset($tokens[$i]);
            }
        }

        if ($typehint = trim($typehint)) {
            $class = $this->namespaceResolver->resolve($this->currentClass);
            try {
                $typehintFcqn = $this->namespaceResolver->resolve($typehint);
                $this->typeHintIndex->add(
                    $class,
                    trim($this->currentFunction),
                    $token[1],
                    $typehintFcqn
                );
            } catch (DisallowedNonObjectTypehintException $e) {
                $this->typeHintIndex->addInvalid(
                    $class,
                    trim($this->currentFunction),
                    $token[1],
                    $e
                );
            }
        }
    }

    /**
     * @param array|string $token
     * @param string $type
     *
     * @return bool
     */
    private function tokenHasType($token, $type)
    {
        return is_array($token) && $type == $token[0];
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    private function shouldExtractTokensOfClass($className)
    {
        return substr($className, -4) == 'Spec';
    }

    /**
     * @param array|string $token
     * @param string $string
     *
     * @return bool
     */
    private function isToken($token, $string)
    {
        return $token == $string || (is_array($token) && $token[1] == $string);
    }
}
