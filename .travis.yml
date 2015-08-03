language: php

sudo: false

cache:
  directories:
    - $HOME/.composer/cache

matrix:
  include:
    - php: 5.3
    - php: 5.3.3
      env: DEPENDENCIES='low'
    - php: 5.4
    - php: 5.5
    - php: 5.6
      env: DEPENDENCIES='dev'
    - php: 5.6
    - php: hhvm
    - php: 7.0
  allow_failures:
    - php: 7.0
    - env: DEPENDENCIES='dev'
  fast_finish: true

before_install:
  - composer selfupdate

install:
  - export COMPOSER_ROOT_VERSION=dev-master
  - if [ "$DEPENDENCIES" == "dev" ]; then perl -pi -e 's/^}$/,"minimum-stability":"dev"}/' composer.json; fi;
  - if [ "$DEPENDENCIES" != "low" ]; then composer update; fi;
  - if [ "$DEPENDENCIES" == "low" ]; then composer update --prefer-lowest; fi;

before_script:
  - echo "<?php if (PHP_VERSION_ID >= 50400) echo ',@php5.4';" > php_version_tags.php

script:
   - bin/phpspec run --format=pretty
   - ./vendor/bin/phpunit --testdox
   - ./vendor/bin/behat --format=pretty --tags '~@php-version'`php php_version_tags.php`
