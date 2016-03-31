Contributing
============

PhpSpec is an open source, community-driven project. If you'd like to contribute,
feel free to do this, but remember to follow this few simple rules:

Branching strategy
-------------------

- For new features, or bugs that only affect the 3.x versions, base your changes on the `master` branch and open PRs against `master`
- For bugs that affect 2.5.x versions, base your changes on the `2.5` branch and open PRs agains `2.5`
- Bugs in previous versions are not going to be fixed, upgrade to `2.5` minimum.

Coverage
--------

- All classes that interact solely with the core logic should be covered by Specs
- Any infrastructure adaptors should be covered by integration tests using PHPUnit
- All features should be covered with .feature descriptions automated with Behat

Code style / Formatting
-----------------------

- All new classes must carry the standard copyright notice docblock
- All code in the `src` folder must follow the PSR-2 standard
