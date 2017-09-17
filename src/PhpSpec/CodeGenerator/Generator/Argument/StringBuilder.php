<?php

namespace PhpSpec\CodeGenerator\Generator\Argument;

class StringBuilder
{
    /**
     * @param \ReflectionParameter[] $parameters
     *
     * @return string
     */
    public function buildFromReflectionParameters(array $parameters) : string
    {
        return implode(', ', array_map(function (\ReflectionParameter $parameter) {
            return $this->buildArgument($parameter);
        }, $parameters));
    }

    private function buildArgument(\ReflectionParameter $parameter) : string
    {
        $parameterName = '$' . $parameter->getName();

        $type = $parameter->getType();

        switch (true) {
            case (class_exists($type) || interface_exists($type)):
                $typeHint = sprintf('\\%s ', $type);
                break;
            case (strlen($type) > 0):
                $typeHint = $type . ' ';
                break;
            default:
                $typeHint = '';
        }

        $nullableOperator = $parameter->allowsNull() && (strlen($typeHint) > 0) && !$parameter->isDefaultValueAvailable() ? '?' : '';
        $defaultValueString = $this->getDefaultValueStringFromParameter($parameter);

        return $nullableOperator . $typeHint . $parameterName . $defaultValueString;
    }

    private function getDefaultValueStringFromParameter(\ReflectionParameter $parameter) : string
    {
        $type = $parameter->getType();

        return $type && $parameter->isDefaultValueAvailable() ? sprintf(' = %s', strtolower(gettype($parameter->getDefaultValue()))) : '';
    }
}
