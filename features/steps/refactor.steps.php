<?php

/**
 * Refactor command steps — When steps that execute phpspec refactor
 * in the temporary project directory.
 */

when('I run phpspec refactor {string}', function (string $target) {
    _phpspec_exec($this, 'refactor ' . $target);
});

given('Xdebug is available', function () {
    if (!extension_loaded('xdebug')) {
        skip('Xdebug is not available');
    }
});
