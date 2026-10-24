<?php

/**
 * Sanity-checks a release for consistency.
 *
 * Verifies that the version being released, given as the only argument,
 * matches the composer.json branch alias and has a CHANGES.md entry.
 */

$version = $argv[1] ?? '';
if (!preg_match('/^(?<major>[5-9])\.(?<minor>[0-9]+)\.(?<patch>[0-9]+)(?<suffix>-[0-9A-Za-z.]+)?$/', $version, $matches)) {
    echo "👎 usage: php check-release.php <version>, e.g. 9.0.0-rc.6\n";
    exit(1);
}

['major' => $major, 'minor' => $minor] = $matches;

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

// Check CHANGES.md heading: the version being released, pre-release suffix included
$changelog = file_get_contents('CHANGES.md');
if ($changelog === false) {
    echo "👎 could not read CHANGES.md\n";
    exit(1);
}

if (!preg_match('/## \[' . preg_quote($version, '/') . '\]/', $changelog, $matches)) {
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

// Check spec suite passes (a dry run: just verify the binary works)
$specOutput = [];
$specExit = 0;
exec('php bin/phpspec run --format=dot 2>&1', $specOutput, $specExit);
if ($specExit !== 0) {
    echo "👎 spec suite fails (exit code $specExit)\n";
    exit(1);
}

echo "👍 spec suite passes\n";

exit(0);
