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
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use UnitEnum;

/**
 * @internal
 * Generates test double classes at runtime using reflection and eval().
 * Supports doubling classes and interfaces, handling constructors with complex
 * type signatures including nullable, union, intersection, and enum types.
 */
final class Double
{
    /** @var array<string, array{mockClassName: string, reflectionClass: ReflectionClass<object>}> Cached class definitions by FQCN */
    private static array $classCache = [];

    /**
     * Resets the class cache between in-process runs.
     */
    public static function resetCache(): void
    {
        self::$classCache = [];
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
        if (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class)) {
            throw new LogicException("Cannot create mock: class or interface '$class' does not exist. Make sure the class is autoloaded or the file is included.");
        }

        if (isset(self::$classCache[$class])) {
            $cached = self::$classCache[$class];
            return self::generateDouble($cached['reflectionClass'], $cached['mockClassName']);
        }

        // create a unique name for the double class using a hash of the FQCN
        // to avoid collisions between classes with the same short name
        $classHash = substr(md5($class), 0, 8);
        $separator = strrpos($class, '\\');
        $shortName = $separator === false ? $class : substr($class, $separator + 1);
        $mockClassName = 'PhpspecDouble\\' . $shortName . '_Double_' . $classHash . '_' . uniqid();

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
                    . 'Extract an interface and type-hint against it, or make the class non-final.',
            );
        }

        $methods = self::generateMethods($reflectionClass);
        $undefinedMethodGuard = self::undefinedMethodGuard($reflectionClass, ltrim($class, '\\'));

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
    private ?\PhpSpec\Mock\Stubs \$______phpspec_stubs = null;
    private \$______phpspec_lenient = false;
    private ?\PhpSpec\Mock\Declarations \$______phpspec_declared = null;
    private \$______phpspec_signatures = [];
    private \$______phpspec_returnTypes = [];
    private \$______phpspec_strictFiles = [];

    $methods
    $undefinedMethodGuard

    private function ______PhpSpecWasCalledWith(\$method, \$args) {
        if (!isset(\$this->______phpspec_stack)) {
            \$this->______phpspec_stack = new \PhpSpec\Mock\MethodCallsStack();
        }
        \$mocked = new \PhpSpec\Mock\MockedMethod(\$this, \$method, \$args);
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

    private function ______PhpSpecStrictIn(string \$file): bool {
        return \$this->______phpspec_strictFiles[\$file] ??= (new \PhpSpec\Mock\CallingFile(\$file))->declaresStrictTypes();
    }

    public function ______PhpSpecBeLenient(): void {
        \$this->______phpspec_lenient = true;
    }

    public function ______PhpSpecDeclare(string \$method, ?array \$args): void {
        (\$this->______phpspec_declared ??= new \PhpSpec\Mock\Declarations())->add(\$method, \$args);
    }

    private function ______PhpSpecHoldUnlessExpected(string \$method, array \$args, array \$frame): void {
        if (\$this->______phpspec_lenient
            || (\$this->______phpspec_declared?->cover(\$method, \$args) ?? false)
            || \PhpSpec\Mock\ArrangingCode::includes(\$frame['file'] ?? '')) {
            return;
        }

        \PhpSpec\Mock\HeldCalls::hold(\$this, \$method, \$args, \PhpSpec\Mock\UnexpectedCallException::to('$prefixedClass', \$method, \$args, \$frame['file'] ?? '', \$frame['line'] ?? 0));
    }

    private function ______PhpSpecStubs(): \PhpSpec\Mock\Stubs {
        return \$this->______phpspec_stubs ??= new \PhpSpec\Mock\Stubs();
    }

    public function ______PhpSpecStubReturn(string \$method, mixed \$value, ?array \$args = null): void {
        \$this->______PhpSpecStubs()->add(\$method, \$args, 'value', \$value);
    }

    public function ______PhpSpecStubReturnUsing(string \$method, callable \$callback, ?array \$args = null): void {
        \$this->______PhpSpecStubs()->add(\$method, \$args, 'callback', \$callback);
    }

    public function ______PhpSpecStubThrow(string \$method, string|\Throwable \$exception, string \$message = '', ?array \$args = null): void {
        \$this->______PhpSpecStubs()->add(\$method, \$args, 'throw', \$exception instanceof \Throwable ? ['throwable' => \$exception] : ['class' => \$exception, 'message' => \$message]);
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
        $typeNames = array_map(
            fn(ReflectionNamedType|ReflectionIntersectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
            $type->getTypes(),
        );

        // If null is in the union, return null
        if (in_array('null', $typeNames)) {
            return self::returning('null');
        }

        // Pick the simplest type to return
        $map = ['string' => "''", 'int' => '0', 'float' => '0.0', 'bool' => 'false', 'array' => '[]'];
        foreach ($map as $name => $value) {
            if (in_array($name, $typeNames)) {
                return self::returning($value);
            }
        }

        return self::returning('null');
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
     * The code that holds a call to what the real method takes, before the
     * double answers it.
     */
    private static function holdCode(string $class, ReflectionMethod $method): string
    {
        return sprintf(
            "\$__frame = \\debug_backtrace(\\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? []; \$__made = !\\PhpSpec\\Mock\\ArrangingCode::includes(\$__frame['file'] ?? ''); \$__given = (\$this->______phpspec_signatures[%2\$s] ??= new \\PhpSpec\\Mock\\Signature(%1\$s, %2\$s, %3\$d, %4\$s, %5\$s, %6\$s))->hold(\\func_get_args(), \$__frame['file'] ?? '', \$__frame['line'] ?? 0, \$this->______PhpSpecStrictIn(\$__frame['file'] ?? ''));",
            var_export($class, true),
            var_export($method->getName(), true),
            $method->getNumberOfRequiredParameters(),
            $method->isVariadic() ? 'null' : (string) $method->getNumberOfParameters(),
            var_export(self::parameterList($method), true),
            self::typedCode($method),
        );
    }

    /**
     * The code for each parameter's name, its type as declared, and a closure
     * taking that type alone, through which an argument is held to it as PHP
     * holds it; no closure for a parameter that takes anything.
     */
    private static function typedCode(ReflectionMethod $method): string
    {
        $typed = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $taking = $type === null || ($type instanceof ReflectionNamedType && $type->getName() === 'mixed')
                ? 'null'
                : sprintf('static fn(%s $value) => $value', self::typeIn($type, $method));
            $typed[] = sprintf('[%s, %s, %s]', var_export($parameter->getName(), true), var_export((string) $type, true), $taking);
        }

        return '[' . implode(', ', $typed) . ']';
    }

    /**
     * A type as code outside the class that declares it: self and parent
     * named for the classes they stand for.
     */
    private static function typeIn(ReflectionType $type, ReflectionMethod $method): string
    {
        $declaring = $method->getDeclaringClass();
        $parent = $declaring->getParentClass();

        return (string) preg_replace_callback(
            '/(?<![\\\\\w])(self|parent)(?![\\\\\w])/',
            static fn(array $word): string => '\\' . ($word[1] === 'self' || $parent === false ? $declaring->getName() : $parent->getName()),
            self::formatTypeSyntax($type),
        );
    }

    /**
     * The code for a call's arguments with the defaults it left out filled
     * in, so a call is matched as the call it is however it was written.
     *
     * @param list<ReflectionParameter> $parameters
     */
    private static function argumentsCode(array $parameters, bool $held): string
    {
        $named = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic() || ($parameter->isOptional() && !$parameter->isDefaultValueAvailable())) {
                break;
            }

            $named[] = '$' . $parameter->getName();
        }

        if ($held) {
            return $named === [] ? '$__given' : sprintf('\\array_merge($__given, \\array_slice([%s], \\count($__given)))', implode(', ', $named));
        }

        if ($named === []) {
            return '\\func_get_args()';
        }

        return sprintf('\\array_merge([%s], \\array_slice(\\func_get_args(), %d))', implode(', ', $named), count($named));
    }

    /**
     * The parameter list as the method declares it, for a message.
     */
    private static function parameterList(ReflectionMethod $method): string
    {
        $listed = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $listed[] = ltrim(sprintf(
                '%s %s%s$%s%s',
                $type === null ? '' : (string) $type,
                $parameter->isPassedByReference() ? '&' : '',
                $parameter->isVariadic() ? '...' : '',
                $parameter->getName(),
                $parameter->isDefaultValueAvailable() ? ' = ' . self::shownDefault($parameter->getDefaultValue()) : '',
            ));
        }

        return implode(', ', $listed);
    }

    private static function shownDefault(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === [] => '[]',
            is_array($value) => '[...]',
            is_object($value) => 'new ' . $value::class . '()',
            default => var_export($value, true),
        };
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
     * Returns the method body statement that produces a default value for a builtin return type.
     *
     * @param string $typeName the builtin type name
     * @return string|null the return statement, or null if the type is not a recognised builtin
     */
    private static function getBuiltinReturnCode(string $typeName): ?string
    {
        $returning = self::returning(...);

        return match ($typeName) {
            'string' => $returning("''"),
            'void' => 'return;',
            'never' => "throw new \\RuntimeException('Mock method should not be called');",
            'mixed' => $returning('null'),
            'array', 'iterable' => $returning('[]'),
            'int' => $returning('0'),
            'float' => $returning('0.0'),
            'bool' => $returning('false'),
            'true' => $returning('true'),
            'false' => $returning('false'),
            // No double can stand in for these, so a usable value does.
            'static', 'self', 'parent' => $returning('$this'),
            'object' => $returning('new \\stdClass()'),
            'callable', 'Closure' => $returning('static fn() => null'),
            'Generator' => $returning('(static fn() => yield from [])()'),
            default => null,
        };
    }

    /**
     * Returns a value through a variable, as a method returning by reference
     * must, and keeps it as the last value a double returned.
     */
    private static function returning(string $value): string
    {
        return "\$__ret = $value; \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = \$__ret; return \$__ret;";
    }

    /**
     * Whether var_export() writes the value as code a default may hold: no
     * object other than an enum case, which only runs (new, a closure).
     */
    private static function writable(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::writable($item)) {
                    return false;
                }
            }

            return true;
        }

        return !is_object($value) || $value instanceof UnitEnum;
    }

    /**
     * The default a parameter builds when the call leaves it out, since it
     * cannot be written into the double's signature: an object afresh on every
     * call, as PHP builds it, and a closure once, so a call that leaves it out
     * matches a declaration that left it out too.
     */
    private static function defaultCode(ReflectionMethod $method, ReflectionParameter $parameter): string
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return '';
        }

        return sprintf(
            'if ($%1$s === \\PhpSpec\\Mock\\LeftOut::Argument) { static $__closure_%1$s = null; $%1$s = (new \\ReflectionParameter([%2$s, %3$s], %4$s))->getDefaultValue(); if ($%1$s instanceof \\Closure) { $%1$s = $__closure_%1$s ??= $%1$s; } }',
            $parameter->getName(),
            var_export($method->class, true),
            var_export($method->getName(), true),
            var_export($parameter->getName(), true),
        );
    }

    /**
     * The arguments a stub's callback is handed, each one the method takes by
     * reference handed as that reference, so what the callback writes into it
     * reaches the caller; none when the method takes nothing by reference.
     *
     * @param list<ReflectionParameter> $parameters
     */
    private static function passingCode(array $parameters): string
    {
        $references = '';

        foreach ($parameters as $parameter) {
            if (!$parameter->isPassedByReference()) {
                continue;
            }

            $references .= $parameter->isVariadic()
                ? sprintf(' foreach (\\array_keys($%1$s) as $__key) { if (\\is_int($__key)) { $__passing[%2$d + $__key] = &$%1$s[$__key]; } }', $parameter->getName(), $parameter->getPosition())
                : sprintf(' if (\\array_key_exists(%2$d, $__passing)) { $__passing[%2$d] = &$%1$s; }', $parameter->getName(), $parameter->getPosition());
        }

        return $references === '' ? '' : '$__passing = $__args;' . $references;
    }

    /**
     * What a method returns when nothing can stand in for its return type: an
     * explanation, where PHP alone would say a value of that type was expected
     * and none returned.
     */
    private static function unbuildableReturnCode(string $class, string $methodName, string $returnType): string
    {
        $why = class_exists($returnType) && (new ReflectionClass($returnType))->isFinal()
            ? "$returnType is final, so no double of it can be made"
            : "no double of $returnType can be made";
        $message = sprintf('PhpSpec cannot stand in for the return type of %s::%s(): %s. Stub the call with allow(), return an interface, or make the class non-final.', $class, $methodName, $why);

        return 'throw new \\LogicException(' . var_export($message, true) . ');';
    }

    /**
     * What a method returns when no double of its return type can be made: an
     * instance of the very class, built without its constructor, for allow()
     * to replace. A lenient double has nobody about to replace it, so it
     * refuses instead.
     */
    private static function placeholderReturnCode(string $class, string $methodName, string $returnType, bool $mayBeLenient): string
    {
        $refusal = self::unbuildableReturnCode($class, $methodName, $returnType);

        if (!class_exists($returnType) || (new ReflectionClass($returnType))->isInternal()) {
            return $refusal;
        }

        $placeholder = sprintf(
            '$__ret = (new \ReflectionClass(%s))->newInstanceWithoutConstructor(); \PhpSpec\Mock\Expectation::$lastMockReturn = $__ret; return $__ret;',
            var_export($returnType, true),
        );

        return $mayBeLenient ? "if (\$this->______phpspec_lenient) { $refusal } $placeholder" : $placeholder;
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
     * A method the doubled type does not define is an error naming that type,
     * as PHP would report it on the real object, so a reader and a generator
     * see what the spec asked of App\Clock rather than of a generated double.
     * A type with a __call of its own keeps it.
     *
     * @param ReflectionClass<object> $reflectionClass
     */
    private static function undefinedMethodGuard(ReflectionClass $reflectionClass, string $doubled): string
    {
        if ($reflectionClass->hasMethod('__call')) {
            return '';
        }

        return <<<PHP
    public function __call(string \$method, array \$arguments): mixed {
        throw new \Error("Call to undefined method $doubled::{\$method}()");
    }
PHP;
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
            // Tentative return types (e.g. JsonSerializable::jsonSerialize(): mixed) are not
            // exposed via getReturnType(); declaring them explicitly avoids the PHP deprecation
            // about incompatible/missing return types on internal interfaces.
            $returnedType = $method->getReturnType() ?? $method->getTentativeReturnType();

            // format return type syntax using helper
            $returnedTypeSyntax = '';
            $isClassReturn = self::isClassReturnType($returnedType);
            $simpleReturnName = self::getSimpleReturnTypeName($returnedType);
            $isNullable = self::isNullableType($returnedType);
            $isCompoundType = $returnedType instanceof ReflectionUnionType || $returnedType instanceof ReflectionIntersectionType;

            if ($returnedType !== null) {
                $returnedTypeSyntax = ': ' . self::typeIn($returnedType, $method);
            }

            // get method parameters
            // All parameters get defaults so mocks can be called with no args
            // (needed for allow($mock->method())->toReturn(...) stub setup)
            $paramStrings = [];
            $defaults = '';
            foreach ($parameters as $parameter) {
                // Use mixed for all params so ArgumentMatcher objects can be passed
                // (parameter contravariance allows widening in an implementing class)
                $typeStr = 'mixed ' . ($parameter->isPassedByReference() ? '&' : '');
                if ($parameter->isVariadic()) {
                    $paramStr = $typeStr . '...$' . $parameter->getName();
                } elseif ($parameter->isOptional() && $parameter->isDefaultValueAvailable() && self::writable($parameter->getDefaultValue())) {
                    $paramStr = $typeStr . '$' . $parameter->getName() . ' = ' . var_export($parameter->getDefaultValue(), true);
                } else {
                    $paramStr = $typeStr . '$' . $parameter->getName() . ($parameter->isDefaultValueAvailable() ? ' = \\PhpSpec\\Mock\\LeftOut::Argument' : ' = null');
                    $defaults .= self::defaultCode($method, $parameter);
                }
                $paramStrings[] = $paramStr;
            }
            $passing = self::passingCode($parameters);
            $callWith = $passing === '' ? '$__args' : '$__passing';
            $called = $method->returnsReference()
                ? "\$__cb = \$__match['data']; if ((new \\ReflectionFunction(\\Closure::fromCallable(\$__cb)))->returnsReference()) { \$__ret = &\$__cb(...$callWith); } else { \$__ret = \$__cb(...$callWith); }"
                : "\$__ret = (\$__match['data'])(...$callWith);";
            $paramString = implode(', ', $paramStrings);
            $arguments = self::argumentsCode($parameters, $depth === 0);
            $hold = $depth === 0 ? self::holdCode($reflectionClass->getName(), $method) : '';
            $holdAnswer = $depth === 0 && $returnedType !== null ? sprintf('($this->______phpspec_returnTypes[%2$s] ??= new \\PhpSpec\\Mock\\ReturnType(%1$s, %2$s, self::class))->holdAnswer($__ret);', var_export($reflectionClass->getName(), true), var_export($methodName, true)) : '';

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
    use \PhpSpec\Mock\MatchableDoubleBehaviour;

    public function __construct(object \$mockedObject) {
        \$this->______phpspec_mockedObject = \$mockedObject;
        \$this->______phpspec_methodName = '$methodName';
    }

    $templateMethods
}
PHP;
                } elseif (class_exists($returnTypeFqcn, false) && !$returnedTypeReflected->isFinal() && !$returnedTypeReflected->isReadOnly()) {
                    $className = 'MockedMethodFor' . $wrapperSuffix;
                    $template = <<<PHP
class $className extends $returnTypeFqcn implements \PhpSpec\Mock\MatchableDouble {
    use \PhpSpec\Mock\MatchableDoubleBehaviour;

    public function __construct(?object \$mockedObject = null) {
        if (\$mockedObject !== null) {
            \$this->______phpspec_mockedObject = \$mockedObject;
            \$this->______phpspec_methodName = '$methodName';
        }
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
    use \PhpSpec\Mock\MatchableDoubleBehaviour;

    public function __construct(object \$mockedObject) {
        \$this->______phpspec_mockedObject = \$mockedObject;
        \$this->______phpspec_methodName = '$methodName';
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
    use \PhpSpec\Mock\MatchableDoubleBehaviour;

    public function __construct(object \$mockedObject) {
        \$this->______phpspec_mockedObject = \$mockedObject;
        \$this->______phpspec_methodName = '$methodName';
    }
}
PHP;
                eval($template);
            }

            $return = '';
            if ($isNullable && $simpleReturnName !== 'mixed' && !isset($className)) {
                // Nullable types can just return null, clear mock state
                $return = self::returning('null');
            } elseif ($isCompoundType && !isset($className)) {
                // Union/intersection types: return null if nullable, else pick simplest
                if ($returnedType instanceof ReflectionUnionType) {
                    $return = self::getUnionReturnCode($returnedType);
                } else {
                    $return = self::returning('null');
                }
            } elseif (isset($className)) {
                $return = '$__ret = new \\' . $className . '($this); return $__ret;';
            } elseif ($isEnum) {
                $return = "\$__ret = \\{$simpleReturnName}::cases()[0]; return \$__ret;";
            } elseif ($simpleReturnName !== null) {
                $return = self::getBuiltinReturnCode($simpleReturnName)
                    ?? self::placeholderReturnCode($reflectionClass->getName(), $methodName, $simpleReturnName, $depth === 0);
            }

            $guard = '';
            if ($depth === 0 && $simpleReturnName !== 'never') {
                $guard = "if (\$__match === null) { \$this->______PhpSpecHoldUnlessExpected('$methodName', \$__args, \$__frame); }";
            }

            $stubCheck = '';
            $throwCheck = '';
            if ($simpleReturnName === 'void') {
                $stubCheck = "\$__match = \\PhpSpec\\Mock\\ArgumentMatcher::among(\$__args) ? null : \$this->______PhpSpecStubs()->answer('$methodName', \$__args, \$__made ?? true);"
                    . " if (\$__match !== null && \$__match['type'] === 'withheld') { \\PhpSpec\\Mock\\Expectation::\$withheld = \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); \$__match = null; }"
                    . " if (\$__match !== null) { if (\$__match['type'] === 'throw') { throw \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); }"
                    . " if (\$__match['type'] === 'callback') { (\$__match['data'])(...$callWith); }"
                    . ' \\PhpSpec\\Mock\\Expectation::$lastMockReturn = null; return; }';
            } elseif ($simpleReturnName !== 'never') {
                $stubCheck = "\$__match = \\PhpSpec\\Mock\\ArgumentMatcher::among(\$__args) ? null : \$this->______PhpSpecStubs()->answer('$methodName', \$__args, \$__made ?? true);"
                    . " if (\$__match !== null && \$__match['type'] === 'withheld') { \\PhpSpec\\Mock\\Expectation::\$withheld = \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); \$__match = null; }"
                    . " if (\$__match !== null) { if (\$__match['type'] === 'throw') { throw \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); }"
                    . " if (\$__match['type'] === 'callback') { $called $holdAnswer \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = \$__ret; return \$__ret; }"
                    . " if (\$__match['type'] === 'value') { \$__ret = \$__match['data']; \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = \$__ret; return \$__ret; } }";
            }

            $byReference = $method->returnsReference() ? '&' : '';

            // put it all together
            $mockMethods[] = <<<PHP
    public function {$byReference}$methodName($paramString)$returnedTypeSyntax {
        $defaults
        $hold
        \$__args = $arguments;
        $passing
        \$this->______PhpSpecWasCalledWith('$methodName', \$__args);
        $stubCheck
        $guard
        $return
    }
PHP;
        }

        return implode("\n", $mockMethods);
    }
}
