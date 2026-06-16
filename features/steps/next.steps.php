<?php

/**
 * Next command steps — When steps that execute phpspec next
 * in the temporary project directory.
 */

when('I run phpspec next', function () {
    _phpspec_exec($this, 'next');
});
