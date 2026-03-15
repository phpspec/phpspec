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

namespace PhpSpec\Ai;

use Closure;
use PhpSpec\Ai\Contracts\ToolInterface;

/**
 * A concrete AI tool implementation with a closure-based handler.
 */
final class Tool implements ToolInterface
{
    /**
     * @param string $name the tool name
     * @param string $description the tool description for the LLM
     * @param array<string, mixed> $parameterSchema JSON Schema for parameters
     * @param Closure $handler the function to execute when the tool is called
     */
    private function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly array $parameterSchema,
        private readonly Closure $handler,
    ) {}

    /**
     * Creates a new Tool instance.
     *
     * @param string $name tool name used in API calls
     * @param string $description description of the tool for the LLM
     * @param array<string, array<string, mixed>> $parameters parameter definitions keyed by name
     * @param Closure $handler the function to execute
     */
    public static function make(
        string $name,
        string $description,
        array $parameters,
        Closure $handler,
    ): self {
        return new self($name, $description, self::buildSchema($parameters), $handler);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameterSchema(): array
    {
        return $this->parameterSchema;
    }

    public function execute(array $arguments, mixed $context = null): mixed
    {
        return ($this->handler)($arguments, $context);
    }

    /**
     * Converts parameter definitions to JSON Schema format.
     *
     * @param array<string, array<string, mixed>> $parameters
     *
     * @return array<string, mixed>
     */
    private static function buildSchema(array $parameters): array
    {
        $properties = [];
        $required = [];

        foreach ($parameters as $paramName => $definition) {
            $prop = ['type' => $definition['type'] ?? 'string'];

            if (isset($definition['description'])) {
                $prop['description'] = $definition['description'];
            }

            if (isset($definition['enum'])) {
                $prop['enum'] = $definition['enum'];
            }

            $properties[$paramName] = $prop;

            if (!isset($definition['default'])) {
                $required[] = $paramName;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }
}
