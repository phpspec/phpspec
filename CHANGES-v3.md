3.4.3 / 2017-12-06
==================

* [fixed] Undefined exception when giving wrong args to Trigger matcher (@greg0ire)

3.4.2 / 2017-08-05
==================

* [fixed] Illegible text when using a white terminal background (@MarcelloDuarte)

3.4.1 / 2017-07-29
==================

* [fixed] parameters after extensions ignored in config file (@borNfreee)

3.4.0 / 2017-05-12
==================

* [fixed] constructor no longer generated multuple tiles (@CarlosV2)
* [fixed] warning when src_path is empty (@vitorf7)
* Support methods with reserved names on PHP 7 (@avant1)

3.3.0 / 2017-04-27
==================

* Support sebastian/exporter 3.0 (@remicollet)
* Support `.phpspec.yml` as a filename (@shrikeh)

3.2.3 / 2016-01-29
==================

* IDE support for shouldYield/shouldStartYielding (@pamil)

3.2.2 / 2016-12-04
==================

* Support sebastian/exporter 2.0 providing PHPUnit 5.7 compatibility (@mattsches)

3.2.1 / 2016-12-02
==================

* [fixed] Prevent deprecation warning in Symfony 3.2.0 (@veewee)

3.2.0 / 2016-11-27
==================

* New `shouldTrigger` matcher for specifying a warning is triggered (@Taluu)
* New `shouldIterateAs` matcher for specifying how a class is iterated (@pamil)
* New `shouldBeApproximately` matcher for comparing floats (@brainrepo)
* [fixed] No longer suggests an outdated version of Nyan formatters (@unfunco)
* [performance] Reduced size of Phar (@unfunco)

3.1.1 / 2016-09-26
==================

* [fixed] Accidental linebreaks in spec name are not allowed (@randompixel)
* [fixed] Throwable can be passed as instance to shouldThrow (@jameshalsall)
* [performance] Phar version now has an optimised autoloader

3.1.0 / 2017-09-17
======================

* Many errors are now caught and handled without ending suite execution (@ciaranmcnulty)
* Validates that matchers specified in config are valid matchers (@avant1)
* Shows Error message even when Exception was expected (@harrisonbro)
* Disallows doubling of PHP 7.1's `iterable` type (@avant1)
* [fixed] Exceptions are properly highlighted in error messages (@ciaranmcnulty)

3.0.0 / 2016-07-16
==================

* Default template now uses `::class` (@ciaranmcnulty)
* No longer declare variables/constants in global scope (@ciaranmcnulty)
* Ability to register matchers quickly via the config file (@gquemener)
* [fixed] Describing a class providing a namespace with leading backslash (@mheki)
* [fixed] Bug where rerun test suite was uncoloured (@ciaranmcnulty)
* [fixed] Bug in DotFormatter when number of rows is multiple of column width (@bendavies)
* [BC break] Removed support for @param for creating doubles (@Sam-Burns)
* [BC break] Bumped dependency versions (see migration guide) (@ciaranmcnulty)
* [BC break] Removed various code branches for support of older dependencies (@ciaranmcnulty)
* [BC break] Made classes final or abstract in simple cases (@ciaranmcnulty)
* [BC break] Removed `*Interface` from all interfaces (@shanethehat)
* [BC break] Removed deprecated code / optional interfaces (@mheki)
* [BC break] Changed extension config format so parameters are scoped to extensions (@docteurklein)
* [BC break] New Extension and ServiceContainer interfaces (@ciaranmcnulty)
