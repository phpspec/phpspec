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

    /** Whether PHP lets a plain class implement a built-in interface, asked once per interface in this process */
    private static ?ImplementationProbe $probe = null;

    /**
     * Resets the class cache between in-process runs.
     */
    public static function resetCache(): void
    {
        self::$classCache = [];
        Answers::reset();
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
            [$modifier, $wayOut] = match (true) {
                $reflectionClass->isFinal() && $reflectionClass->isReadOnly() => ['final readonly', 'neither final nor readonly'],
                $reflectionClass->isFinal() => ['final', 'non-final'],
                default => ['readonly', 'not readonly'],
            };

            throw new LogicException(sprintf('Cannot create a test double for %s class %s. Extract an interface and type-hint against it, or make the class %s.', $modifier, ltrim($class, '\\'), $wayOut));
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
     * Generates the class of a double of the given types: a subclass of a
     * class, or an implementation of interfaces on the base PHP requires for
     * them.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @return string the generated class name
     * @throws ReflectionException
     */
    public static function classOf(array $types, ?NativeBase $base, MethodSet $methods): string
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
        $extends = $base?->extends() ?? ($types[0]->isInterface() ? null : '\\' . $names[0]);
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
     * Instantiates the generated double class. A double of a class is made
     * without the class's constructor, which would run the application's
     * initialization with arguments nobody gave: properties keep the defaults
     * they declare, and those the constructor sets stay unset. A double of an
     * interface built on a base PHP requires is initialised as that base is.
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

        $double = new ReflectionClass($mockClassName);

        return $reflectionClass->isInterface() ? $double->newInstance() : $double->newInstanceWithoutConstructor();
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
    private static function unbuildableReturnCode(string $class, string $methodName, string $returnType, bool $stubbable): string
    {
        $why = class_exists($returnType) && (new ReflectionClass($returnType))->isFinal()
            ? "$returnType is final, so no double of it can be made"
            : "no double of $returnType can be made";
        $cannot = sprintf('PhpSpec cannot stand in for the return type of %s::%s(): %s.', $class, $methodName, $why);

        return 'throw new \\LogicException(' . var_export($cannot, true) . ($stubbable ? ' . $this->______PhpSpecWayOut(' . var_export($methodName, true) . ')' : '') . ');';
    }

    /**
     * What a method returns when no double of its return type can be made: an
     * instance of the very class, built without its constructor, for allow()
     * to replace. A lenient double has nobody about to replace it, so it
     * refuses the code under spec instead, still handing the spec's own call
     * the instance its stub needs. A type no instance of which can be built
     * this way is refused, with no advice a stub could not follow.
     */
    private static function placeholderReturnCode(string $class, string $methodName, string $returnType): string
    {
        if (!class_exists($returnType) || (new ReflectionClass($returnType))->isInternal()) {
            return self::unbuildableReturnCode($class, $methodName, $returnType, false);
        }

        $placeholder = sprintf(
            '$__ret = (new \ReflectionClass(%s))->newInstanceWithoutConstructor(); \PhpSpec\Mock\Expectation::$lastMockReturn = $__ret; return $__ret;',
            var_export($returnType, true),
        );

        return sprintf('if ($this->______phpspec_lenient && $__made) { %s } %s', self::unbuildableReturnCode($class, $methodName, $returnType, true), $placeholder);
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
     * Whether the type is a built-in interface or null, which a call nobody
     * stubbed answers with null rather than a double.
     */
    private static function isNullableBuiltIn(?ReflectionType $type): bool
    {
        return $type instanceof ReflectionNamedType && $type->allowsNull() && interface_exists($type->getName()) && (new ReflectionClass($type->getName()))->isInternal();
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
            if ($contract->static) {
                $mockMethods[] = $contract->staticCode();

                continue;
            }

            $owner = $contract->owner;
            $methodName = $contract->name;
            $returnedType = $contract->returnType;
            $simpleReturnName = self::getSimpleReturnTypeName($returnedType);
            $isNullable = self::isNullableType($returnedType);
            $isEnum = self::isClassReturnType($returnedType) && $simpleReturnName !== null && enum_exists($simpleReturnName);

            [$paramString, $defaults] = $contract->parametersCode();
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
                $contract->noValue !== null, self::isNullableBuiltIn($returnedType) => [null, null, []],
                $returnedType === null => [Answers::untypedClass($methodName, $wrapperSuffix), null, []],
                default => Answers::classFor($returnedType, $methodName, $wrapperSuffix),
            };

            $return = '';
            if ($contract->noValue !== null) {
                $return = 'throw new \\LogicException(' . var_export($contract->noValue, true) . ');';
            } elseif ($className !== null && $answerTypes !== []) {
                $return = sprintf('$__ret = $this->______phpspec_lenient ? \\PhpSpec\\Mock\\Answers::dummy($this, %s, %s) : (new \\ReflectionClass(\\%s::class))->newInstanceWithoutConstructor()->______PhpSpecStandFor($this); return $__ret;', var_export($methodName, true), var_export($answerTypes, true), $className);
            } elseif ($className !== null) {
                $return = sprintf('$__ret = (new \\ReflectionClass(\\%s::class))->newInstanceWithoutConstructor()->______PhpSpecStandFor($this); return $__ret;', $className);
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
     * What a double of the types is built on, and the methods it defines; or
     * why no class can be the types.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @param list<string>|null $emptyIteratorAfter when the double answers a dummy aggregate's getIterator(), the combinations of types being iterated already; null otherwise
     * @param bool $iterated whether the double iterates by itself, where one that refuses every call never does
     * @return array{0: NativeBase|null, 1: MethodSet|null, 2: string|null}
     */
    public static function planOf(array $types, ?array $emptyIteratorAfter = null, bool $iterated = true): array
    {
        $interfaces = array_values(array_filter($types, static fn(ReflectionClass $type): bool => $type->isInterface()));
        $base = $interfaces === [] && $emptyIteratorAfter === null ? null : new NativeBase($interfaces, self::refusedBy(), $types[0]->isInterface() ? null : $types[0], [], $emptyIteratorAfter);

        if ($base?->refusal($iterated) !== null) {
            return [null, null, $base->refusal($iterated)];
        }

        $methods = MethodSet::of($types, $base);

        return $methods->refusal === null ? [$base, $methods, null] : [null, null, $methods->refusal];
    }
}
