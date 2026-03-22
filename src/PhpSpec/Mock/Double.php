<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Mock;

use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * @internal
 * Generates test double classes at runtime using reflection and eval().
 * Supports doubling classes and interfaces, handling constructors with complex
 * type signatures including nullable, union, intersection, and enum types.
 */
final class Double
{
    /** @var array<string, class-string> Maps generated mock class names to their original FQCNs */
    public static array $doubleRegistry = [];

    /** @var array<string, array{mockClassName: string, reflectionClass: ReflectionClass<object>}> Cached class definitions by FQCN */
    private static array $classCache = [];

    /**
     * Resets the class cache and double registry between in-process runs.
     */
    public static function resetCache(): void
    {
        self::$classCache = [];
        self::$doubleRegistry = [];
    }

    /**
     * Creates a test double instance for the given class or interface.
     * Generates a unique subclass (or implementor) with stubbed methods,
     * call tracking, and return value configuration support.
     *
     * @param string $class fully qualified class or interface name to double
     * @return object an instance of the generated test double
     * @throws ReflectionException
     */
    public static function getInstance(string $class): object
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new LogicException("Cannot create test double: class or interface '$class' does not exist.");
        }

        if (isset(self::$classCache[$class])) {
            $cached = self::$classCache[$class];
            return self::generateDouble($cached['reflectionClass'], $cached['mockClassName']);
        }

        // create a unique name for the double class using a hash of the FQCN
        // to avoid collisions between classes with the same short name
        $classHash = substr(md5($class), 0, 8);
        $shortName = substr($class, strrpos($class, '\\') + 1);
        $mockClassName = 'PhpspecDouble\\' . $shortName . '_Double_' . $classHash . '_' . uniqid();

        // register the mapping from mock class name to original FQCN
        self::$doubleRegistry[$mockClassName] = $class;

        // generate the methods for the double
        $reflectionClass = new ReflectionClass($class);

        if ($reflectionClass->isTrait()) {
            throw new LogicException(
                "Cannot create a test double for trait $class. "
                    . 'Type-hint against an interface or class instead.',
            );
        }

        if ($reflectionClass->isEnum()) {
            throw new LogicException(
                "Cannot create a test double for enum $class. "
                    . 'Enums cannot be mocked — use a real enum value instead.',
            );
        }

        if (!$reflectionClass->isInterface() && ($reflectionClass->isFinal() || $reflectionClass->isReadOnly())) {
            $modifier = $reflectionClass->isReadOnly() ? 'readonly' : 'final';
            throw new LogicException(
                "Cannot create a test double for $modifier class $class. "
                    . 'Extract an interface and type-hint against it instead.',
            );
        }

        $methods = self::generateMethods($reflectionClass);

        // prefix class name with a namespace separator
        $prefixedClass = $class;
        if ($prefixedClass[0] !== '\\') {
            $prefixedClass = '\\' . $prefixedClass;
        }

        // make sure you are extending or implementing double class effectively
        $extends = 'extends';
        $implementsGenerated = 'implements \\PhpSpec\\Mock\\GeneratedDouble';
        if ($reflectionClass->isInterface()) {
            $extends = 'implements';
            $implementsGenerated = ', \\PhpSpec\\Mock\\GeneratedDouble';
        }

        // Extract the short class name from the namespaced mock class name
        /** @var int $nsSepPos */
        $nsSepPos = strrpos($mockClassName, '\\');
        $mockShortName = substr($mockClassName, $nsSepPos + 1);
        $mockNamespace = substr($mockClassName, 0, $nsSepPos);

        // put it all together and evaluate the double class template
        $mockClassCode = <<<PHP

namespace $mockNamespace;

class $mockShortName $extends $prefixedClass $implementsGenerated {
    private \$______phpspec_stack;
    private \$______phpspec_stubbedReturns = [];
    private \$______phpspec_stubbedThrows = [];
    private \$______phpspec_stubbedReturnCallbacks = [];

    $methods

