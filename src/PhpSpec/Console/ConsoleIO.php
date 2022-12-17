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

namespace PhpSpec\Console;

use PhpSpec\IO\IO;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpSpec\Config\OptionsConfig;

/**
 * Class ConsoleIO deals with input and output from command line interaction
 */
class ConsoleIO implements IO
{
    private const COL_MIN_WIDTH = 40;
    private const COL_DEFAULT_WIDTH = 60;
    private const COL_MAX_WIDTH = 80;

    private ?string $lastMessage = null;

    private bool $hasTempString = false;

    private ?int $consoleWidth = null;

    public function __construct(private InputInterface $input, private OutputInterface $output, private OptionsConfig $config, private Prompter $prompter)
    {
    }

    public function isInteractive(): bool
    {
        return $this->input->isInteractive();
    }

    public function isDecorated(): bool
    {
        return $this->output->isDecorated();
    }

    public function isCodeGenerationEnabled(): bool
    {
        if (!$this->isInteractive()) {
            return false;
        }

        return $this->config->isCodeGenerationEnabled()
            && !$this->input->getOption('no-code-generation');
    }

    public function isStopOnFailureEnabled(): bool
    {
        return $this->config->isStopOnFailureEnabled()
            || $this->input->getOption('stop-on-failure');
    }

    public function isVerbose(): bool
    {
        return $this->config->isVerbose()
            || OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity();
    }

    public function getLastWrittenMessage(): string
    {
        return $this->lastMessage ?? '';
    }

    public function writeln(string $message = '', int $indent = null): void
    {
        $this->write($message, $indent, true);
    }

    public function writeTemp(string $message, int $indent = null): void
    {
        $this->write($message, $indent);
        $this->hasTempString = true;
    }

    public function cutTemp(): ?string
    {
        if (false === $this->hasTempString) {
            return null;
        }

        $message = $this->lastMessage;
        $this->write('');

        return $message;
    }

    public function freezeTemp(): void
    {
        $this->write($this->lastMessage ?? '');
    }

    public function write(string $message, int $indent = null, bool $newline = false): void
    {
        if ($this->hasTempString) {
            $this->hasTempString = false;
            $this->overwrite($message, $indent, $newline);

            return;
        }

        if (null !== $indent) {
            $message = $this->indentText($message, $indent);
        }

        $this->output->write($message, $newline);
        $this->lastMessage = $message.($newline ? "\n" : '');
    }

    public function overwriteln(string $message = '', int $indent = null): void
    {
        $this->overwrite($message, $indent, true);
    }

    public function overwrite(string $message, int $indent = null, bool $newline = false): void
    {
        if (null !== $indent) {
            $message = $this->indentText($message, $indent);
        }

        if ($message === $this->lastMessage) {
            return;
        }

        $commonPrefix = $this->getCommonPrefix($message, $this->lastMessage ?? '');
        $newSuffix = substr($message, \strlen($commonPrefix));
        $oldSuffix = substr($this->lastMessage ?? '', \strlen($commonPrefix));

        $overwriteLength = \strlen(strip_tags($oldSuffix));

        $this->write(str_repeat("\x08", $overwriteLength));
        $this->write($newSuffix);

        $fill = $overwriteLength - \strlen(strip_tags($newSuffix));
        if ($fill > 0) {
            $this->write(str_repeat(' ', $fill));
            $this->write(str_repeat("\x08", $fill));
        }

        if ($newline) {
            $this->writeln();
        }

        $this->lastMessage = $message.($newline ? "\n" : '');
    }

    private function getCommonPrefix(string $stringA, string $stringB): string
    {
        for ($i = 0, $len = min(\strlen($stringA), \strlen($stringB)); $i<$len; $i++) {
            if ($stringA[$i] != $stringB[$i]) {
                break;
            }
        }

        $common = substr($stringA, 0, $i);

        if (preg_match('/(^.*)<[a-z-]*>?[^<]*$/', $common, $matches)) {
            $common = $matches[1];
        }

        return $common;
    }

    public function askConfirmation(string $question, bool $default = true): bool
    {
        $lines   = array();
        $lines[] = '<question>'.str_repeat(' ', $this->getBlockWidth())."</question>";
        foreach (explode("\n", wordwrap($question, $this->getBlockWidth() - 4, "\n", true)) as $line) {
            $lines[] = '<question>  '.str_pad($line, $this->getBlockWidth() - 2).'</question>';
        }
        $lines[] = '<question>'.str_repeat(' ', $this->getBlockWidth() - 8).'</question> <value>'.
            ($default ? '[Y/n]' : '[y/N]').'</value> ';

        $formattedQuestion = implode("\n", $lines) . "\n";

        return $this->prompter->askConfirmation($formattedQuestion, $default);
    }

    private function indentText(string $text, int $indent): string
    {
        return implode("\n", array_map(
            fn($line) => str_repeat(' ', $indent).$line,
            explode("\n", $text)
        ));
    }

    public function isRerunEnabled(): bool
    {
        return !$this->input->getOption('no-rerun') && $this->config->isReRunEnabled();
    }

    public function isFakingEnabled(): bool
    {
        return $this->input->getOption('fake') || $this->config->isFakingEnabled();
    }

    public function getBootstrapPath(): ?string
    {
        if ($path = $this->input->getOption('bootstrap')) {
            return (string) $path;
        }

        if ($path = $this->config->getBootstrapPath()) {
            return $path;
        }

        return null;
    }

    public function setConsoleWidth(int $width): void
    {
        $this->consoleWidth = $width;
    }

    public function getBlockWidth(): int
    {
        $width = self::COL_DEFAULT_WIDTH;
        if ($this->consoleWidth && ($this->consoleWidth - 10) > self::COL_MIN_WIDTH) {
            $width = $this->consoleWidth - 10;
        }
        if ($width > self::COL_MAX_WIDTH) {
            $width = self::COL_MAX_WIDTH;
        }
        return $width;
    }

    
    public function writeBrokenCodeBlock(string $message, int $indent = 0): void
    {
        $message = wordwrap($message, $this->getBlockWidth() - ($indent * 2), "\n", true);

        if ($indent) {
            $message = $this->indentText($message, $indent);
        }

        $this->output->writeln("<broken-bg>".str_repeat(" ", $this->getBlockWidth())."</broken-bg>");

        foreach (explode("\n", $message) as $line) {
            $this->output->writeln("<broken-bg>".str_pad($line, $this->getBlockWidth(), ' ')."</broken-bg>");
        }

        $this->output->writeln("<broken-bg>".str_repeat(" ", $this->getBlockWidth())."</broken-bg>");
        $this->output->writeln('');
    }
}
