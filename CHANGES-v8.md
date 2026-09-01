# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Fixed
- Nullable parameter typehints (`?Foo`) in spec classes no longer produce a `ParseError` when the spec is loaded [#1583](https://github.com/phpspec/phpspec/issues/1583)

## [8.3.1]
### Fixed
- Bump `phpspec/prophecy` constraint to `^1.26.1` to fix compatibility issues that were breaking CI and had prevented the PHAR from being published with the 8.3.0 release

## [8.3.0]
### Added
- Support `sebastian/exporter` 8.x to be compatible with PHPUnit 13 [@Jean85](https://github.com/Jean85)

## [8.2.0](https://github.com/phpspec/phpspec/compare/8.1.0...8.2.0)
### Added
- PHP 8.5 compatibility

## [8.1.0](https://github.com/phpspec/phpspec/compare/8.0.0...8.1.0)
### Added
- Symfony 8 compatibility

## [8.0.0](https://github.com/phpspec/phpspec/compare/7.6.0...8.0.0)
### Added
- PHP 8.4 compatibility [@jrfnl](https://github.com/jrfnl) [@andypost](https://github.com/andypost)

[8.3.1]: https://github.com/phpspec/phpspec/compare/8.3.0...8.3.1
[8.3.0]: https://github.com/phpspec/phpspec/compare/8.2.0...8.3.0