    private function ______PhpSpecWasCalledWith(\$method, \$args) {
        if (!isset(\$this->______phpspec_stack)) {
            \$this->______phpspec_stack = new \PhpSpec\Mock\MethodCallsStack();
        }
        \$mocked = new \PhpSpec\Mock\MockedMethod(\$this, \$method, \$args);
        \PhpSpec\Mock\Expectation::\$registry["$mockClassName"] = \$mocked;
        \$this->______phpspec_stack->push(\$mocked);
        \$__lcd = new \PhpSpec\Mock\LastCallDouble(\$this, \$method);
        \PhpSpec\Mock\Expectation::\$lastDouble = \$__lcd;
        \PhpSpec\Mock\Expectation::\$lastCallForAllow = \$__lcd;
        \PhpSpec\Mock\Expectation::\$lastMockReturn = null;
    }

    public function ______PhpSpecGetStubbedCalls(): \PhpSpec\Mock\MethodCallsStack {
        if (!isset(\$this->______phpspec_stack)) {
            \$this->______phpspec_stack = new \PhpSpec\Mock\MethodCallsStack();
        }
        return \$this->______phpspec_stack;
    }

    public function ______PhpSpecNameOfClassDoubled(): string {
        return '$prefixedClass';
    }

    public function ______PhpSpecStubReturn(string \$method, mixed \$value, ?array \$args = null): void {
        if (!isset(\$this->______phpspec_stubbedReturns[\$method])) { \$this->______phpspec_stubbedReturns[\$method] = []; }
        array_unshift(\$this->______phpspec_stubbedReturns[\$method], [\$args, \$value]);
    }

    public function ______PhpSpecStubReturnUsing(string \$method, callable \$callback, ?array \$args = null): void {
        if (!isset(\$this->______phpspec_stubbedReturnCallbacks[\$method])) { \$this->______phpspec_stubbedReturnCallbacks[\$method] = []; }
        array_unshift(\$this->______phpspec_stubbedReturnCallbacks[\$method], [\$args, \$callback]);
    }

