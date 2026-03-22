<?php

/**
 * Sanity-checks a release for consistency.
 *
 * Verifies that the version in bin/phpspec matches the composer.json
 * branch alias and has a corresponding CHANGES.md entry.
 */

// Get the version reported by bin/phpspec
$binary = file_get_contents('bin/phpspec');
if ($binary === false || !preg_match_all('/(?<major>[5-9])\.(?<minor>[0-9]+)\.(?<patch>[0-9]+)/', $binary, $matches) || count($matches[0]) !== 1) {
    echo "👎 could not read version from binary file\n";
    exit(1);
}

[
    0 => [0 => $version],
    'major' => [0 => $major],
    'minor' => [0 => $minor],
    'patch' => [0 => $patch],
] = $matches;

echo "Verifying version $version\n";

// Check composer.json branch alias
$composer = file_get_contents('composer.json');
if ($composer === false) {
    echo "👎 could not read composer.json\n";
    exit(1);
}

if (!preg_match("/$major\.$minor\.x-dev/", $composer, $matches)) {
    echo "👎 composer.json does not contain matching branch alias\n";
    exit(1);
}

echo "👍 composer.json contains branch alias {$matches[0]}\n";

// Check CHANGES.md heading
$changelog = file_get_contents('CHANGES.md');
if ($changelog === false) {
    echo "👎 could not read CHANGES.md\n";
    exit(1);
}

if (!preg_match("/## \[$major\.$minor\.$patch\]/", $changelog, $matches)) {
    echo "👎 CHANGES.md does not contain matching heading\n";
    exit(1);
}

echo "👍 CHANGES.md contains heading '{$matches[0]}'\n";

// Check PHPStan config exists
if (!file_exists('phpstan.neon')) {
    echo "👎 phpstan.neon is missing\n";
    exit(1);
}

echo "👍 phpstan.neon exists\n";

// Check spec suite passes (dry run — just verify the binary works)
$specOutput = [];
$specExit = 0;
exec('php bin/phpspec run --format=dot 2>&1', $specOutput, $specExit);
if ($specExit !== 0) {
    echo "👎 spec suite fails (exit code $specExit)\n";
    exit(1);
}

echo "👍 spec suite passes\n";

exit(0);
