<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Formatter\Template as TemplateInterface;

use PhpSpec\IO\IOInterface;

class Template implements TemplateInterface
{
    const DIR = __DIR__;

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    public function render($text, array $templateVars = array())
    {
        if (file_exists($text)) {
            $text = file_get_contents($text);
        }
        $templateKeys = $this->extractKeys($templateVars);
        $output = str_replace($templateKeys, array_values($templateVars), $text);
        $this->io->write($output);
    }

    private function extractKeys($templateVars)
    {
        return array_map(function($e) {
            return '{' . $e . '}';
        }, array_keys($templateVars));
    }
}