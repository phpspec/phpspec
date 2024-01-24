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
use PhpSpec\Util\Token;
use function array_map;
use function join;

final class TokenizedTypeHintRewriter implements TypeHintRewriter
{
    const STATE_DEFAULT = 0;
    const STATE_READING_CLASS = 1;
    const STATE_READING_FUNCTION = 2;
    const STATE_READING_ARGUMENTS = 3;
    const STATE_READING_FUNCTION_BODY = 4;

    private int $state = self::STATE_DEFAULT;

    private string $currentClass = '';
    private string $currentFunction = '';
    private int $currentBodyLevel = 0;

    private array $typehintTokens = [
        T_WHITESPACE,
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_QUALIFIED,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
    ];

    public function __construct(
        private TypeHintIndex $typeHintIndex,
        private NamespaceResolver $namespaceResolver
    )
    {
    }

    public function rewrite(string $classDefinition): string
    {
        $this->reset();

        $this->namespaceResolver->analyse($classDefinition);

        $tokens = Token::getAll($classDefinition);
        $strippedTokens = $this->stripTypeHints($tokens);

        return join('', array_map(fn(Token $token) => $token->asString(), $strippedTokens));
    }

    private function reset(): void
    {
        $this->state = self::STATE_DEFAULT;
        $this->currentClass = '';
        $this->currentFunction = '';
    }

    /** @param list<Token> $tokens */
    private function stripTypeHints(array $tokens): array
    {
        foreach ($tokens as $index => $token) {

            if ($token->equals('{')) {
                $this->currentBodyLevel++;
            }
            elseif ($token->equals('}')) {
                $this->currentBodyLevel--;
            }

            switch ($this->state) {
                case self::STATE_READING_ARGUMENTS:
                    if ($token->equals(')')) {
                        $this->state = self::STATE_READING_CLASS;
                    }
                    elseif ($token->hasType(T_VARIABLE)) {
                        $this->extractTypehints($tokens, $index, $token);
                    }
                    break;
                case self::STATE_READING_FUNCTION:
                    if ($token->equals('(')) {
                        $this->state = self::STATE_READING_ARGUMENTS;
                    }
                    elseif ($token->hasType(T_STRING) && !$this->currentFunction) {
                        $this->currentFunction = $token->asString();
                    }
                    break;
                case self::STATE_READING_CLASS:
                    if ($token->equals('{') && $this->currentFunction) {
                        $this->state = self::STATE_READING_FUNCTION_BODY;
                        $this->currentBodyLevel = 1;
                    }
                    elseif ($token->equals('}') && $this->currentClass) {
                        $this->state = self::STATE_DEFAULT;
                        $this->currentClass = '';
                    }
                    elseif ($token->hasType(T_STRING) && !$this->currentClass && $this->shouldExtractTokensOfClass($token->asString())) {
                        $this->currentClass = $token->asString();
                    }
                    elseif ($token->hasType( T_FUNCTION) && $this->currentClass) {
                        $this->state = self::STATE_READING_FUNCTION;
                    }
                    break;
                case self::STATE_READING_FUNCTION_BODY:
                    if ($token->equals('}') && $this->currentBodyLevel === 0) {
                        $this->currentFunction = '';
                        $this->state = self::STATE_READING_CLASS;
                    }

                    break;
                default:
                    if ($token->hasType( T_CLASS)) {
                        $this->state = self::STATE_READING_CLASS;
                    }
            }
        }

        return $tokens;
    }

    private function extractTypehints(array &$tokens, int $variableNameIndex, Token $variableName): void
    {
        $typehint = '';
        for ($i = $variableNameIndex - 1; !$this->haveNotReachedEndOfTypeHint($tokens[$i]); $i--) {
            $scanningToken = $tokens[$i];
            $typehint = $scanningToken->asString() . $typehint;

            if (!$scanningToken->hasType(T_WHITESPACE)) {
                unset($tokens[$i]);
            }
        }

        if ($typehint = trim($typehint)) {

            $class = $this->namespaceResolver->resolve($this->currentClass);

            if (\strpos($typehint, '|') !== false) {
                $this->typeHintIndex->addInvalid(
                    $class,
                    trim($this->currentFunction),
                    $variableName->asString(),
                    new DisallowedUnionTypehintException("Union type $typehint cannot be used to create a double")
                );

                return;
            }

            if (\strpos($typehint, '&') !== false) {
                $this->typeHintIndex->addInvalid(
                    $class,
                    trim($this->currentFunction),
                    $variableName->asString(),
                    new DisallowedUnionTypehintException("Intersection type $typehint cannot be used to create a double")
                );

                return;
            }

            try {
                $typehintFcqn = $this->namespaceResolver->resolve($typehint);
                $this->typeHintIndex->add(
                    $class,
                    trim($this->currentFunction),
                    $variableName->asString(),
                    $typehintFcqn
                );
            } catch (DisallowedNonObjectTypehintException $e) {
                $this->typeHintIndex->addInvalid(
                    $class,
                    trim($this->currentFunction),
                    $variableName->asString(),
                    $e
                );
            }
        }
    }

    private function haveNotReachedEndOfTypeHint(Token $token) : bool
    {
        if ($token->equals('|') || $token->equals('&')) {
            return false;
        }

        return !$token->isInTypes($this->typehintTokens);
    }

    private function shouldExtractTokensOfClass(string $className): bool
    {
        return substr($className, -4) == 'Spec';
    }
}
