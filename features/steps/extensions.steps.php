<?php

/**
 * Extension steps — steps for testing the extension system.
 * File-content assertions live in assertions.steps.php; step titles are
 * unique across all steps files.
 */

when('I run phpspec command {string}', function (string $command) {
    _phpspec_exec($this, $command);
});
