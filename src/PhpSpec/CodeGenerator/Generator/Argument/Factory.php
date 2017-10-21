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
