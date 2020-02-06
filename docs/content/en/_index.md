---
title: "Quick Start"
menu:
  main:
    title: "main"
    weight: 10
---

Install **phpspec** with Composer:

```sh
composer require --dev phpspec/phpspec
```

Follow the instructions on this page to install Composer: <https://getcomposer.org/download/>.

Start writing specs:

```sh
vendor/bin/phpspec desc Acme/Calculator
```

Run phpspec:

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
        "phpspec": "phpspec run"
    }
}
```

```sh
composer run phpspec
```

Learn more from the [documentation]({{< relref "/manual/introduction" >}}).
