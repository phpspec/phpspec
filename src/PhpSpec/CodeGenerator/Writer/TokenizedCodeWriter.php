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

use PhpSpec\Util\ClassFileAnalyser;

final class TokenizedCodeWriter implements CodeWriter
{
    /**
     * @var ClassFileAnalyser
     */
    private $analyser;

    /**
     * @param ClassFileAnalyser $analyser
     */
    public function __construct(ClassFileAnalyser $analyser = null)
    {
        $this->analyser = $analyser ?: new ClassFileAnalyser();
    }

    /**
     * @param string $class
     * @param string $method
     * @return string
     */
    public function insertMethodFirstInClass($class, $method)
    {
        if (!$this->analyser->classHasMethods($class)) {
            return $this->writeAtEndOfClass($class, $method);
        }

        $line = $this->analyser->getStartLineOfFirstMethod($class);

        return $this->insertStringBeforeLine($class, $method, $line);
    }

    /**
     * @param string $class
     * @param string $method
     * @return string
     */
    public function insertMethodLastInClass($class, $method)
    {
        if ($this->analyser->classHasMethods($class)) {
            $line = $this->analyser->getEndLineOfLastMethod($class);
            return $this->insertStringAfterLine($class, $method, $line);
        }

        return $this->writeAtEndOfClass($class, $method);
    }

    /**
     * @param string $class
     * @param string $methodName
     * @param string $method
     * @return string
     */
    public function insertAfterMethod($class, $methodName, $method)
    {
        $line = $this->analyser->getEndLineOfNamedMethod($class, $methodName);

        return $this->insertStringAfterLine($class, $method, $line);
    }

    /**
     * @param string $target
     * @param string $toInsert
     * @param int $line
     * @param bool $leadingNewline
     * @return string
     */
    private function insertStringAfterLine($target, $toInsert, $line, $leadingNewline = true)
    {
        $lines = explode("\n", $target);
        $lastLines = array_slice($lines, $line);
        $toInsert = trim($toInsert, "\n\r");
        if ($leadingNewline) {
            $toInsert = PHP_EOL . $toInsert;
        }
        array_unshift($lastLines, $toInsert);
        array_splice($lines, $line, count($lines), $lastLines);
        return implode(PHP_EOL, $lines);
    }

    /**
     * @param string $target
     * @param string $toInsert
     * @param int $line
     * @return string
     */
    private function insertStringBeforeLine($target, $toInsert, $line)
    {
        $line--;
        $lines = explode("\n", $target);
        $lastLines = array_slice($lines, $line);
        array_unshift($lastLines, trim($toInsert, "\n\r") . PHP_EOL);
        array_splice($lines, $line, count($lines), $lastLines);
        return implode(PHP_EOL, $lines);
    }

    /**
     * @param string $class
     * @param string $method
     * @param bool $prependNewLine
     * @return string
     */
    private function writeAtEndOfClass($class, $method, $prependNewLine = false)
    {
        $tokens = token_get_all($class);
        $searching = false;
        $searchPattern = array();

        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if ($token === '}') {
                $searching = true;
                continue;
            }

            if (!$searching) {
                continue;
            }

            if ($this->isWritePoint($token)) {
                $line = $token[2];
                return $this->insertStringAfterLine($class, $method, $line, $token[0] === T_COMMENT ?: $prependNewLine);
            }

            array_unshift($searchPattern, is_array($token) ? $token[1] : $token);

            if ($token === '{') {
                $search = implode('', $searchPattern);
                $position = strpos($class, $search) + strlen($search) - 1;
                return substr_replace($class, PHP_EOL . $method . PHP_EOL, $position, 0);
            }
        }
    }

    /**
     * @param $token
     * @return bool
     */
    private function isWritePoint($token)
    {
        return is_array($token) && ($token[1] === PHP_EOL || $token[0] === T_COMMENT);
    }
}
