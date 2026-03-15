<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Report;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders PHP view templates by extracting variables into scope and requiring the template file.
 * Provides a write() method that templates can use to output content to the console.
 */
final readonly class TemplateRenderer
{
    /**
     * @param string $pathToTemplates absolute path to the directory containing view templates
     * @param OutputInterface $output the console output to write to
     */
    public function __construct(
        private string $pathToTemplates,
        private OutputInterface $output,
    ) {}

    /**
     * Renders a named template by requiring its .view.php file with extracted variables.
     *
     * @param string $template the template name (without .view.php extension)
     * @param array $vars associative array of variables to make available in the template scope
     */
    public function render(string $template, array $vars): void
    {
        foreach ($vars as $key => $value) {
            $$key = $value;
        }

        require(
            $this->pathToTemplates .
            DIRECTORY_SEPARATOR .
            $template . '.view.php');
    }

    /**
     * Writes text to the console output without a trailing newline.
     *
     * @param string $text the text to output
     */
    public function write($text): void
    {
        $this->output->write($text);
    }
}
