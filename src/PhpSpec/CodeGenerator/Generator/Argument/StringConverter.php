<?php

namespace PhpSpec\CodeGenerator\Generator\Argument;

class StringConverter
{
    /**
     * @param Argument[]  $arguments
     * @param string      $targetClassNamespace
     *
     * @return string
     */
    public function convertFromArguments(array $arguments, string $targetClassNamespace) : string
    {
        return implode(', ', array_map(function (Argument $argument) use ($targetClassNamespace) {
            return $this->convertArgument($argument, $targetClassNamespace);
        }, $arguments));
    }

    private function convertArgument(Argument $argument, string $targetClassNamespace) : string
    {
        $argumentName = '$' . $argument->getName();

        $type = $argument->getType();

        switch (true) {
            case ($type instanceof \ReflectionClass):
                $typeHint = $this->getResolvedClassTypeHint($type, $targetClassNamespace) . ' ';
                break;
            case (strlen($type) > 0):
                $typeHint = $type . ' ';
                break;
            default:
                $typeHint = '';
        }

        $defaultValueString = $this->getDefaultValueStringFrom($argument);

        return $typeHint . $argumentName . $defaultValueString;
    }

    private function getDefaultValueStringFrom(Argument $argument) : string
    {
        $type = $argument->getType();

        return $type && $argument->hasDefaultValue() ? sprintf(' = %s', strtolower(gettype($argument->getDefaultValue()))) : '';
    }

    private function getResolvedClassTypeHint(\ReflectionClass $class, string $targetClassNamespace) : string
    {
        if ($class->getNamespaceName() === $targetClassNamespace) {
            return $class->getShortName();
        }

        return sprintf('\\%s', $class->getName());
    }
}
