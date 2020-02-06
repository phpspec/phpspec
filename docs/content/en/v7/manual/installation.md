---
title: "Installation"
weight: 20
---

**phpspec** is a PHP library that you'll have in your project
development environment. Before you begin, ensure that you have the
correct PHP version installed.

| phpspec | PHP          |
| ------- | ------------ |
| 4.x     | ^7.0, \<7.3  |
| 5.x     | ^7.1, \<7.4  |
| 6.x     | ^7.2, \<7.5  |
| 7.x     | ^7.3 &#124;&#124; 8.0.*|

Installation process:
---------------------

You can install phpspec with all its dependencies through Composer.
Follow instructions on [the composer
website](https://getcomposer.org/download/) if you don't have it
installed yet.

N.b.: You will need to ensure that your Composer `autoload` settings are
correct. phpspec will not be able to detect classes, even ones it has
created, unless this is working. This is a common issue which causes
confusion when installing phpspec.

The `autoload` section of your `composer.json` file may look something
like this:

```json
"autoload": {
    "psr-0": {
        "": "src/"
    }
}
```

Method \#1 (Composer command):
------------------------------

You can use this Composer command to install phpspec:

```sh
composer require --dev phpspec/phpspec
```

Method \#2 (Composer config file):
----------------------------------

If you prefer editing your `composer.json` file manually, add phpspec to
your `require-dev` section like this:

```js
{
    "require-dev": {
        "phpspec/phpspec": "[your preferred version]"
    },
    "config": {
        "bin-dir": "bin"
    },
    "autoload": {
        "psr-0": {
            "": "src/"
        }
    }
}
```

Then install phpspec with the composer install command:

```sh
$ composer install
```

Result:
-------

phpspec with its dependencies will be installed inside the `vendor`
folder. The phpspec executable will be available at
`vendor/bin/phpspec`, or wherever you have specified in your
`composer.json` file's `bin-dir` setting. See the [composer
docs](https://getcomposer.org/doc/04-schema.md#bin) for more information
