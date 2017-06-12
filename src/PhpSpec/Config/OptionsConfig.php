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

class OptionsConfig
{
    /**
     * @var bool
     */
    private $stopOnFailureEnabled;

    /**
     * @var bool
     */
    private $codeGenerationEnabled;

    /**
     * @var bool
     */
    private $reRunEnabled;

    /**
     * @var bool
     */
    private $fakingEnabled;
    /**
     * @var string|bool
     */
    private $bootstrapPath;

    /**
     * @param bool $stopOnFailureEnabled
     * @param bool $codeGenerationEnabled
     * @param bool $reRunEnabled
     * @param bool $fakingEnabled
     * @param string|bool $bootstrapPath
     */
    public function __construct(
        bool $stopOnFailureEnabled,
        bool $codeGenerationEnabled,
        bool $reRunEnabled,
        bool $fakingEnabled,
        $bootstrapPath
    ) {
        $this->stopOnFailureEnabled  = $stopOnFailureEnabled;
        $this->codeGenerationEnabled = $codeGenerationEnabled;
        $this->reRunEnabled = $reRunEnabled;
        $this->fakingEnabled = $fakingEnabled;
        $this->bootstrapPath = $bootstrapPath;
    }

    /**
     * @return bool
     */
    public function isStopOnFailureEnabled(): bool
    {
        return $this->stopOnFailureEnabled;
    }

    /**
     * @return bool
     */
    public function isCodeGenerationEnabled(): bool
    {
        return $this->codeGenerationEnabled;
    }

    public function isReRunEnabled()
    {
        return $this->reRunEnabled;
    }

    public function isFakingEnabled()
    {
        return $this->fakingEnabled;
    }

    public function getBootstrapPath()
    {
        return $this->bootstrapPath;
    }
}
