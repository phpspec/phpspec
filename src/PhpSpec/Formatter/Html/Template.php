<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Formatter\Template as TemplateInterface;

class Template implements TemplateInterface
{

    public function render($text, array $templateVars = array())
    {
        if (file_exists($text)) {
            $text = file_get_contents($text);
        }
        $templateKeys = $this->extractKeys($templateVars);
        return str_replace($templateKeys, array_values($templateVars), $text);
    }

    private function extractKeys($templateVars)
    {
        return array_map(function($e) {
            return '{' . $e . '}';
        }, array_keys($templateVars));
    }
}