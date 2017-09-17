<?php

namespace PhpSpec\CodeGenerator\Generator\Argument;

class StringBuilder
{
    /**
     * @param \ReflectionParameter[] $parameters
     *
     * @return string
     */
    public function buildFromReflectionParameters(array $parameters): string
    {
        return implode(', ', array_map(function (\ReflectionParameter $parameter) {
            return $this->buildArgument($parameter);
        }, $parameters));
    }

    private function buildArgument(\ReflectionParameter $parameter): string
    {
        $parameterName = '$' . $parameter->getName();

        $typeHint = $parameter->getType() ? sprintf('\\%s ', $parameter->getType()->getName()) : '';

        return $typeHint . $parameterName;
    }
}
