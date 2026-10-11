<?php

// Code under spec, as a double sees it: a function in a file outside the spec
// folder, so its calls use stubs up and meet the exceptions they throw.
if (!defined('CODE_UNDER_SPEC')) {
    define('CODE_UNDER_SPEC', sys_get_temp_dir() . '/phpspec_code_under_spec_' . getmypid() . '.php');
}

if (!function_exists('code_under_spec_calls')) {
    register_shutdown_function(static function (): void {
        if (is_file(CODE_UNDER_SPEC)) {
            unlink(CODE_UNDER_SPEC);
        }
    });
    file_put_contents(CODE_UNDER_SPEC, "<?php\nfunction code_under_spec_calls(object \$double, string \$method, mixed ...\$arguments): mixed { return \$double->\$method(...\$arguments); }\n");
    require CODE_UNDER_SPEC;
}
