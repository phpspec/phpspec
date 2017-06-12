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

use PhpSpec\Formatter\Presenter\Value\ExceptionTypePresenter;
use PhpSpec\Formatter\Presenter\Value\ValuePresenter;

final class TaggingExceptionElementPresenter implements ExceptionElementPresenter
{
    /**
     * @var ExceptionTypePresenter
     */
    private $exceptionTypePresenter;

    /**
     * @var ValuePresenter
     */
    private $valuePresenter;

    /**
     * @param ExceptionTypePresenter $exceptionTypePresenter
     * @param ValuePresenter $valuePresenter
     */
    public function __construct(ExceptionTypePresenter $exceptionTypePresenter, ValuePresenter $valuePresenter)
    {
        $this->exceptionTypePresenter = $exceptionTypePresenter;
        $this->valuePresenter = $valuePresenter;
    }

    /**
     * @param \Exception $exception
     * @return string
     */
    public function presentExceptionThrownMessage(\Exception $exception): string
    {
        return sprintf(
            'Exception <label>%s</label> has been thrown.',
            $this->exceptionTypePresenter->present($exception)
        );
    }

    /**
     * @param string $number
     * @param string $line
     * @return string
     */
    public function presentCodeLine(string $number, string $line): string
    {
        return sprintf('<lineno>%s</lineno> <code>%s</code>', $number, $line);
    }

    /**
     * @param string $line
     * @return string
     */
    public function presentHighlight(string $line): string
    {
        return sprintf('<hl>%s</hl>', $line);
    }

    /**
     * @param string $header
     * @return string
     */
    public function presentExceptionTraceHeader(string $header): string
    {
        return sprintf('<trace>%s</trace>', $header);
    }

    /**
     * @param string $class
     * @param string $type
     * @param string $method
     * @param array $args
     * @return string
     */
    public function presentExceptionTraceMethod(string $class, string $type, string $method, array $args): string
    {
        $template = '   <trace><trace-class>%s</trace-class><trace-type>%s</trace-type>'.
            '<trace-func>%s</trace-func>(<trace-args>%s</trace-args>)</trace>';

        return sprintf($template, $class, $type, $method, $this->presentExceptionTraceArguments($args));
    }

    /**
     * @param string $function
     * @param array $args
     * @return string
     */
    public function presentExceptionTraceFunction(string $function, array $args): string
    {
        $template = '   <trace><trace-func>%s</trace-func>(<trace-args>%s</trace-args>)</trace>';

        return sprintf($template, $function, $this->presentExceptionTraceArguments($args));
    }

    /**
     * @param array $args
     * @return string
     */
    private function presentExceptionTraceArguments(array $args): string
    {
        $valuePresenter = $this->valuePresenter;

        $taggedArgs = array_map(function ($arg) use ($valuePresenter) {
            return sprintf('<value>%s</value>', $valuePresenter->presentValue($arg));
        }, $args);

        return implode(', ', $taggedArgs);
    }
}
