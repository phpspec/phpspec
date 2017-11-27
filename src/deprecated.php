<?php

spl_autoload_register(function ($class) {

    $deprecatedClasses = [
        'PhpSpec\Matcher\ApproximatelyMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ApproximatelyMatcher',
        'PhpSpec\Matcher\ArrayContainMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ArrayContainMatcher',
        'PhpSpec\Matcher\ArrayCountMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ArrayCountMatcher',
        'PhpSpec\Matcher\ArrayKeyMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ArrayKeyMatcher',
        'PhpSpec\Matcher\ArrayKeyValueMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ArrayKeyValueMatcher',
        'PhpSpec\Matcher\BasicMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\BasicMatcher',
        'PhpSpec\Matcher\CallbackMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\CallbackMatcher',
        'PhpSpec\Matcher\ComparisonMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ComparisonMatcher',
        'PhpSpec\Matcher\IdentityMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\IdentityMatcher',
        'PhpSpec\Matcher\Iterate\IterablesMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\Iterate\IterablesMatcher',
        'PhpSpec\Matcher\Iterate\SubjectElementDoesNotMatchException' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\Iterate\SubjectElementDoesNotMatchException',
        'PhpSpec\Matcher\Iterate\SubjectHasFewerElementsException' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\Iterate\SubjectHasFewerElementsException',
        'PhpSpec\Matcher\Iterate\SubjectHasMoreElementsException' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\Iterate\SubjectHasMoreElementsException',
        'PhpSpec\Matcher\IterateAsMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\IterateAsMatcher',
        'PhpSpec\Matcher\IterateLikeMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\IterateLikeMatcher',
        'PhpSpec\Matcher\ObjectStateMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ObjectStateMatcher',
        'PhpSpec\Matcher\ScalarMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ScalarMatcher',
        'PhpSpec\Matcher\StartIteratingAsMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\StartIteratingAsMatcher',
        'PhpSpec\Matcher\StringContainMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\StringContainMatcher',
        'PhpSpec\Matcher\StringEndMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\StringEndMatcher',
        'PhpSpec\Matcher\StringRegexMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\StringRegexMatcher',
        'PhpSpec\Matcher\StringStartMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\StringStartMatcher',
        'PhpSpec\Matcher\ThrowMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\ThrowMatcher',
        'PhpSpec\Matcher\TraversableContainMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\TraversableContainMatcher',
        'PhpSpec\Matcher\TraversableCountMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\TraversableCountMatcher',
        'PhpSpec\Matcher\TraversableKeyMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\TraversableKeyMatcher',
        'PhpSpec\Matcher\TraversableKeyValueMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\TraversableKeyValueMatcher',
        'PhpSpec\Matcher\TriggerMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\TriggerMatcher',
        'PhpSpec\Matcher\TypeMatcher' => 'PhpSpec\Extensions\DefaultMatchers\Matcher\TypeMatcher',
    ];
    
    if (array_key_exists($class, $deprecatedClasses)) {
        $new = $deprecatedClasses[$class];
        class_alias($new, $class);
    }
});
