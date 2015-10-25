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


interface ExceptionElementPresenter
{
    /**
     * @param \Exception $exception
     * @return string
     */
    public function presentExceptionThrownMessage(\Exception $exception);

    /**
     * @param string $number
     * @param string $line
     * @return string
     */
    public function presentCodeLine($number, $line);

    /**
     * @param string $line
     * @return string
     */
    public function presentHighlight($line);

    /**
     * @param string $header
     * @return string
     */
    public function presentExceptionTraceHeader($header);

    /**
     * @param string $class
     * @param string $type
     * @param string $method
     * @param array $args
     * @return string
     */
    public function presentExceptionTraceMethod($class, $type, $method, array $args);

    /**
     * @param string $function
     * @param array $args
     * @return string
     */
    public function presentExceptionTraceFunction($function, array $args);
}
