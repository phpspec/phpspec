4.3.4 / 2019-08-05
==================

* [fixed] Revert accidental platform entry in composer.json (@ciaranmcnulty)

4.3.3 / 2019-08-05
==================

* [fixed] Avoid memory error in DotFormatter with large number of events (@lombartec)

4.3.2 / 2018-09-24
==================

* [fixed] Better error message when trying to call method on scalar return type (@ciaranmcnulty) 

4.3.1 / 2018-07-02
==================

* Typehint iteration matchers for IDEs (@l3l0)
* Extension point to help annotation extension (@drupol)

4.3.0 / 2017-12-22
==================

* Add support for .yaml file extension in config file (@unfunco)
* [fixed] src folder is created when does not exist and using PSR-4 (@unfunco)

4.2.5 / 2017-12-06
==================

* [fixed] Undefined exception when giving wrong args to Trigger matcher (@greg0ire)

4.2.4 / 2017-11-24
==================

* [fixed] Errors from incorrect type hint when collaborator method not found (@greg0ire)

4.2.3 / 2017-11-24
==================

* [fixed] Allow installation with Symfony 4 (@sroze, @gnugat)

4.2.2 / 2017-11-17
==================

* [fixed] Missing autocomplete for shouldIterateLike matchers (@pamil)
* [fixed] Regression where config files called .dist or . prefix were not picked up (@jakzal)

4.2.1 / 2017-11-10
==================

* [fixed] Properly handle empty config file (@ciaranmcnulty)
* [fixed] Non-existent folders broke composer detection (@greg0ire)

4.2.0 / 2017-10-28
==================

* Detect autoloader from composer to automatically define spec locations, reducing need for suites with PSR-4 (@greg0ire)
* Describe command without class now shows prompt with autocompleting input (@fullpipe)

4.1.0 / 2017-10-18
==================

* New `shouldIterateLike`/`shouldYieldLike` matcher (@sroze)
* Checks class name is not a reserved word when creating spec (@avant1)

4.0.4 / 2017-09-13
==================

* Allow installation on PHP 7.2 (@ciaranmcnulty)
* [performance] Improved speed when invoking native functions (@bendavies)

4.0.3 / 2017-08-26
==================

* [fixed] TypeError thrown when calling `ExampleEvent::getTime()` on event constructed w/ nullable `$time` (@oxkhar)
* [fixed] TypeError thrown when presenting diff in verbose mode (@avant1)

4.0.2 / 2017-08-05
==================

* [fixed] Illegible text when using a white terminal background (@MarcelloDuarte)

4.0.1 / 2017-07-04
==================

* [fixed] type error when handling errors (@nightlinus)

4.0.0 / 2017-07-29
==================

* Dropped support for PHP versions less than 7.0 (@ciaranmcnulty)
* Added scalar types and return types (@Sam-Burns, @ciaranmcnulty)
* [fixed] parameters after extensions ignored in config file (@borNfreee)
