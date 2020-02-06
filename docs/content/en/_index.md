---
title: "Quick Start"
menu:
  main:
    title: "main"
    weight: 10
---

Create a `composer.json` file:

```json
{
    "require-dev": {
        "phpspec/phpspec": "^6.1"
    },
    "autoload": {
        "psr-4": {
            "Your\\Namespace\\": "src"
        }
    },
    "scripts": {
        "test": "phpspec run"
    }
}
```

Follow the instructions on this page to install composer:
<https://getcomposer.org/download/>.

Install **phpspec** with composer:

```sh
composer require --dev phpspec/phpspec
```

Start writing specs:

```sh
vendor/bin/phpspec desc Acme/Calculator
```

Start running phpspec:

```sh
composer run test
```

Learn more from the documentation [documentation](/manual/introduction/).
