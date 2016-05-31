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

namespace PhpSpec\Config;

use Symfony\Component\Console\Input\InputInterface;

class OptionsConfig
{
    /**
     * @var array
     */
    private $optionsArray;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @param array          $optionsArray
     * @param InputInterface $input
     */
    public function __construct(array $optionsArray, InputInterface $input)
    {
        $this->optionsArray = $optionsArray;
        $this->input = $input;
    }

    public function isStopOnFailureEnabled()
    {
        return isset($this->optionsArray['stop_on_failure']) ? $this->optionsArray['stop_on_failure'] : false;
    }

    public function isCodeGenerationEnabled()
    {
        return isset($this->optionsArray['code_generation']) ? $this->optionsArray['code_generation'] : true;
    }

    public function isReRunEnabled()
    {
        if ($this->input->hasOption('no-rerun') && $this->input->getOption('no-rerun')) {
            return $this->input->getOption('no-rerun');
        }
        return isset($this->optionsArray['rerun']) ? $this->optionsArray['rerun'] : true;
    }

    public function isFakingEnabled()
    {
        return isset($this->optionsArray['fake']) ? $this->optionsArray['fake'] : false;
    }

    public function getBootstrapPath()
    {
        return isset($this->optionsArray['bootstrap']) ? $this->optionsArray['bootstrap'] : false;
    }

    public function getExtensions()
    {
        return isset($this->optionsArray['extensions']) ? $this->optionsArray['extensions'] : [];
    }

    public function getFormatterName()
    {
        if ($this->input->hasOption('format') && $this->input->getOption('format')) {
            return $this->input->getOption('format');
        }
        return isset($this->optionsArray['formatter.name']) ? $this->optionsArray['formatter.name'] : 'progress';
    }

    public function getCodeGeneratorTemplatePaths()
    {
        if (isset($this->optionsArray['code_generator.templates.paths'])) {
            return $this->optionsArray['code_generator.templates.paths'];
        }

        if (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            $home = $_SERVER['HOMEDRIVE'].$_SERVER['HOMEPATH'];
        } else {
            $home = getenv('HOME');
        }

        return [
            rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
            rtrim($home, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
        ];
    }

    public function getSuites()
    {
        return isset($this->optionsArray['suites']) ? $this->optionsArray['suites'] : ['main' => ''];
    }

    public function getErrorLevel()
    {
        return isset($this->optionsArray['runner.maintainers.errors.level']) ? $this->optionsArray['runner.maintainers.errors.level'] : E_ALL ^ E_STRICT;
    }
}