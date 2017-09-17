<?php

namespace PhpSpec\CodeGenerator\Generator\Argument;

class StringBuilder
{
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

        $defaultValueString = $this->getDefaultValueStringFrom($parameter);

        return $typeHint . $parameterName . $defaultValueString;
    }

    private function getDefaultValueStringFrom(\ReflectionParameter $parameter) : string
    {
        $type = $parameter->getType();

        return $type && $parameter->isDefaultValueAvailable() ? sprintf(' = %s', strtolower(gettype($parameter->getDefaultValue()))) : '';
    }

    private function getResolvedClassTypeHint(\ReflectionClass $class, string $targetClassNamespace) : string
    {
        if ($class->getNamespaceName() === $targetClassNamespace) {
            return $class->getShortName();
        }

        return sprintf('\\%s', $class->getName());
    }
}
