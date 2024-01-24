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

use PhpSpec\Util\Token;

final class TokenizedNamespaceResolver implements NamespaceResolver
{
    const STATE_DEFAULT = 0;
    const STATE_READING_NAMESPACE = 1;
    const STATE_READING_USE = 2;
    const STATE_READING_USE_GROUP = 3;

    private int $state = self::STATE_DEFAULT;

    private string $currentNamespace = '';
    private string $currentUseGroup = '';
    private string $currentUse = '';
    private array $uses = [];

    public function analyse(string $code): void
    {
        $this->state = self::STATE_DEFAULT;
        $this->currentUse = '';
        $this->currentUseGroup = '';
        $this->uses = [];

        $tokens = Token::getAll($code);

        foreach ($tokens as $_ => $token) {

            switch ($this->state) {

                case self::STATE_READING_NAMESPACE:
                    if ($token->equals(';')) {
                        $this->currentNamespace = trim($this->currentNamespace);
                        $this->state = self::STATE_DEFAULT;
                    }
                    else {
                        $this->currentNamespace .= $token->asString();
                    }
                    break;

                case self::STATE_READING_USE_GROUP:
                    if ($token->equals('}')) {
                        $this->state = self::STATE_READING_USE;
                        $this->currentUseGroup = '';
                    }
                    elseif ($token->equals(',')) {
                        $this->storeCurrentUse();
                    }
                    else {
                        $this->currentUse = $this->currentUseGroup . trim($token->asString());
                    }
                    break;

                case self::STATE_READING_USE:
                    if ($token->equals(';')) {
                        $this->storeCurrentUse();
                        $this->state = self::STATE_DEFAULT;
                    }
                    if ($token->equals('{')) {
                        $this->currentUseGroup = trim($this->currentUse);
                        $this->state = self::STATE_READING_USE_GROUP;
                    }
                    elseif ($token->equals(',')) {
                        $this->storeCurrentUse();
                    }
                    else {
                        $this->currentUse .= $token->asString();
                    }
                    break;

                default:
                    if ($token->hasType(T_NAMESPACE)) {
                        $this->state = self::STATE_READING_NAMESPACE;
                        $this->currentNamespace = '';
                        $this->uses = array();
                    }
                    if ($token->hasType(T_USE)) {
                        $this->state = self::STATE_READING_USE;
                        $this->currentUse = '';
                    }

            }
        }
    }

    public function resolve(string $typeAlias): string
    {
        if (strpos($typeAlias, '\\') === 0) {
            return substr($typeAlias, 1);
        }
        if (($divider = strpos($typeAlias, '\\')) && array_key_exists(strtolower(substr($typeAlias, 0, $divider)), $this->uses)) {
            return $this->uses[strtolower(substr($typeAlias, 0, $divider))] . substr($typeAlias, $divider);
        }
        if (array_key_exists(strtolower($typeAlias), $this->uses)) {
            return $this->uses[strtolower($typeAlias)];
        }
        if ($this->currentNamespace) {
            return $this->currentNamespace . '\\' . $typeAlias;
        }

        return $typeAlias;
    }

    private function storeCurrentUse() : void
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

        $this->currentUse = '';
    }
}
