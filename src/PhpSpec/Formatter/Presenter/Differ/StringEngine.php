<?php

namespace PhpSpec\Formatter\Presenter\Differ;

class StringEngine implements EngineInterface
{
    public function supports($expected, $actual)
    {
        return is_string($expected) && is_string($actual);
    }

    public function compare($expected, $actual)
    {
        $expected = explode(PHP_EOL, (string) $expected);
        $actual   = explode(PHP_EOL, (string) $actual);

        $diff = new \Diff($expected, $actual, array());

        $renderer = new \Diff_Renderer_Text_Unified;
        $text = $diff->render($renderer);

        $lines = array();
        foreach (explode("\n", $text) as $line) {
            if (0 === strpos($line, '-')) {
                $lines[] = sprintf('<diff-del>%s</diff-del>', $line);
            } elseif (0 === strpos($line, '+')) {
                $lines[] = sprintf('<diff-add>%s</diff-add>', $line);
            } else {
                $lines[] = $line;
            }
        }

        return sprintf("<code>%s%s</code>", PHP_EOL, implode(PHP_EOL, $lines));
    }
}
