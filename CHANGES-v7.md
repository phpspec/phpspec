# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

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
 
[7.0.1]: https://github.com/phpspec/phpspec/compare/7.0.0...7.0.1
[7.0.0]: https://github.com/phpspec/phpspec/compare/6.2.2...7.0.0
