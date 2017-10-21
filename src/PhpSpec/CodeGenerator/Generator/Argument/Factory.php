<?php

namespace PhpSpec\CodeGenerator\Generator\Argument;

class Factory
{
    /**
     * @param \ReflectionParameter[] $reflectionParams
     *
     * @return Argument[]
     */
    public function fromReflectionParams(array $reflectionParams): array
    {
        $arguments = [];

        foreach ($reflectionParams as $reflectionParam) {
            $arguments[] = $this->createArgumentFromParam($reflectionParam);
        }

        return $arguments;
    }

    /**
     * Creates numbered arguments from their values.
     *
     * Useful if we do not know anything about the arguments other than their
     * call-time values.
     *
     * @param mixed[] $argumentValues
     *
     * @return Argument[]
     */
    public function fromValues(array $argumentValues): array
    {
        $arguments = [];

        foreach ($argumentValues as $i => $argumentValue) {
            $arguments[] = new Argument(sprintf('argument%d', $i + 1), '');
        }

        return $arguments;
    }

    private function createArgumentFromParam(\ReflectionParameter $reflectionParam): Argument
    {
        $type = $reflectionParam->getType();

        if ($reflectionParam->getClass()) {
            $type = $reflectionParam->getClass();
        }

        $argument = new Argument($reflectionParam->getName(), $type);

        if ($reflectionParam->isDefaultValueAvailable()) {
            $argument->setDefaultValue($reflectionParam->getDefaultValue());
        }

        return $argument;
    }
}
