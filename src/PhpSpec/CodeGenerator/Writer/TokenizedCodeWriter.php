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

namespace PhpSpec\CodeGenerator\Writer;

use PhpSpec\Exception\Generator\GenerationFailed;
use PhpSpec\Util\ClassFileAnalyser;
use PhpSpec\Util\Token;

final class TokenizedCodeWriter implements CodeWriter
{
    public function __construct(
        private ClassFileAnalyser $analyser
    )
    {
    }

    public function insertMethodFirstInClass(string $class, string $method): string
    {
        if (!$this->analyser->classHasMethods($class)) {
            return $this->writeAtEndOfClass($class, $method);
        }

        $line = $this->analyser->getStartLineOfFirstMethod($class);

        return $this->insertStringBeforeLine($class, $method, $line);
    }

    public function insertMethodLastInClass(string $class, string $method): string
    {
        if ($this->analyser->classHasMethods($class)) {
            $line = $this->analyser->getEndLineOfLastMethod($class);
            return $this->insertStringAfterLine($class, $method, $line);
        }

        return $this->writeAtEndOfClass($class, $method);
    }

    public function insertAfterMethod(string $class, string $methodName, string $method): string
    {
        $line = $this->analyser->getEndLineOfNamedMethod($class, $methodName);

        return $this->insertStringAfterLine($class, $method, $line);
    }

    private function insertStringAfterLine(string $target, string $toInsert, int $line, bool $leadingNewline = true): string
    {
        $lines = explode("\n", $target);
        $lastLines = \array_slice($lines, $line);
        $toInsert = trim($toInsert, "\n\r");
        if ($leadingNewline) {
            $toInsert = "\n" . $toInsert;
        }
        array_unshift($lastLines, $toInsert);
        array_splice($lines, $line, \count($lines), $lastLines);

        return implode("\n", $lines);
    }

    private function insertStringBeforeLine(string $target, string $toInsert, int $line): string
    {
        $line--;
        $lines = explode("\n", $target);
        $lastLines = \array_slice($lines, $line);
        array_unshift($lastLines, trim($toInsert, "\n\r") . "\n");
        array_splice($lines, $line, \count($lines), $lastLines);

        return implode("\n", $lines);
    }

    private function writeAtEndOfClass(string $class, string $method): string
    {
        $tokens = Token::getAll($class);
        $searching = false;
        $inString = false;
        $searchPattern = array();

        for ($i = \count($tokens) - 1; $i >= 0; $i--) {
            $parsedToken = $tokens[$i];

            if ($parsedToken->equals('}') && !$inString) {
                $searching = true;
                continue;
            }

            if (!$searching) {
                continue;
            }

            if ($parsedToken->equals('"')) {
                $inString = !$inString;
                continue;
            }

            if ($this->isWritePoint($parsedToken)) {
                $line = (int) $parsedToken->getLine();
                $prependNewLine = $parsedToken->hasType(T_COMMENT) || ($i != 0 && $tokens[$i-1]->hasType(T_COMMENT));
                return $this->insertStringAfterLine($class, $method, $line, $prependNewLine);
            }

            array_unshift($searchPattern, $parsedToken->asString());

            if ($parsedToken->equals('{')) {
                $search = implode('', $searchPattern);
                $position = strpos($class, $search) + \strlen($search) - 1;

                return substr_replace($class, "\n" . $method . "\n", $position, 0);
            }
        }

        throw new GenerationFailed('Could not locate end of class');
    }

    private function isWritePoint(Token $token): bool
    {
        return $token->equals("\n") || $token->hasType(T_COMMENT);
    }
}
