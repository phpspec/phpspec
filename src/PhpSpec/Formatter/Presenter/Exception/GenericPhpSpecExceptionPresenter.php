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

namespace PhpSpec\Formatter\Presenter\Exception;

final class GenericPhpSpecExceptionPresenter extends AbstractPhpSpecExceptionPresenter implements PhpSpecExceptionPresenter
{
    /**
     * @var ExceptionElementPresenter
     */
    private $exceptionElementPresenter;

    /**
     * @param ExceptionElementPresenter $exceptionElementPresenter
     */
    public function __construct(ExceptionElementPresenter $exceptionElementPresenter)
    {
        $this->exceptionElementPresenter = $exceptionElementPresenter;
    }

    /**
     * @param string  $file
     * @param integer $lineno
     * @param integer $context
     *
     * @return string
     */
    protected function presentFileCode($file, $lineno, $context = 6)
    {
        $lines  = explode(PHP_EOL, file_get_contents($file));
        $offset = max(0, $lineno - ceil($context / 2));
        $lines  = array_slice($lines, $offset, $context);

        $text = PHP_EOL;
        foreach ($lines as $line) {
            $offset++;

            if ($offset == $lineno) {
                $text .= $this->exceptionElementPresenter->presentHighlight(sprintf('%4d', $offset).' '.$line);
            } else {
                $text .= $this->exceptionElementPresenter->presentCodeLine(sprintf('%4d', $offset), $line);
            }

            $text .= PHP_EOL;
        }

        return $text;
    }
}
