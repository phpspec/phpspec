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

namespace PhpSpec\Loader\Transformer;

use PhpSpec\Loader\SpecTransformer;

final class TypeHintRewriter implements SpecTransformer
{
    private $inClass = false;
    private $inFunction = false;
    private $inArguments = false;
    private $readingNamespace = false;
    private $readingUse = false;
    private $currentClass;
    private $currentFunction;
    private $currentNamespace;
    private $currentUse;
    private $uses = array();

    private $typehintTokens = array(
        T_WHITESPACE, T_STRING, T_NS_SEPARATOR
    );

    /**
     * @var TypeHintIndex
     */
    private $typeHintIndex;

    public function __construct(TypeHintIndex $typeHintIndex)
    {
        $this->typeHintIndex = $typeHintIndex;
    }

    /**
     * @param string $spec
     * @return string
     */
    public function transform($spec)
    {
        $tokens = $this->stripTypeHints(token_get_all($spec));

        $tokensToString = $this->tokensToString($tokens);

        return $tokensToString;
    }

    /**
     * @param array $tokens
     * @return $tokens
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
                    unset($this->currentFunction);
                }
                // found end of namespace declaration
                if (';' == $token && $this->readingNamespace) {
                    $this->currentNamespace = trim($this->currentNamespace);
                    $this->readingNamespace = false;
                }
                // found end of use statement
                if (';' == $token && $this->readingUse) {
                    $this->storeUse();
                    $this->currentUse = '';
                    $this->readingUse = false;
                }
                // found break in use statement
                if (',' == $token && $this->readingUse) {
                    $this->currentNamespace = trim($this->currentNamespace);
                    $this->storeUse();
                    $this->currentUse = '';
                }
            }
            elseif ($this->readingUse) {
                $this->currentUse .= $token[1];
            }
            elseif (T_CLASS == $token[0]) {
                $this->inClass = true;
            }
            elseif (T_FUNCTION == $token[0]) {
                $this->inFunction = true;
            }
            elseif (T_STRING == $token[0]) {
                // string is likely the function name
                if ($this->inFunction && !$this->currentFunction) {
                    $this->currentFunction = $token[1];
                }
                // string is likely the class name
                if ($this->inClass && !$this->currentClass) {
                    $this->currentClass = $token[1];
                }
                // string is likey part of the namespace
                if ($this->readingNamespace) {
                    $this->currentNamespace .= $token[1];
                }
            }
            elseif (T_VARIABLE == $token[0]) {
                // variable is an argument
                if ($this->inArguments) {
                    $typehint = '';
                    for ($i = $index - 1; in_array($tokens[$i][0], $this->typehintTokens); $i--) {
                        $typehint = $tokens[$i][1] . $typehint;
                        unset($tokens[$i]);

                        if ($typehint = trim($typehint)) {
                            $this->typeHintIndex->add(
                                $this->applyNamespace($this->currentClass),
                                $token[1],
                                $this->applyNamespace($typehint));
                        }
                    }
                }
            }
            elseif (T_NAMESPACE == $token[0]) {
                $this->readingNamespace = true;
                $this->currentNamespace = '';
                $this->uses = array();
            }
            elseif (T_USE == $token[0]) {
                $this->readingUse = true;
                $this->currentUse = '';
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
     * @param string $classAlias
     * @return string
     */
    private function applyNamespace($classAlias)
    {
        if (array_key_exists($classAlias, $this->uses)) {
            return $this->uses[$classAlias];
        }
        if ($this->currentNamespace) {
            return  $this->currentNamespace . '\\' . $classAlias;
        }

        return $classAlias;
    }

    private function storeUse()
    {
        if (preg_match('/\s*(.*)\s+as\s+(.*)\s*/', $this->currentUse, $matches)) {
            $this->uses[trim($matches[2])] = trim($matches[1]);
        }
        elseif(preg_match('/\\\\([^\\\\]+)\s*/', $this->currentUse, $matches)){
            $this->uses[$matches[1]] = trim($this->currentUse);
        }
        else {
            $this->uses[trim($this->currentUse)] = trim($this->currentUse);
        }
    }
}
