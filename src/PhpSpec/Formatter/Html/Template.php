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

namespace PhpSpec\Formatter\Html;

use PhpSpec\Formatter\Template as TemplateInterface;

use PhpSpec\IO\IOInterface;

/**
 * Class Template
 * @package PhpSpec\Formatter\Html
 */
class Template implements TemplateInterface
{
    /**
     *
     */
    const DIR = __DIR__;

    /**
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    /**
     * @param $text
     * @param array $templateVars
     */
    public function render($text, array $templateVars = array())
    {
        if (file_exists($text)) {
            $text = file_get_contents($text);
        }
        $templateKeys = $this->extractKeys($templateVars);
        $output = str_replace($templateKeys, array_values($templateVars), $text);
        $this->io->write($output);
    }

    /**
     * @param $templateVars
     * @return array
     */
    private function extractKeys($templateVars)
    {
        return array_map(function ($e) {
            return '{' . $e . '}';
        }, array_keys($templateVars));
    }
}
