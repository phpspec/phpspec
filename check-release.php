<?php

/**
 * Sanity-checks a release for consistency
 */



// get the version reported by phpspec --version
if (!preg_match_all('/(?<major>[5-9])\.(?<minor>[0-9]+)\.(?<patch>[0-9]+)/', file_get_contents('bin/phpspec'), $matches) || count($matches[0])!=1) {
    echo "👎 could not read version from binary file\n";
    exit(1);
}

[
    0 => [ 0 => $version],
    'major' => [ 0 => $major],
    'minor' => [ 0 => $minor],
    'patch' => [ 0 => $patch]
] = $matches;

echo "Verifying version $version \n" ;

$composer = file_get_contents('composer.json');

if (!preg_match("/$major\.$minor\.x-dev/", $composer, $matches)) {
    echo "👎 composer.json does not contain matching branch alias\n";
    exit(1);
}

echo "👍 composer.json contains branch alias {$matches[0]}\n";

$changelog = file_get_contents('CHANGES.md');

if (!preg_match("/## \[$major.$minor.$patch\]/", $changelog, $matches)) {
    echo "👎 CHANGES.md does not contain matching heading\n";
    exit(1);
}

echo "👍 CHANGES.md contains heading '{$matches[0]}'\n";

if (!preg_match("/\[$major.$minor.$patch\]: https:\/\/github\.com.*$major.$minor.$patch/", $changelog, $matches)) {
    echo "👎 CHANGES.md does not contain matching github diff\n";
    exit(1);
}

echo "👍 CHANGES.md contains link '{$matches[0]}'\n";

exit(0);
