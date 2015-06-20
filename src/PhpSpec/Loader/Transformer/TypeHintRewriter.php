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
    private $inFunction = false;
    private $inArguments = false;
    private $currentFunction;

    private $typehintTokens = array(
        T_WHITESPACE, T_STRING, T_NS_SEPARATOR
    );

    public function transform($spec)
    {
        $tokens = $this->stripTypeHints(token_get_all($spec));

        $tokensToString = $this->tokensToString($tokens);

        return $tokensToString;
    }

    /**
     * @param $tokens
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
            }
            elseif (T_FUNCTION == $token[0]) {
                $this->inFunction = true;
            }
            elseif (T_STRING == $token[0]) {
                // string is likely the function name
                if ($this->inFunction && !$this->currentFunction) {
                    $this->currentFunction = $token[1];
                }
            }
            elseif (T_VARIABLE == $token[0]) {
                // variable is an argument
                if ($this->inArguments) {
                    $typehint = '';
                    for ($i = $index -1; in_array($tokens[$i][0], $this->typehintTokens); $i--) {
                        $typehint = $tokens[$i][1] . $typehint;
                        unset($tokens[$i]);
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * @param $tokens
     * @return string
     */
    private function tokensToString($tokens)
    {
        return join('', array_map(function ($token) {
            return is_array($token) ? $token[1] : $token;
        }, $tokens));
    }
}
