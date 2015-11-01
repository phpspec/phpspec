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
    private $inClass = false;
    private $inFunction = false;
    private $inArguments = false;
    private $currentClass;
    private $currentFunction;

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
        $this->inClass = false;
        $this->inFunction = false;
        $this->inArguments = false;
        $this->currentClass = null;
        $this->currentFunction = null;
    }
    /**
     * @param array $tokens
     * @return array $tokens
     */
    private function stripTypeHints($tokens)
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                // found start of argument list
                if ('(' == $token && $this->inFunction) {
                    $this->inArguments = true;
                }
                // found end of argument list so reset state
                if (')' == $token && $this->inArguments) {
                    $this->inArguments = false;
                    $this->inFunction = true;
                    $this->currentFunction = null;
                }
            }
            elseif (T_CLASS == $token[0]) {
                $this->inClass = true;
            }
            elseif (T_FUNCTION == $token[0]) {
                $this->inFunction = true;
            }
            elseif (T_STRING == $token[0]) {
                if ($this->inFunction && !$this->currentFunction) {
                    $this->currentFunction = $token[1];
                }
                // string is likely the class name
                if ($this->inClass && !$this->currentClass) {
                    $this->currentClass = $token[1];
                }
            }
            elseif (T_VARIABLE == $token[0]) {
                // variable is an argument
                if ($this->inArguments) {
                    $typehint = '';
                    for ($i = $index - 1; in_array($tokens[$i][0], $this->typehintTokens); $i--) {
                        $typehint = $tokens[$i][1] . $typehint;
                        unset($tokens[$i]);
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
                        }
                        catch (DisallowedScalarTypehintException $e) {
                            $this->typeHintIndex->addInvalid(
                                $class,
                                trim($this->currentFunction),
                                $token[1],
                                $e
                            );
                        }
                    }
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
}
