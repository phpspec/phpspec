<?php

namespace PhpSpec\Extensions\DefaultMatchers;

use PhpSpec\Matcher;
use PhpSpec\ServiceContainer;
use PhpSpec\ServiceContainer\IndexedServiceContainer;

class Extension implements \PhpSpec\Extension
{
    /**
     * @param ServiceContainer $container
     * @param array $params
     */
    public function load(ServiceContainer $container, array $params)
    {
        $container->define('matchers.identity', function (IndexedServiceContainer $c) {
            return new Matcher\IdentityMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.comparison', function (IndexedServiceContainer $c) {
            return new Matcher\ComparisonMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.throwm', function (IndexedServiceContainer $c) {
            return new Matcher\ThrowMatcher($c->get('unwrapper'), $c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.trigger', function (IndexedServiceContainer $c) {
            return new Matcher\TriggerMatcher($c->get('unwrapper'));
        }, ['matchers']);
        $container->define('matchers.type', function (IndexedServiceContainer $c) {
            return new Matcher\TypeMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.object_state', function (IndexedServiceContainer $c) {
            return new Matcher\ObjectStateMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.scalar', function (IndexedServiceContainer $c) {
            return new Matcher\ScalarMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_count', function (IndexedServiceContainer $c) {
            return new Matcher\ArrayCountMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_key', function (IndexedServiceContainer $c) {
            return new Matcher\ArrayKeyMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_key_with_value', function (IndexedServiceContainer $c) {
            return new Matcher\ArrayKeyValueMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_contain', function (IndexedServiceContainer $c) {
            return new Matcher\ArrayContainMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_start', function (IndexedServiceContainer $c) {
            return new Matcher\StringStartMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_end', function (IndexedServiceContainer $c) {
            return new Matcher\StringEndMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_regex', function (IndexedServiceContainer $c) {
            return new Matcher\StringRegexMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_contain', function (IndexedServiceContainer $c) {
            return new Matcher\StringContainMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_count', function (IndexedServiceContainer $c) {
            return new Matcher\TraversableCountMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_key', function (IndexedServiceContainer $c) {
            return new Matcher\TraversableKeyMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_key_with_value', function (IndexedServiceContainer $c) {
            return new Matcher\TraversableKeyValueMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_contain', function (IndexedServiceContainer $c) {
            return new Matcher\TraversableContainMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.iterate', function (IndexedServiceContainer $c) {
            return new Matcher\IterateAsMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.iterate_like', function (IndexedServiceContainer $c) {
            return new Matcher\IterateLikeMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.start_iterating', function (IndexedServiceContainer $c) {
            return new Matcher\StartIteratingAsMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.approximately', function (IndexedServiceContainer $c) {
            return new Matcher\ApproximatelyMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
    }
}
