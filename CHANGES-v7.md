# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [7.3.0]

### Added
 - PHP 8.2 support [@gquemener](https://github.com/gquemener)

### Fixed
 - Deprecation notices for dynamic properties under PHP 8.2 [@gquemener](https://github.com/gquemener)
 - Fixed CI config for PHP 8.2 [@rogervila](https://github.com/rogervila)

## [7.2.0]

### Added
 - PHP 8.1 support [@ADmad](https://github.com/ADmad), [@iambrosi](https://github.com/iambrosi), [@jaylinski](https://github.com/jaylinski)
 - Symfony 6.0 support [@loic425](https://github.com/ADmad)
 - TeamCity formatter [@AyrtonRicardo](https://github.com/AyrtonRicardo)
 - Display any Specs that have been ignored (e.g. due to broken namespaces) [@gquemener](https://github.com/gquemener)
 - Temporarily disallow intersection types when requesting Mocks [@ciaranmcnulty](https://github.com/ciaranmcnulty)

### Fixed
 - Error when checking an Exception with unset properties is thrown [@jmleroux](https://github.com/jmleroux)
 - Sort order of default matchers made consistent between PHP 7/8 [@dannyvw](https://github.com/dannyvw)
 - Removed redundant cast [@driesvints](https://github.com/drupol)

### Changed
 - Ship fewer files in the archive versions [@drupol](https://github.com/drupol)

## [7.1.0]
### Fixed
- Suppressed errors on PHP 8 no longer cause failing tests [@AlexandruGG](https://github.com/AlexandruGG)

### Added
- ApproximatelyMatcher can now compare arrays of floats [@ciaranmcnulty](https://github.com/ciaranmcnulty)

## [7.0.1]
### Fixed
- Collaborator generation didn't happen in some cases [@ciaranmcnulty](https://github.com/ciaranmcnulty)
- Some error diffs caused a strict type error on PHP 8 [@ciaranmcnulty](https://github.com/ciaranmcnulty)

## [7.0.0]

### Changed
 - Dropped support for PHP 7.2 [@ciaranmcnulty](https://github.com/ciaranmcnulty)
 - Dropped support for Symfony 4.x < 4.4 [@ciaranmcnulty](https://github.com/ciaranmcnulty)
 - More accurate error message for bad during* calls [@drupol](https://github.com/drupol)
 - Removed superfluous phpdoc and added bool return types where appropriate [@drupol](https://github.com/drupol)

[7.3.0]: https://github.com/phpspec/phpspec/compare/7.2.0...7.3.0
[7.2.0]: https://github.com/phpspec/phpspec/compare/7.1.0...7.2.0
[7.1.0]: https://github.com/phpspec/phpspec/compare/7.0.1...7.1.0
[7.0.1]: https://github.com/phpspec/phpspec/compare/7.0.0...7.0.1
[7.0.0]: https://github.com/phpspec/phpspec/compare/6.2.2...7.0.0
