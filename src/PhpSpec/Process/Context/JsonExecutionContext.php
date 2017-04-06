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

namespace PhpSpec\Process\Context;

final class JsonExecutionContext implements ExecutionContext
{
    const ENV_NAME = 'PHPSPEC_EXECUTION_CONTEXT';
    /**
     * @var array
     */
    private $generatedTypes;

    /**
     * @param array $env
     *
     * @return JsonExecutionContext
     */
    public static function fromEnv($env)
    {
        $executionContext = new JsonExecutionContext();

        if (array_key_exists(self::ENV_NAME, $env)) {
            $serialized = json_decode($env[self::ENV_NAME], true);
            $executionContext->generatedTypes = $serialized['generated-types'];
        }
        else {
            $executionContext->generatedTypes = array();
        }

        return $executionContext;
    }

    /**
     * @param string $generatedType
     */
    public function addGeneratedType($generatedType)
    {
        $this->generatedTypes[] = $generatedType;
    }

    /**
     * @return array
     */
    public function getGeneratedTypes()
    {
        return $this->generatedTypes;
    }

    /**
     * @return string
     */
    public function asEnv()
    {
        return array(
            self::ENV_NAME => json_encode(
                array(
                    'generated-types' => $this->generatedTypes
                )
            )
        );
    }
}
