<?php

namespace PhpSpec\CodeGenerator\Generator\Argument;

class StringBuilder
{
    const NULLABLE_OPERATOR = '?';

    /**
     * @param \ReflectionParameter[] $parameters
     *
     * @return string
     */
    public function buildFromReflectionParameters(array $parameters, string $targetClassNamespace) : string
    {
        return implode(', ', array_map(function (\ReflectionParameter $parameter) use ($targetClassNamespace) {
            return $this->buildArgument($parameter, $targetClassNamespace);
        }, $parameters));
    }

    private function buildArgument(\ReflectionParameter $parameter, string $targetClassNamespace) : string
    {
        $parameterName = '$' . $parameter->getName();

        $type = $parameter->getType();

        switch (true) {
            case (class_exists($type) || interface_exists($type)):
                $typeHint = $this->getResolvedClassTypeHint($parameter->getClass(), $targetClassNamespace) . ' ';
                break;
            case (strlen($type) > 0):
                $typeHint = $type . ' ';
                break;
            default:
                $typeHint = '';
        }

        $nullableOperator = $this->getNullableOperator($parameter, $typeHint);
        $defaultValueString = $this->getDefaultValueStringFrom($parameter);

        return $nullableOperator . $typeHint . $parameterName . $defaultValueString;
    }

    private function getDefaultValueStringFrom(\ReflectionParameter $parameter) : string
    {
        $type = $parameter->getType();

        return $type && $parameter->isDefaultValueAvailable() ? sprintf(' = %s', strtolower(gettype($parameter->getDefaultValue()))) : '';
    }

    private function getNullableOperator(\ReflectionParameter $parameter, $typeHint) : string
    {
        return $parameter->allowsNull() &&
                (strlen($typeHint) > 0) && !$parameter->isDefaultValueAvailable() ? self::NULLABLE_OPERATOR : '';
    }

    private function getResolvedClassTypeHint(\ReflectionClass $class, string $targetClassNamespace) : string
    {
        if ($class->getNamespaceName() === $targetClassNamespace) {
            return $class->getShortName();
        }

        return sprintf('\\%s', $class->getName());
    }
}
