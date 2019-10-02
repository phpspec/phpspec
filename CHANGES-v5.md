# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [5.1.2]
### Fixed
 - [fixed] Throw better exception when constructor returns null (@ddziaduch)

## [5.1.1]
### Fixed
 - [fixed] Avoid memory error in DotFormatter with large number of events (@lombartec) 
 
## [5.1.0]
### Added
 - PHP 7.3 compatibility (@ciaranmcnulty)
 - Configure verbosity option in configuration file (@DonCallisto)

## [5.0.3]
### Fixed
 - Error with scalarmatcher when type does not match (@DonCallisto)

## [5.0.2]
### Fixed
 - Better error message when trying to call method on scalar return type (@ciaranmcnulty)

## [5.0.1]
### Fixed
 - Type error when using Object State Matcher (@nightlinus)

## [5.0.0]
### Changed
 - Bumped minimum PHP and Symfony dependences (@ciaranmcnulty)
 - Added void type hints to codebase (@kix)

[Unreleased]: https://github.com/phpspec/phpspec/compare/5.1.1...5.1.2
[5.1.1]: https://github.com/phpspec/phpspec/compare/5.1.0...5.1.1
[5.1.0]: https://github.com/phpspec/phpspec/compare/5.0.3...5.1.0
[5.0.2]: https://github.com/phpspec/phpspec/compare/5.0.2...5.0.3
[5.0.2]: https://github.com/phpspec/phpspec/compare/5.0.1...5.0.2
[5.0.1]: https://github.com/phpspec/phpspec/compare/5.0.0...5.0.1
[5.0.0]: https://github.com/phpspec/phpspec/compare/4.3.1...5.0.0
