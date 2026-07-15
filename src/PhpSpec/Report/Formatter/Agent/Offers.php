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

namespace PhpSpec\Report\Formatter\Agent;

/**
 * @internal
 * Turns a run's code-generation candidates (the interactive runner's "shall I
 * create this?" offers) into flat, deduplicated JSON data — {action, target} —
 * so an agent can act on them (write the code, or run --accept-offers) instead
 * of being prompted. Operates on the plain GenerationCandidates::toArray() shape
 * so the formatter stays free of the console-command layer.
 */
final class Offers
{
    /**
     * @param array<string, mixed> $candidates the GenerationCandidates::toArray() output
     * @return list<array{action: string, target: string, value?: string}>
     */
    public static function fromCandidates(array $candidates): array
    {
        $offers = [];
        $seen = [];

        $add = static function (string $action, string $target, ?string $value = null) use (&$offers, &$seen): void {
            $key = $action . ' ' . $target;
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $offer = ['action' => $action, 'target' => $target];
            if ($value !== null) {
                $offer['value'] = $value;
            }

            $offers[] = $offer;
        };

        foreach (self::fqcns($candidates, 'missingSpecClasses') as $fqcn) {
            $add('create_class', $fqcn);
        }

        foreach (self::fqcns($candidates, 'missingStepClasses') as $fqcn) {
            $add('create_class', $fqcn);
        }

        foreach (self::fqcns($candidates, 'missingMockTypes') as $fqcn) {
            $add('create_interface', $fqcn);
        }

        foreach (self::methodTargets($candidates, 'undefinedClassMethods') as $target) {
            $add('create_method', $target);
        }

        foreach (self::methodTargets($candidates, 'undefinedMockInterfaceMethods') as $target) {
            $add('create_method', $target);
        }

        foreach (self::fakeableMethods($candidates) as [$target, $value]) {
            $add('fake_method', $target, $value);
        }

        foreach (self::featurePaths($candidates) as $path) {
            $add('create_steps', $path);
        }

        return $offers;
    }

    /**
     * Derives the single generation offer implied by an error message — a
     * missing class, mock interface, or method — or null when the error is not
     * something a generator can resolve. This lets each errored example carry
     * its own offer, not just the run-wide summary.
     *
     * @return array{action: string, target: string}|null
     */
    public static function forError(string $message): ?array
    {
        if (preg_match('/^Class "([^"]+)" not found$/', $message, $matches)) {
            return ['action' => 'create_class', 'target' => $matches[1]];
        }

        if (preg_match('/Cannot create mock: class or interface \'([^\']+)\' does not exist/', $message, $matches)) {
            return ['action' => 'create_interface', 'target' => $matches[1]];
        }

        if (preg_match('/Call to undefined method ([^:]+)::([A-Za-z0-9_]+)\(\)/', $message, $matches)) {
            return ['action' => 'create_method', 'target' => $matches[1] . '::' . $matches[2]];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $candidates
     * @return list<string>
     */
    private static function fqcns(array $candidates, string $key): array
    {
        $values = $candidates[$key] ?? [];

        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }

    /**
     * @param array<string, mixed> $candidates
     * @return list<string>
     */
    private static function methodTargets(array $candidates, string $key): array
    {
        $values = $candidates[$key] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $targets = [];
        foreach ($values as $method) {
            if (is_array($method) && is_string($method['className'] ?? null) && is_string($method['methodName'] ?? null)) {
                $targets[] = $method['className'] . '::' . $method['methodName'];
            }
        }

        return $targets;
    }

    /**
     * Existing empty methods that --fake could fill, paired with the return
     * expression it would insert (derived from the spec's expectation).
     *
     * @param array<string, mixed> $candidates
     * @return list<array{0: string, 1: string}> [Class::method, returnExpression] pairs
     */
    private static function fakeableMethods(array $candidates): array
    {
        $values = $candidates['fakeableMethods'] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $targets = [];
        foreach ($values as $method) {
            if (is_array($method)
                && is_string($method['className'] ?? null)
                && is_string($method['methodName'] ?? null)
                && is_string($method['fakeExpression'] ?? null)
            ) {
                $targets[] = [$method['className'] . '::' . $method['methodName'], $method['fakeExpression']];
            }
        }

        return $targets;
    }

    /**
     * @param array<string, mixed> $candidates
     * @return list<string>
     */
    private static function featurePaths(array $candidates): array
    {
        $values = $candidates['undefinedSteps'] ?? [];

        return is_array($values) ? array_values(array_filter(array_keys($values), 'is_string')) : [];
    }
}
