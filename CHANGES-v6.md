# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [6.1.1]
### Fixed
 - Fixed regression that caused classes to be instantiated in cases where they previously were not [@ciaranmcnulty](https://github.com/ciaranmcnulty)

## [6.1.0]
### Added
 - JSON formatter [@chris-kruining](https://github.com/chris-kruining)
 - Add file and line number to parse error output [@gquemener](https://github.com/gquemener)
 - Symfony 5 compatibility [@alexander-schranz](https://github.com/alexander-schranz)
 - PHP 7.4 compatibility [@ddziaduch](https://github.com/ddziaduch), [@ciaranmcnulty](https://github.com/ciaranmcnulty)

## [6.0.0]
### Changed
 - Bumped minimum PHP and Symfony dependences [@ciaranmcnulty](https://github.com/ciaranmcnulty)
 - AfterSpecification event now always fires in case of failure [@chris-kruining](https://github.com/chris-kruining)
 - Removed Prophecy\Argument use statement from templates [@DonCallisto](https://github.com/DonCallisto)

[6.1.1]: https://github.com/phpspec/phpspec/compare/6.1.0...6.1.1
[6.1.0]: https://github.com/phpspec/phpspec/compare/6.0.0...6.1.0
[6.0.0]: https://github.com/phpspec/phpspec/compare/5.1.2...6.0.0

