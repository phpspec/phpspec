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

final class TokenizedNamespaceResolver implements NamespaceResolver
{
    private $readingNamespace = false;
    private $readingUse = false;
    private $currentNamespace;
    private $currentUse;
    private $uses = array();

    /**
     * @param string $code
     */
    public function analyse($code)
    {
        $this->readingNamespace = false;
        $this->readingUse = false;
        $this->currentUse = null;
        $this->uses = array();

        $tokens = token_get_all($code);

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
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
            elseif (T_STRING == $token[0]) {
                // string is likey part of the namespace
                if ($this->readingNamespace) {
                    $this->currentNamespace .= $token[1];
                }
            }
            elseif (T_NS_SEPARATOR == $token[0]) {
                // string is likey part of the namespace
                if ($this->readingNamespace) {
                    $this->currentNamespace .= $token[1];
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
    }

    /**
     * @param string $typeAlias
     *
     * @return string
     */
    public function resolve($typeAlias)
    {
        if (strpos($typeAlias, '\\') === 0) {
            return substr($typeAlias, 1);
        }
        if (array_key_exists(strtolower($typeAlias), $this->uses)) {
            return $this->uses[strtolower($typeAlias)];
        }
        if ($this->currentNamespace) {
            return $this->currentNamespace . '\\' . $typeAlias;
        }

        return $typeAlias;
    }

    private function storeUse()
    {
        if (preg_match('/\s*(.*)\s+as\s+(.*)\s*/', $this->currentUse, $matches)) {
            $this->uses[strtolower(trim($matches[2]))] = trim($matches[1]);
        }
        elseif(preg_match('/\\\\([^\\\\]+)\s*$/', $this->currentUse, $matches)){
            $this->uses[strtolower($matches[1])] = trim($this->currentUse);
        }
        else {
            $this->uses[strtolower(trim($this->currentUse))] = trim($this->currentUse);
        }
    }
}