    public function ______PhpSpecStubThrow(string \$method, string \$exceptionClass, string \$message = '', ?array \$args = null): void {
        if (!isset(\$this->______phpspec_stubbedThrows[\$method])) { \$this->______phpspec_stubbedThrows[\$method] = []; }
        array_unshift(\$this->______phpspec_stubbedThrows[\$method], [\$args, ['class' => \$exceptionClass, 'message' => \$message]]);
    }
}
PHP;
        eval($mockClassCode);

        self::$classCache[$class] = [
            'mockClassName' => $mockClassName,
            'reflectionClass' => $reflectionClass,
        ];

        // create a double object and return
        return self::generateDouble($reflectionClass, $mockClassName);
    }

    /**
     * Instantiates the generated double class, resolving constructor dependencies
     * with sensible defaults based on parameter types.
     *
     * @param ReflectionClass<object> $reflectionClass reflection of the original class
     * @param string $mockClassName the generated double class name
     * @return object an instance of the double
     * @throws ReflectionException
     */
    private static function generateDouble(ReflectionClass $reflectionClass, string $mockClassName): object
    {
        if (!class_exists($mockClassName)) {
            throw new LogicException("Generated double class '$mockClassName' does not exist.");
        }

        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null || count($constructor->getParameters()) === 0) {
            $mockReflectionClass = new ReflectionClass($mockClassName);
            return $mockReflectionClass->newInstance();
        }

        if (!$constructor->isPublic()) {
            $mockReflectionClass = new ReflectionClass($mockClassName);
            return $mockReflectionClass->newInstanceWithoutConstructor();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                break;
            }
            $parameterType = $parameter->getType();
            if ($parameterType === null) {
                $dependencies[] = null;
                continue;
            }

            // Nullable types: pass null
            if (self::isNullableType($parameterType)) {
                $dependencies[] = null;
                continue;
            }

            // Union types: pick simplest value
            if ($parameterType instanceof ReflectionUnionType) {
                $dependencies[] = self::resolveUnionDefault($parameterType);
                continue;
            }

            // Intersection types: try to mock first type
            if ($parameterType instanceof ReflectionIntersectionType) {
                $types = $parameterType->getTypes();
                $first = $types[0];
                if ($first instanceof ReflectionNamedType && !$first->isBuiltin()) {
                    try {
                        $dependencies[] = Double::getInstance($first->getName());
                    } catch (Throwable) {
                        $dependencies[] = null;
                    }
                } else {
                    $dependencies[] = null;
                }
                continue;
            }

            if ($parameterType instanceof ReflectionNamedType) {
                $dependencies[] = self::resolveNamedDefault($parameterType);
            } else {
                $dependencies[] = null;
            }
        }

        $mockReflectionClass = new ReflectionClass($mockClassName);
        return $mockReflectionClass->newInstanceArgs($dependencies);
    }

    /**
     * Resolves a sensible default value for a named type (e.g. string => '', int => 0).
     * Recursively creates doubles for class types and picks the first case for enums.
     *
     * @param ReflectionNamedType $type the named type to resolve a default for
     * @return mixed a sensible default value for the type (e.g. '' for string, 0 for int, a double for classes)
     * @throws ReflectionException
     */
    private static function resolveNamedDefault(ReflectionNamedType $type): mixed
    {
        $name = $type->getName();
        return match ($name) {
            'array' => [],
            'string' => '',
            'int' => 0,
            'float' => 0.0,
            'bool' => false,
            'Closure' => function () {},
            'Throwable' => new \Exception('mock'),
            'mixed' => null,
            default => enum_exists($name) ? $name::cases()[0] : Double::getInstance($name),
        };
    }

    /**
     * Resolves a default value for a union type by preferring null, then simple
     * scalar types, then attempting to double the first class type.
     *
     * @param ReflectionUnionType $type the union type to resolve
     * @return mixed null if nullable, a scalar default if possible, or a double for the first class type
     */
    private static function resolveUnionDefault(ReflectionUnionType $type): mixed
    {
        $typeNames = array_map(
            fn(ReflectionNamedType|ReflectionIntersectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
            $type->getTypes(),
        );
        if (in_array('null', $typeNames)) {
            return null;
        }
        $map = ['string' => '', 'int' => 0, 'float' => 0.0, 'bool' => false, 'array' => []];
        foreach ($map as $name => $value) {
            if (in_array($name, $typeNames)) {
                return $value;
            }
        }
        // Try first class type
        foreach ($type->getTypes() as $t) {
            if ($t instanceof ReflectionNamedType && !$t->isBuiltin()) {
                try {
                    return Double::getInstance($t->getName());
                } catch (Throwable) {
                }
            }
        }
        return null;
    }

    /**
     * Generates PHP code string for a union return type in a doubled method.
     * Clears mock state tracking variables and returns the simplest compatible value.
     *
     * @param ReflectionUnionType $type the union return type to generate code for
     * @return string PHP code for the return statement
     */
    private static function getUnionReturnCode(ReflectionUnionType $type): string
    {
        $clear = '\\PhpSpec\\Mock\\Expectation::$lastDouble = null; \\PhpSpec\\Mock\\Expectation::$lastMockReturn = null; ';
        $typeNames = array_map(
            fn(ReflectionNamedType|ReflectionIntersectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
            $type->getTypes(),
        );

        // If null is in the union, return null
        if (in_array('null', $typeNames)) {
            return $clear . 'return null;';
        }

        // Pick the simplest type to return
        $map = ['string' => "''", 'int' => '0', 'float' => '0.0', 'bool' => 'false', 'array' => '[]'];
        foreach ($map as $name => $value) {
            if (in_array($name, $typeNames)) {
                return "\$__ret = $value; \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = \$__ret; return \$__ret;";
            }
        }

        return $clear . 'return null;';
    }

    /**
     * Formats a ReflectionType into valid PHP type syntax for code generation.
     * Handles named, union, and intersection types with proper namespace prefixing.
     *
     * @param ?ReflectionType $type the reflection type to format
     * @return string the PHP type syntax (e.g. "?\\Foo\\Bar", "string|int")
     */
    private static function formatTypeSyntax(?ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if (!$type->isBuiltin() && $name !== 'self' && $name !== 'static' && $name !== 'parent') {
                $name = '\\' . $name;
            }
            return $type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' . $name : $name;
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $t) {
                $formatted = self::formatTypeSyntax($t);
                // Wrap intersection members in parentheses for DNF types (e.g. (A&B)|C)
                if ($t instanceof ReflectionIntersectionType) {
                    $formatted = '(' . $formatted . ')';
                }
                $parts[] = $formatted;
            }
            return implode('|', $parts);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $parts = [];
            foreach ($type->getTypes() as $t) {
                if (!$t instanceof ReflectionNamedType) {
                    continue;
                }
                $name = $t->getName();
                if (!$t->isBuiltin()) {
                    $name = '\\' . $name;
                }
                $parts[] = $name;
            }
            return implode('&', $parts);
        }

        return (string) $type;
    }

    /**
     * Extracts the type name from a named type, returning null for compound types.
     *
     * @param ?ReflectionType $type the reflection type to inspect
     * @return string|null the type name, or null if not a named type
     */
    private static function getSimpleReturnTypeName(?ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }
        return null;
    }

    /**
     * Checks whether a type represents a class (non-builtin named type).
     *
     * @param ?ReflectionType $type the reflection type to check
     * @return bool true if the type is a non-builtin named type (i.e. a class/interface)
     */
    private static function isClassReturnType(?ReflectionType $type): bool
    {
        if ($type instanceof ReflectionNamedType) {
            return !$type->isBuiltin();
        }
        return false;
    }

    /**
     * Determines whether a type allows null, including union types containing null.
     *
     * @param ?ReflectionType $type the reflection type to check
     * @return bool true if the type allows null
     */
    private static function isNullableType(?ReflectionType $type): bool
    {
        if ($type === null) {
            return false;
        }
        if ($type instanceof ReflectionNamedType) {
            return $type->allowsNull();
        }
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if ($t instanceof ReflectionNamedType && $t->getName() === 'null') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Generates PHP method code strings for all overridable methods on a class.
     * Each generated method records calls, checks for stubs/throws, and returns
     * type-appropriate defaults or MatchableDouble wrappers for chaining.
     *
     * @param ReflectionClass<object> $reflectionClass the class whose methods to generate
     * @param int $depth recursion depth to prevent infinite loops on circular return types
     * @return string the generated PHP method code
     * @throws ReflectionException
     */
    private static function generateMethods(ReflectionClass $reflectionClass, int $depth = 0): string
    {
        if ($depth > 3) {
            return '';
        }

        // get the reflection methods
        $methods = $reflectionClass->getMethods();

        // for each reflection method generate the method code as a string
        //  - make it return an instance of the hinted returned type
        $mockMethods = [];
        foreach ($methods as $method) {

            // ignore constructors, final methods, static methods, and internal magic methods
            if ($method->getName() === '__construct'
                || $method->isFinal()
                || $method->isStatic()
                || ($method->isInternal() && str_starts_with($method->getName(), '__'))) {
                continue;
            }

            // get method properties
            $methodName = $method->getName();
            $parameters = $method->getParameters();
            $returnedType = $method->getReturnType();

            // format return type syntax using helper
            $returnedTypeSyntax = '';
            $isClassReturn = self::isClassReturnType($returnedType);
            $simpleReturnName = self::getSimpleReturnTypeName($returnedType);
            $isNullable = self::isNullableType($returnedType);
            $isCompoundType = $returnedType instanceof ReflectionUnionType || $returnedType instanceof ReflectionIntersectionType;

            if ($returnedType !== null) {
                $returnedTypeSyntax = ': ' . self::formatTypeSyntax($returnedType);
            }

            // get method parameters
            // All parameters get defaults so mocks can be called with no args
            // (needed for allow($mock->method())->toReturn(...) stub setup)
            $paramStrings = [];
            foreach ($parameters as $parameter) {
                // Use mixed for all params so ArgumentMatcher objects can be passed
                // (parameter contravariance allows widening in an implementing class)
                $typeStr = 'mixed ';
                if ($parameter->isVariadic()) {
                    $paramStr = $typeStr . '...$' . $parameter->getName();
                } elseif ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
                    $paramStr = $typeStr . '$' . $parameter->getName() . ' = ' . var_export($parameter->getDefaultValue(), true);
                } else {
                    $paramStr = $typeStr . '$' . $parameter->getName() . ' = null';
                }
                $paramStrings[] = $paramStr;
            }
            $paramString = implode(', ', $paramStrings);

            // Unique suffix for MockedMethodFor classes, includes parent class hash to avoid collisions
            $wrapperSuffix = ucfirst($methodName) . '_' . substr(md5($reflectionClass->getName() . '::' . $methodName), 0, 8) . '_' . uniqid();

            // make a class that is returned
            // implementing the Matchable interface
            // whilst extending the returned type
            $className = null;
            $isEnum = $isClassReturn && $simpleReturnName && enum_exists($simpleReturnName);
            $returnTypeFqcn = $isClassReturn && $simpleReturnName ? '\\' . $simpleReturnName : null;
            if ($isClassReturn && !$isEnum && $depth === 0 && $returnTypeFqcn !== null && (class_exists($returnTypeFqcn) || interface_exists($returnTypeFqcn))) {
                $returnedTypeReflected = new ReflectionClass($returnTypeFqcn);
                $templateMethods = '';

                if (count($returnedTypeReflected->getMethods())) {
                    $templateMethods = self::generateMethods($returnedTypeReflected, $depth + 1);
                }
                $template = '';
                if ($returnedTypeReflected->isInterface() && !$returnedTypeReflected->isInternal()) {
                    $className = 'MockedMethodFor' . $wrapperSuffix;
                    $template = <<<PHP
class $className implements \PhpSpec\Mock\MatchableDouble, $returnTypeFqcn {
    public function __construct(private \$mockedObject)
    {

    }

    public function ______PhpSpecGetDouble(): mixed
    {
        return \$this->mockedObject;
    }

    public function ______PhpSpecGetMethod(): string
    {
        return "$methodName";
    }

    public function toReturn(mixed \$value): void
    {
        \$this->mockedObject->______PhpSpecStubReturn('$methodName', \$value);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toReturnUsing(callable \$callback): void
    {
        \$this->mockedObject->______PhpSpecStubReturnUsing('$methodName', \$callback);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toThrow(string \$exceptionClass, string \$message = ''): void
    {
        \$this->mockedObject->______PhpSpecStubThrow('$methodName', \$exceptionClass, \$message);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    $templateMethods
}
PHP;
                } elseif (class_exists($returnTypeFqcn, false) && !$returnedTypeReflected->isFinal() && !$returnedTypeReflected->isReadOnly()) {
                    $className = 'MockedMethodFor' . $wrapperSuffix;
                    $template = <<<PHP
class $className extends $returnTypeFqcn implements \PhpSpec\Mock\MatchableDouble {
    private \$______mockedObject;

    public function __construct(\$mockedObject = null) {
        \$this->______mockedObject = \$mockedObject;
    }

    public function ______PhpSpecGetDouble(): mixed {
        return \$this->______mockedObject;
    }

    public function ______PhpSpecGetMethod(): string {
        return "$methodName";
    }

    public function toReturn(mixed \$value): void {
        \$this->______mockedObject->______PhpSpecStubReturn('$methodName', \$value);
        \$this->______mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toReturnUsing(callable \$callback): void {
        \$this->______mockedObject->______PhpSpecStubReturnUsing('$methodName', \$callback);
        \$this->______mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toThrow(string \$exceptionClass, string \$message = ''): void {
        \$this->______mockedObject->______PhpSpecStubThrow('$methodName', \$exceptionClass, \$message);
        \$this->______mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    $templateMethods
}
PHP;
                }
                try {
                    eval($template);
                } catch (Throwable) {
                    $className = null;
                }
            }

            // For intersection return types, generate a wrapper implementing ALL interfaces
            if ($returnedType instanceof ReflectionIntersectionType && $depth === 0 && !isset($className)) {
                $interfaceList = [];
                $canGenerate = true;
                foreach ($returnedType->getTypes() as $t) {
                    if (!$t instanceof ReflectionNamedType || $t->isBuiltin()) {
                        $canGenerate = false;
                        break;
                    }
                    $tName = $t->getName();
                    if (!interface_exists($tName)) {
                        $canGenerate = false;
                        break;
                    }
                    $tReflection = new ReflectionClass($tName);
                    if ($tReflection->isInternal()) {
                        $canGenerate = false;
                        break;
                    }
                    $interfaceList[] = '\\' . $tName;
                }
                if ($canGenerate && count($interfaceList) > 0) {
                    $className = 'MockedMethodFor' . $wrapperSuffix;
                    $interfacesStr = implode(', ', $interfaceList);
                    // Gather methods from all interfaces for the template
                    $allInterfaceReflections = [];
                    foreach ($interfaceList as $iface) {
                        /** @var class-string $ifaceClassName */
                        $ifaceClassName = ltrim($iface, '\\');
                        $allInterfaceReflections[] = new ReflectionClass($ifaceClassName);
                    }
                    $combinedTemplateMethods = '';
                    foreach ($allInterfaceReflections as $ifaceReflection) {
                        if (count($ifaceReflection->getMethods())) {
                            $combinedTemplateMethods .= self::generateMethods($ifaceReflection, $depth + 1) . "\n";
                        }
                    }
                    $template = <<<PHP
class $className implements \PhpSpec\Mock\MatchableDouble, $interfacesStr {
    public function __construct(private \$mockedObject) {}

    public function ______PhpSpecGetDouble(): mixed {
        return \$this->mockedObject;
    }

    public function ______PhpSpecGetMethod(): string {
        return "$methodName";
    }

    public function toReturn(mixed \$value): void {
        \$this->mockedObject->______PhpSpecStubReturn('$methodName', \$value);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toReturnUsing(callable \$callback): void {
        \$this->mockedObject->______PhpSpecStubReturnUsing('$methodName', \$callback);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toThrow(string \$exceptionClass, string \$message = ''): void {
        \$this->mockedObject->______PhpSpecStubThrow('$methodName', \$exceptionClass, \$message);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    $combinedTemplateMethods
}
PHP;
                    try {
                        eval($template);
                    } catch (Throwable) {
                        $className = null;
                    }
                }
            }

            // for methods with no return type (and not union/intersection), generate a MatchableDouble wrapper
            if ($returnedType === null && $depth === 0) {
                $className = 'MockedMethodFor' . $wrapperSuffix;
                $template = <<<PHP
class $className implements \PhpSpec\Mock\MatchableDouble {
    public function __construct(private \$mockedObject) {}

    public function ______PhpSpecGetDouble(): mixed {
        return \$this->mockedObject;
    }

    public function ______PhpSpecGetMethod(): string {
        return "$methodName";
    }

    public function toReturn(mixed \$value): void {
        \$this->mockedObject->______PhpSpecStubReturn('$methodName', \$value);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toReturnUsing(callable \$callback): void {
        \$this->mockedObject->______PhpSpecStubReturnUsing('$methodName', \$callback);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toThrow(string \$exceptionClass, string \$message = ''): void {
        \$this->mockedObject->______PhpSpecStubThrow('$methodName', \$exceptionClass, \$message);
        \$this->mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }
}
PHP;
                eval($template);
            }

            $return = '';
            if ($isNullable && $simpleReturnName !== 'mixed' && !isset($className)) {
                // Nullable types can just return null, clear mock state
                $return = '\\PhpSpec\\Mock\\Expectation::$lastDouble = null; \\PhpSpec\\Mock\\Expectation::$lastMockReturn = null; return null;';
            } elseif ($isCompoundType && !isset($className)) {
                // Union/intersection types: return null if nullable, else pick simplest
                if ($returnedType instanceof ReflectionUnionType) {
                    $return = self::getUnionReturnCode($returnedType);
                } else {
                    $return = 'return null;';
                }
            } elseif ($simpleReturnName === 'string') {
                $return = "\$__ret = ''; \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = \$__ret; return \$__ret;";
            } elseif ($simpleReturnName === 'void') {
                $return = 'return;';
            } elseif ($simpleReturnName === 'never') {
                $return = "throw new \\RuntimeException('Mock method should not be called');";
            } elseif ($simpleReturnName === 'mixed') {
                $return = 'return null;';
            } elseif (isset($className)) {
                $return = 'return new \\' . $className . '($this);';
            } elseif ($simpleReturnName === 'array') {
                $return = '$__ret = []; \\PhpSpec\\Mock\\Expectation::$lastMockReturn = $__ret; return $__ret;';
            } elseif ($simpleReturnName === 'int') {
                $return = '$__ret = 0; \\PhpSpec\\Mock\\Expectation::$lastMockReturn = $__ret; return $__ret;';
            } elseif ($simpleReturnName === 'float') {
                $return = '$__ret = 0.0; \\PhpSpec\\Mock\\Expectation::$lastMockReturn = $__ret; return $__ret;';
            } elseif ($simpleReturnName === 'bool') {
                $return = '$__ret = false; \\PhpSpec\\Mock\\Expectation::$lastMockReturn = $__ret; return $__ret;';
            } elseif ($isEnum) {
                $return = "return \\{$simpleReturnName}::cases()[0];";
            }

            // stub check — uses StubRegistry to find matching stub by method + args
            $stubCheck = '';
            $throwCheck = '';
            if ($simpleReturnName === 'void') {
                $stubCheck = "\$__match = \\PhpSpec\\Mock\\StubRegistry::findMatch('$methodName', func_get_args(), \$this->______phpspec_stubbedReturns, \$this->______phpspec_stubbedReturnCallbacks, \$this->______phpspec_stubbedThrows);"
                    . " if (\$__match !== null) { if (\$__match['type'] === 'throw') { throw new \$__match['data']['class'](\$__match['data']['message']); }"
                    . " if (\$__match['type'] === 'callback') { try { (\$__match['data'])(...func_get_args()); } catch (\\ArgumentCountError) {} }"
                    . ' \\PhpSpec\\Mock\\Expectation::$lastDouble = null; \\PhpSpec\\Mock\\Expectation::$lastMockReturn = null; return; }';
            } elseif ($simpleReturnName !== 'never') {
                $stubCheck = "\$__match = \\PhpSpec\\Mock\\StubRegistry::findMatch('$methodName', func_get_args(), \$this->______phpspec_stubbedReturns, \$this->______phpspec_stubbedReturnCallbacks, \$this->______phpspec_stubbedThrows);"
                    . " if (\$__match !== null) { if (\$__match['type'] === 'throw') { throw new \$__match['data']['class'](\$__match['data']['message']); }"
                    . " if (\$__match['type'] === 'callback') { try { \$__ret = (\$__match['data'])(...func_get_args()); \\PhpSpec\\Mock\\Expectation::\$lastDouble = null; \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = null; return \$__ret; } catch (\\ArgumentCountError) {} }"
                    . " if (\$__match['type'] === 'value') { \\PhpSpec\\Mock\\Expectation::\$lastDouble = null; \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = null; return \$__match['data']; } }";
            }

            // put it all together
            $mockMethods[] = <<<PHP
    public function $methodName($paramString)$returnedTypeSyntax {
        \$this->______PhpSpecWasCalledWith('$methodName', func_get_args());
        $stubCheck
        $return
    }
PHP;
        }

        return implode("\n", $mockMethods);
    }
}
