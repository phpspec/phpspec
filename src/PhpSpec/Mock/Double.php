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

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use Traversable;
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

    /** @var array<string, class-string<DummyAnswer>> The class of a dummy answer, by the types it is of */
    private static array $answerClasses = [];

    /** Whether PHP lets a plain class implement a built-in interface, asked once per interface in this process */
    private static ?ImplementationProbe $probe = null;

    /**
     * Resets the class cache between in-process runs.
     */
    public static function resetCache(): void
    {
        self::$classCache = [];
        self::$answerClasses = [];
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

        [$base, $methods, $refusal] = self::planOf([$reflectionClass]);

        if ($methods === null) {
            throw new LogicException(sprintf('Cannot create a test double for %s %s. %s', $reflectionClass->isInterface() ? 'interface' : 'class', ltrim($class, '\\'), $refusal));
        }

        $mockClassName = self::classOf([$reflectionClass], $base, $methods);

        self::$classCache[$class] = [
            'mockClassName' => $mockClassName,
            'reflectionClass' => $reflectionClass,
        ];

        // create a double object and return
        return self::generateDouble($reflectionClass, $mockClassName);
    }

    /**
     * What a lenient double answers to a call of a method returning the given
     * types: a dummy of them, standing for the call.
     *
     * @param non-empty-list<class-string> $types a class first, if any, then interfaces
     */
    public static function answerTo(GeneratedDouble $double, string $method, array $types): object
    {
        $reflections = array_map(static fn(string $type): ReflectionClass => new ReflectionClass($type), $types);
        $answerClass = new ReflectionClass(self::$answerClasses[implode('&', $types)] ??= self::answerClassOf($reflections));
        $answer = $reflections[0]->isInterface() ? $answerClass->newInstance() : $answerClass->newInstanceWithoutConstructor();
        $answer->______PhpSpecAnswering($double, $method);

        return $answer;
    }

    /**
     * Generates the class of a double of the given types: a subclass of a
     * class, or an implementation of interfaces on the base PHP requires for
     * them.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @return string the generated class name
     * @throws ReflectionException
     */
    private static function classOf(array $types, ?NativeBase $base, MethodSet $methods): string
    {
        $names = array_map(static fn(ReflectionClass $type): string => $type->getName(), $types);
        $doubledName = implode('&', $names);

        // create a unique name for the double class using a hash of the FQCN
        // to avoid collisions between classes with the same short name
        $mockShortName = $types[0]->getShortName() . '_Double_' . substr(md5($doubledName), 0, 8) . '_' . uniqid();

        $methods = self::generateMethods($methods->contracts) . ($base?->code() ?? '');
        $undefinedMethodGuard = self::undefinedMethodGuard($types, $doubledName);
        $doubled = var_export($doubledName, true);
        $prefixedClass = '\\' . $doubledName;

        // make sure you are extending or implementing double class effectively
        $interfaces = $types[0]->isInterface() ? $names : array_slice($names, 1);
        $extends = $types[0]->isInterface() ? $base?->extends() : '\\' . $names[0];
        $header = ($extends === null ? '' : 'extends ' . $extends . ' ') . 'implements ' . implode(', ', [...array_map(static fn(string $name): string => '\\' . $name, $interfaces), ...($base?->implements() ?? []), '\\PhpSpec\\Mock\\GeneratedDouble']);

        // put it all together and evaluate the double class template
        $mockClassCode = <<<PHP

namespace PhpspecDouble;

class $mockShortName $header {
    private \$______phpspec_stack;
    private ?\PhpSpec\Mock\Stubs \$______phpspec_stubs = null;
    private \$______phpspec_lenient = false;
    private \$______phpspec_dummy = false;
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

    public function ______PhpSpecBeDummy(): void {
        \$this->______phpspec_lenient = true;
        \$this->______phpspec_dummy = true;
    }

    public function ______PhpSpecArrange(string \$method, string \$as): void {
        if (\$this->______phpspec_dummy) {
            throw new \LogicException(\sprintf('%s::%s() was %s on a dummy. Use mock() instead of dummy().', $doubled, \$method, \$as));
        }
    }

    public function ______PhpSpecChainTo(string \$method): string {
        return $doubled . '::' . \$method . '()';
    }

    public function ______PhpSpecStubAdvice(string \$method): string {
        return \$this->______phpspec_dummy
            ? 'Use mock() instead of dummy() and stub ' . \$this->______PhpSpecChainTo(\$method) . '.'
            : 'Stub ' . \$this->______PhpSpecChainTo(\$method) . ' with a mock() instead.';
    }

    protected function ______PhpSpecWayOut(string \$method): string {
        return \$this->______phpspec_dummy
            ? ' ' . \$this->______PhpSpecStubAdvice(\$method)
            : ' Stub the call with allow(), return an interface, or make the class non-final.';
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
        \$this->______PhpSpecArrange(\$method, 'stubbed');
        \$this->______PhpSpecStubs()->add(\$method, \$args, 'value', \$value);
    }

    public function ______PhpSpecStubReturnUsing(string \$method, callable \$callback, ?array \$args = null): void {
        \$this->______PhpSpecArrange(\$method, 'stubbed');
        \$this->______PhpSpecStubs()->add(\$method, \$args, 'callback', \$callback);
    }

    public function ______PhpSpecStubThrow(string \$method, string|\Throwable \$exception, string \$message = '', ?array \$args = null): void {
        \$this->______PhpSpecArrange(\$method, 'stubbed');
        \$this->______PhpSpecStubs()->add(\$method, \$args, 'throw', \$exception instanceof \Throwable ? ['throwable' => \$exception] : ['class' => \$exception, 'message' => \$message]);
    }
}
PHP;
        eval($mockClassCode);

        return 'PhpspecDouble\\' . $mockShortName;
    }

    /**
     * Generates the class of a dummy answer of the given types: a double of
     * them that stands for the call it answered.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @return class-string<DummyAnswer>
     * @throws ReflectionException
     */
    private static function answerClassOf(array $types): string
    {
        [$base, $methods, $refusal] = self::planOf($types);

        if ($methods === null) {
            throw new LogicException((string) $refusal);
        }

        $doubleClass = self::classOf($types, $base, $methods);
        $shortName = substr($doubleClass, strlen('PhpspecDouble\\')) . '_Answer';

        eval("namespace PhpspecDouble; class $shortName extends \\$doubleClass implements \\PhpSpec\\Mock\\MatchableDouble, \\PhpSpec\\Mock\\DummyAnswer { use \\PhpSpec\\Mock\\MatchableDoubleBehaviour, \\PhpSpec\\Mock\\DummyAnswerBehaviour; }");
        $answerClass = 'PhpspecDouble\\' . $shortName;

        if (!is_a($answerClass, DummyAnswer::class, true)) {
            throw new LogicException("Generated answer class '$answerClass' is not a dummy answer.");
        }

        return $answerClass;
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
     * The code that holds a call to what the real method takes, before the
     * double answers it.
     */
    private static function holdCode(MethodContract $contract): string
    {
        $takes = $contract->takes();

        return sprintf(
            "\$__frame = \\debug_backtrace(\\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? []; \$__made = !\\PhpSpec\\Mock\\ArrangingCode::includes(\$__frame['file'] ?? ''); \$__given = (\$this->______phpspec_signatures[%2\$s] ??= new \\PhpSpec\\Mock\\Signature(%1\$s, %2\$s, %3\$d, %4\$s, %5\$s, %6\$s))->hold(\\func_get_args(), \$__frame['file'] ?? '', \$__frame['line'] ?? 0, \$this->______PhpSpecStrictIn(\$__frame['file'] ?? ''));",
            var_export($contract->owner, true),
            var_export($contract->name, true),
            $contract->required,
            $takes === null ? 'null' : (string) $takes,
            var_export(self::parameterList($contract), true),
            self::typedCode($contract),
        );
    }

    /**
     * The code for each parameter's name, its type as declared, and a closure
     * taking that type alone, through which an argument is held to it as PHP
     * holds it; no closure for a parameter that takes anything.
     */
    private static function typedCode(MethodContract $contract): string
    {
        $typed = [];

        foreach ($contract->parameters as $parameter) {
            $taking = $parameter->takes === null ? 'null' : sprintf('static fn(%s $value) => $value', $parameter->takes);
            $typed[] = sprintf('[%s, %s, %s]', var_export($parameter->name, true), var_export($parameter->declared, true), $taking);
        }

        return '[' . implode(', ', $typed) . ']';
    }

    /**
     * The code for a call's arguments with the defaults it left out filled
     * in, and the named arguments a variadic collected after them, so a call
     * is matched as the call it is however it was written.
     *
     * @param list<ContractParameter> $parameters
     */
    private static function argumentsCode(array $parameters): string
    {
        $named = [];

        foreach ($parameters as $parameter) {
            if ($parameter->variadic || ($parameter->optional && !$parameter->hasDefault())) {
                break;
            }

            $named[] = '$' . $parameter->name;
        }

        $positional = $named === [] ? '$__given' : sprintf('\\array_merge($__given, \\array_slice([%s], \\count($__given)))', implode(', ', $named));

        return self::variadicOf($parameters) === null ? $positional : sprintf('\\array_merge(%s, $__named)', $positional);
    }

    /**
     * @param list<ContractParameter> $parameters
     */
    private static function variadicOf(array $parameters): ?ContractParameter
    {
        $last = end($parameters);

        return $last !== false && $last->variadic ? $last : null;
    }

    /**
     * The named arguments a variadic parameter collected, which PHP leaves out
     * of func_get_args(), held to the parameter's type; nothing for a method
     * with no variadic.
     *
     * @param list<ContractParameter> $parameters
     */
    private static function namedCode(array $parameters, string $methodName): string
    {
        $variadic = self::variadicOf($parameters);

        if ($variadic === null) {
            return '';
        }

        // Copied out, so the call is recorded as it was made: a by-reference
        // variadic holds the caller's variables, and only a stub's callback is
        // handed those.
        $collected = sprintf("\\array_map(static fn(\$value) => \$value, \\array_filter(\$%s, '\\is_string', \\ARRAY_FILTER_USE_KEY))", $variadic->name);

        return sprintf(
            "\$__named = \$this->______phpspec_signatures[%s]->holdNamed(%s, \\func_num_args(), \$__frame['file'] ?? '', \$__frame['line'] ?? 0, \$this->______PhpSpecStrictIn(\$__frame['file'] ?? ''));",
            var_export($methodName, true),
            $collected,
        );
    }

    /**
     * The parameter list as the method declares it, for a message.
     */
    private static function parameterList(MethodContract $contract): string
    {
        $listed = [];

        foreach ($contract->parameters as $parameter) {
            $listed[] = ltrim(sprintf(
                '%s %s%s$%s%s',
                $parameter->declared,
                $parameter->byReference ? '&' : '',
                $parameter->variadic ? '...' : '',
                $parameter->name,
                $parameter->hasDefault() ? ' = ' . self::shownDefault($parameter->default()) : '',
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
            'Traversable', 'Iterator' => $returning('new \\ArrayIterator([])'),
            default => null,
        };
    }

    /**
     * An answer's class: a MatchableDouble standing for the call that
     * answered it, of the class it extends and the interfaces it implements,
     * initialised as a base PHP requires is.
     *
     * @param list<string> $interfaces
     */
    private static function wrapperCode(string $className, ?string $extends, array $interfaces, bool $initialised, string $methodName, string $methods): string
    {
        $extends = $extends === null ? '' : 'extends ' . $extends . ' ';
        $implements = implode(', ', ['\\PhpSpec\\Mock\\MatchableDouble', ...$interfaces]);
        $initialised = $initialised ? 'parent::__construct();' : '';

        return <<<PHP
class $className {$extends}implements $implements {
    use \\PhpSpec\\Mock\\MatchableDoubleBehaviour;

    public function __construct(object \$mockedObject) {
        $initialised
        \$this->______phpspec_mockedObject = \$mockedObject;
        \$this->______phpspec_methodName = '$methodName';
    }

    $methods
}
PHP;
    }

    /**
     * Why PHP refuses a plain class implementing a built-in interface, asked
     * of a child php once per interface in this process.
     *
     * @return Closure(class-string): ?string
     */
    private static function refusedBy(): Closure
    {
        return (self::$probe ??= new ImplementationProbe())->refusal(...);
    }

    /**
     * What a method returns when PHP lets no double be its return type: the
     * reason, said when the method is called.
     */
    private static function refusedReturnCode(string $class, string $methodName, string $returnType, string $why): string
    {
        $message = sprintf('PhpSpec cannot stand in for the return type of %s::%s(): no double of %s can be made. %s', ltrim($class, '\\'), $methodName, ltrim($returnType, '\\'), $why);

        return 'throw new \\LogicException(' . var_export($message, true) . ');';
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
    private static function defaultCode(ContractParameter $parameter): string
    {
        if (!$parameter->hasDefault()) {
            return '';
        }

        return sprintf(
            'if ($%1$s === \\PhpSpec\\Mock\\LeftOut::Argument) { static $__closure_%1$s = null; $%1$s = (new \\ReflectionParameter([%2$s, %3$s], %4$s))->getDefaultValue(); if ($%1$s instanceof \\Closure) { $%1$s = $__closure_%1$s ??= $%1$s; } }',
            $parameter->name,
            var_export($parameter->source->getDeclaringClass()?->getName(), true),
            var_export($parameter->source->getDeclaringFunction()->getName(), true),
            var_export($parameter->source->getName(), true),
        );
    }

    /**
     * The arguments a stub's callback is handed, each one the method takes by
     * reference handed as that reference, so what the callback writes into it
     * reaches the caller; none when the method takes nothing by reference.
     * The reference first takes the argument as it was held, so the caller's
     * variable is converted to the parameter's type as PHP converts it.
     *
     * @param list<ContractParameter> $parameters
     */
    private static function passingCode(array $parameters): string
    {
        $references = '';

        foreach ($parameters as $parameter) {
            if (!$parameter->byReference) {
                continue;
            }

            $references .= $parameter->variadic
                ? sprintf(' foreach (\\array_keys($%1$s) as $__key) { $__at = \\is_int($__key) ? %2$d + $__key : $__key; if (\\array_key_exists($__at, $__passing)) { $%1$s[$__key] = $__passing[$__at]; $__passing[$__at] = &$%1$s[$__key]; } }', $parameter->name, $parameter->position)
                : sprintf(' if (\\array_key_exists(%2$d, $__passing)) { $%1$s = $__passing[%2$d]; $__passing[%2$d] = &$%1$s; }', $parameter->name, $parameter->position);
        }

        return $references === '' ? '' : '$__passing = $__args;' . $references;
    }

    /**
     * What a method returns when nothing can stand in for its return type: an
     * explanation, where PHP alone would say a value of that type was expected
     * and none returned, and the way out the double offers.
     */
    private static function unbuildableReturnCode(string $class, string $methodName, string $returnType): string
    {
        $why = class_exists($returnType) && (new ReflectionClass($returnType))->isFinal()
            ? "$returnType is final, so no double of it can be made"
            : "no double of $returnType can be made";
        $cannot = sprintf('PhpSpec cannot stand in for the return type of %s::%s(): %s.', $class, $methodName, $why);

        return 'throw new \\LogicException(' . var_export($cannot, true) . ' . $this->______PhpSpecWayOut(' . var_export($methodName, true) . '));';
    }

    /**
     * What a method returns when no double of its return type can be made: an
     * instance of the very class, built without its constructor, for allow()
     * to replace. A lenient double has nobody about to replace it, so it
     * refuses instead.
     */
    private static function placeholderReturnCode(string $class, string $methodName, string $returnType): string
    {
        $refusal = self::unbuildableReturnCode($class, $methodName, $returnType);

        if (!class_exists($returnType) || (new ReflectionClass($returnType))->isInternal()) {
            return $refusal;
        }

        $placeholder = sprintf(
            '$__ret = (new \ReflectionClass(%s))->newInstanceWithoutConstructor(); \PhpSpec\Mock\Expectation::$lastMockReturn = $__ret; return $__ret;',
            var_export($returnType, true),
        );

        return "if (\$this->______phpspec_lenient) { $refusal } $placeholder";
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
     * @param list<ReflectionClass<object>> $types
     */
    private static function undefinedMethodGuard(array $types, string $doubled): string
    {
        foreach ($types as $type) {
            if ($type->hasMethod('__call')) {
                return '';
            }
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
     * a type-appropriate default, or an answer of the type it returns.
     *
     * @param list<MethodContract> $contracts the methods, each resolved from every declaration of it
     * @return string the generated PHP method code
     * @throws ReflectionException
     */
    private static function generateMethods(array $contracts): string
    {
        $mockMethods = [];
        foreach ($contracts as $contract) {
            $owner = $contract->owner;
            $methodName = $contract->name;
            $returnedType = $contract->returnType;
            $simpleReturnName = self::getSimpleReturnTypeName($returnedType);
            $isNullable = self::isNullableType($returnedType);
            $isEnum = self::isClassReturnType($returnedType) && $simpleReturnName !== null && enum_exists($simpleReturnName);

            [$paramString, $defaults] = self::parametersCode($contract);
            $passing = self::passingCode($contract->parameters);
            $callWith = $passing === '' ? '$__args' : '$__passing';
            $called = $contract->byReference
                ? "\$__cb = \$__match['data']; if ((new \\ReflectionFunction(\\Closure::fromCallable(\$__cb)))->returnsReference()) { \$__ret = &\$__cb(...$callWith); } else { \$__ret = \$__cb(...$callWith); }"
                : "\$__ret = (\$__match['data'])(...$callWith);";
            $arguments = self::argumentsCode($contract->parameters);
            $named = self::namedCode($contract->parameters, $methodName);
            $hold = self::holdCode($contract);
            $holdAnswer = $returnedType !== null ? sprintf('($this->______phpspec_returnTypes[%2$s] ??= new \\PhpSpec\\Mock\\ReturnType(%1$s, %2$s, self::class))->holdAnswer($__ret);', var_export($owner, true), var_export($methodName, true)) : '';

            // Unique suffix for MockedMethodFor classes, includes parent class hash to avoid collisions
            $wrapperSuffix = ucfirst($methodName) . '_' . substr(md5($owner . '::' . $methodName), 0, 8) . '_' . uniqid();
            [$className, $refusedReturn, $answerTypes] = match (true) {
                $contract->noValue !== null => [null, null, []],
                $returnedType === null => [self::untypedAnswerClass($methodName, $wrapperSuffix), null, []],
                default => self::answerClassFor($returnedType, $methodName, $wrapperSuffix),
            };

            $return = '';
            if ($contract->noValue !== null) {
                $return = 'throw new \\LogicException(' . var_export($contract->noValue, true) . ');';
            } elseif ($className !== null && $answerTypes !== []) {
                $return = sprintf('$__ret = $this->______phpspec_lenient ? \\PhpSpec\\Mock\\Double::answerTo($this, %s, %s) : new \\%s($this); return $__ret;', var_export($methodName, true), var_export($answerTypes, true), $className);
            } elseif ($className !== null) {
                $return = '$__ret = new \\' . $className . '($this); return $__ret;';
            } elseif ($isNullable && $simpleReturnName !== 'mixed') {
                $return = self::returning('null');
            } elseif ($refusedReturn !== null && $returnedType !== null) {
                $return = self::refusedReturnCode($owner, $methodName, (string) $returnedType, $refusedReturn);
            } elseif ($returnedType instanceof ReflectionUnionType) {
                $return = self::getUnionReturnCode($returnedType);
            } elseif ($returnedType instanceof ReflectionIntersectionType) {
                $return = self::returning('null');
            } elseif ($isEnum) {
                $return = "\$__ret = \\{$simpleReturnName}::cases()[0]; return \$__ret;";
            } elseif ($simpleReturnName !== null) {
                $return = self::getBuiltinReturnCode($simpleReturnName)
                    ?? self::placeholderReturnCode($owner, $methodName, $simpleReturnName);
            }

            $guard = '';
            if ($simpleReturnName !== 'never') {
                $guard = "if (\$__match === null) { \$this->______PhpSpecHoldUnlessExpected('$methodName', \$__args, \$__frame); }";
            }

            $stubCheck = '';
            if ($simpleReturnName === 'void') {
                $stubCheck = "\$__match = \\PhpSpec\\Mock\\ArgumentMatcher::among(\$__args) ? null : \$this->______PhpSpecStubs()->answer('$methodName', \$__args, \$__made);"
                    . " if (\$__match !== null && \$__match['type'] === 'withheld') { \\PhpSpec\\Mock\\Expectation::\$withheld = \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); \$__match = null; }"
                    . " if (\$__match !== null) { if (\$__match['type'] === 'throw') { throw \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); }"
                    . " if (\$__match['type'] === 'callback') { (\$__match['data'])(...$callWith); }"
                    . ' \\PhpSpec\\Mock\\Expectation::$lastMockReturn = null; return; }';
            } elseif ($simpleReturnName !== 'never') {
                $stubCheck = "\$__match = \\PhpSpec\\Mock\\ArgumentMatcher::among(\$__args) ? null : \$this->______PhpSpecStubs()->answer('$methodName', \$__args, \$__made);"
                    . " if (\$__match !== null && \$__match['type'] === 'withheld') { \\PhpSpec\\Mock\\Expectation::\$withheld = \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); \$__match = null; }"
                    . " if (\$__match !== null) { if (\$__match['type'] === 'throw') { throw \$__match['data']['throwable'] ?? new \$__match['data']['class'](\$__match['data']['message']); }"
                    . " if (\$__match['type'] === 'callback') { $called $holdAnswer \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = \$__ret; return \$__ret; }"
                    . " if (\$__match['type'] === 'value') { \$__ret = \$__match['data']; \\PhpSpec\\Mock\\Expectation::\$lastMockReturn = \$__ret; return \$__ret; } }";
            }

            $byReference = $contract->byReference ? '&' : '';
            $returnTypeCode = $contract->returnCode;

            // put it all together
            $mockMethods[] = <<<PHP
    public function {$byReference}$methodName($paramString)$returnTypeCode {
        $defaults
        $hold
        $named
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

    /**
     * The code for a method's parameters, and for the defaults its signature
     * cannot hold, built when a call leaves them out. Every parameter takes
     * mixed, so an ArgumentMatcher can be handed to it, and has a default, so
     * the spec can write the call with fewer arguments.
     *
     * @return array{0: string, 1: string}
     */
    private static function parametersCode(MethodContract $contract): array
    {
        $parameters = [];
        $defaults = '';

        foreach ($contract->parameters as $parameter) {
            $type = 'mixed ' . ($parameter->byReference ? '&' : '');

            if ($parameter->variadic) {
                $parameters[] = $type . '...$' . $parameter->name;
            } elseif ($parameter->optional && $parameter->hasDefault() && self::writable($parameter->default())) {
                $parameters[] = $type . '$' . $parameter->name . ' = ' . var_export($parameter->default(), true);
            } else {
                $parameters[] = $type . '$' . $parameter->name . ($parameter->hasDefault() ? ' = \\PhpSpec\\Mock\\LeftOut::Argument' : ' = null');
                $defaults .= self::defaultCode($parameter);
            }
        }

        return [implode(', ', $parameters), $defaults];
    }

    /**
     * The class of what a call nobody stubbed answers when its method returns
     * a class or an interface, or a union of them alone: one of the first type
     * that can have one, there to be handed along, refusing to be used, and
     * the types it is of, for a lenient double to answer a dummy of instead.
     * With no class, the reason PHP lets no class be the type, when it gives
     * one.
     *
     * @return array{0: string|null, 1: string|null, 2: list<class-string>}
     */
    private static function answerClassFor(ReflectionType $type, string $methodName, string $suffix): array
    {
        $members = $type instanceof ReflectionUnionType
            ? ($type->allowsNull() || self::holdsScalar($type) ? [] : $type->getTypes())
            : [$type];
        $refusal = null;

        foreach ($members as $member) {
            $types = self::typesOf($member);

            if ($types === null) {
                continue;
            }

            $names = array_map(static fn(ReflectionClass $type): string => $type->getName(), $types);

            if (DeclaredType::allOf($names)->admitsNoValue()) {
                $last = array_pop($names);
                $refusal ??= sprintf('No object can be %s %s and %s.', count($names) === 1 ? 'both' : 'all of', implode(', ', $names), $last);

                continue;
            }

            [$className, $refused] = self::answerClass($types, $methodName, $suffix);

            if ($className !== null) {
                return [$className, null, array_map(static fn(ReflectionClass $type): string => $type->getName(), $types)];
            }

            $refusal ??= $refused;
        }

        return [null, $refusal, []];
    }

    private static function holdsScalar(ReflectionUnionType $type): bool
    {
        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionNamedType && in_array($member->getName(), ['string', 'int', 'float', 'bool', 'array'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The classes and interfaces a type names, or null when it names
     * something else: a built-in type, an enum, a type that does not exist.
     *
     * @return non-empty-list<ReflectionClass<object>>|null
     */
    private static function typesOf(ReflectionType $type): ?array
    {
        $named = $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type];
        $types = [];

        foreach ($named as $member) {
            if (!$member instanceof ReflectionNamedType || $member->isBuiltin()) {
                return null;
            }

            $name = $member->getName();

            if ((!class_exists($name) && !interface_exists($name)) || enum_exists($name)) {
                return null;
            }

            $types[] = new ReflectionClass($name);
        }

        usort($types, static fn(ReflectionClass $one, ReflectionClass $other): int => $one->isInterface() <=> $other->isInterface());

        return $types === [] ? null : $types;
    }

    /**
     * The class of an answer of the given types: a subclass of a class that
     * can have one, built without its constructor, implementing the
     * interfaces, on the base PHP requires for them.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @return array{0: string|null, 1: string|null} the class, or the reason no class can be the types
     */
    private static function answerClass(array $types, string $methodName, string $suffix): array
    {
        $first = $types[0];

        if (!$first->isInterface() && ($first->isFinal() || $first->isReadOnly())) {
            return [null, null];
        }

        foreach ($types as $type) {
            if ($type->isInterface() && $type->isInternal()) {
                return [null, null];
            }
        }

        [$base, $methods, $refusal] = self::planOf($types);

        if ($methods === null) {
            return [null, $refusal];
        }

        $className = 'MockedMethodFor' . $suffix;
        $interfaces = array_map(static fn(ReflectionClass $type): string => '\\' . $type->getName(), array_values(array_filter($types, static fn(ReflectionClass $type): bool => $type->isInterface())));
        $extends = $first->isInterface() ? $base?->extends() : '\\' . $first->getName();

        return [self::evaluated($className, self::wrapperCode($className, $extends, [...$interfaces, ...($base?->implements() ?? [])], $first->isInterface() && $base?->extends() !== null, $methodName, self::answerMethods($methods->contracts, $base))), null];
    }

    /**
     * What a double of the types is built on, and the methods it defines; or
     * why no class can be the types.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @return array{0: NativeBase|null, 1: MethodSet|null, 2: string|null}
     */
    private static function planOf(array $types): array
    {
        $interfaces = array_values(array_filter($types, static fn(ReflectionClass $type): bool => $type->isInterface()));
        $base = $interfaces === [] ? null : new NativeBase($interfaces, self::refusedBy());

        if ($base?->refusal() !== null) {
            return [null, null, $base->refusal()];
        }

        $class = $types[0]->isInterface() ? null : $types[0];

        if ($class !== null && $base !== null && ($base->extends() !== null || $base->implements() !== [])) {
            $needed = ltrim($base->extends() ?? Traversable::class, '\\');

            if (!is_a($class->getName(), $needed, true)) {
                return [null, null, sprintf('No object can be both %s and %s.', $class->getName(), implode(' and ', array_map(static fn(ReflectionClass $type): string => $type->getName(), $interfaces)))];
            }

            $base = null;
        }

        $methods = MethodSet::of($types, $base);

        return $methods->refusal === null ? [$base, $methods, null] : [null, null, $methods->refusal];
    }

    /**
     * The methods of an answer: each the types declare, refusing to be used.
     *
     * @param list<MethodContract> $contracts
     */
    private static function answerMethods(array $contracts, ?NativeBase $base): string
    {
        $methods = [];

        foreach ($contracts as $contract) {
            $methods[] = sprintf(
                '    public function %s%s(%s)%s { throw $this->______PhpSpecUsed(%s, %s, \\func_get_args(), \\debug_backtrace(\\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? []); }',
                $contract->byReference ? '&' : '',
                $contract->name,
                self::parametersCode($contract)[0],
                $contract->returnCode,
                var_export($contract->owner, true),
                var_export($contract->name, true),
            );
        }

        return implode("\n", $methods) . ($base?->code() ?? '');
    }

    /**
     * The class of what a method with no return type answers: a handle on the
     * call, for allow() and expect().
     */
    private static function untypedAnswerClass(string $methodName, string $suffix): string
    {
        $className = 'MockedMethodFor' . $suffix;

        eval(self::wrapperCode($className, null, [], false, $methodName, ''));

        return $className;
    }

    /**
     * The class, once its code is evaluated, or null when PHP refuses it.
     */
    private static function evaluated(string $className, string $code): ?string
    {
        try {
            eval($code);
        } catch (Throwable) {
            return null;
        }

        return $className;
    }
}
