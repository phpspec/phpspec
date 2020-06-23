Contributing
============

PhpSpec is an open source, community-driven project. If you'd like to contribute,
feel free to do this, but remember to follow this few simple rules:

Branching strategy
-------------------

At any given point there are three active branches:

* Bug fixes that apply to old versions should target the current bugfix branch, which will be named after the last minor 
version supported (e.g. `5.3`, `6.2`)
* New features, refactoring and general cleanup should target the `main` branch and maintain backward compatibility
* Any changes or refactoring that would introduce a backward incompatibility should target the `next` branch

Coverage
--------

- All classes that interact solely with the core logic should be covered by Specs
- Any infrastructure adaptors should be covered by integration tests using PHPUnit
- All features should be covered with .feature descriptions automated with Behat

Code style / Formatting
-----------------------

- All new classes must carry the standard copyright notice docblock
- All code in the `src` folder must follow the PSR-2 standard
