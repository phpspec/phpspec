<?php

// Code under spec that reaches into PhpSpec from beyond the spec tree: the
// function lives in a file written to the temp dir, so an error it raises is
// blamed on the spec line that called it, not on a spec file.
if (!defined('BLAME_SPEC_OUTSIDER')) {
    define('BLAME_SPEC_OUTSIDER', sys_get_temp_dir() . '/phpspec_blame_outsider_' . getmypid() . '.php');
}

if (!function_exists('blame_spec_outsider_reaches_in')) {
    register_shutdown_function(static function (): void {
        if (is_file(BLAME_SPEC_OUTSIDER)) {
            unlink(BLAME_SPEC_OUTSIDER);
        }
    });
    file_put_contents(BLAME_SPEC_OUTSIDER, "<?php\nfunction blame_spec_outsider_reaches_in(): void { \\PhpSpec\\Mock\\Double::getInstance('Nope\\Missing'); }\n");
    require BLAME_SPEC_OUTSIDER;
}
