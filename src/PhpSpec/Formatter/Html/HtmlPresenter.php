<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Formatter\Presenter\StringPresenter;
use Exception;
use PhpSpec\Exception\Exception as PhpSpecException;

class HtmlPresenter extends StringPresenter
{
    public function presentException(Exception $exception, $verbose = false)
    {
        if ($exception instanceof PhpSpecException) {
            list($file, $line) = $this->getExceptionExamplePosition($exception);
            return $this->presentFileCode($file, $line);
        }
    }

    protected function presentFileCode($file, $lineno, $context = 6)
    {
        $lines  = explode("\n", file_get_contents($file));
        $offset = max(0, $lineno - ceil($context / 2));
        $lines  = array_slice($lines, $offset, $context);

        $text = "\n";
        foreach ($lines as $line) {
            $offset++;

            if ($offset == $lineno) {
                $cssClass = "offending";
            } else {
                $cssClass = "normal";
            }
            $text .= '<span class="linenum">'.$offset.'</span><span class="' .
                     $cssClass . '">'.$line.'</span>';

            $text .= "\n";
        }

        return $text;
    }
}
