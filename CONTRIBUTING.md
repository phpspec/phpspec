Contributing
============

PhpSpec is an open source, community-driven project. If you'd like to contribute,
feel free to do this, but remember to follow these few simple rules:

Branching strategy
-------------------

At any given point there are three active branches:

* Bug fixes that apply to old versions should target the current bugfix branch, which will be named after the last minor
version supported (e.g. `5.3`, `6.2`)
* New features, refactoring and general cleanup should target the `main` branch and maintain backward compatibility
* Any changes or refactoring that would introduce a backward incompatibility should target the `next` branch

Coverage
--------

- All classes should be covered by Specs (`.spec.php` files in the `spec/` directory)
- All user-facing features should be covered with `.feature` scenarios (in the `features/` directory)
- Run specs: `php bin/phpspec run`
- Run features: `php bin/phpspec run features/`

Code style / Formatting
-----------------------

- All code in the `src` folder must follow the PER-CS2.0 standard
- Run `vendor/bin/php-cs-fixer fix` to auto-fix code style
- Run `vendor/bin/phpstan analyse` for static analysis
