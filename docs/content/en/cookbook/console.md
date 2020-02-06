---
title: "Running phpspec"
weight: 20
menu:
  cookbook:
    weight: 20
---

The phpspec console command uses Symfony's console component. This means
that it inherits the [default Symfony console command and
options](http://symfony.com/doc/current/components/console/usage.html).

**phpspec** has an additional global option to let you specify a config
file other than _phpspec.yml_, _.phpspec.yml_, or _phpspec.yml.dist_:

```sh
$ bin/phpspec run --config path/to/different-phpspec.yml
```

or:

```sh
$ bin/phpspec run -c path/to/different-phpspec.yml
```

Read more about this in the [Configuration Cookbook]({{< relref "/cookbook/configuration" >}}).

Also of note is that using the `--no-interaction` option means that no
code generation will be done.

**phpspec** has the global option to let you specify a custom bootstrap
or autoloading script.

```sh
$ bin/phpspec run --bootstrap=path/to/different-bootstrap.php
```

or:

```sh
$ bin/phpspec run -b path/to/different-bootstrap.php
```

Describe Command
----------------

The `describe` command creates a specification for a class:

```sh
$ bin/phpspec describe ClassName
```

Will generate a specification ClassNameSpec in the spec directory.

```sh
$ bin/phpspec describe Namespace/ClassName
```

Will generate a namespaced specification NamespaceClassNameSpec. Note
that `/` is used as the separator. To use `\` it must be quoted:

```sh
$ bin/phpspec describe "Namespace\ClassName"
```

The `describe` command has no additional options. It will create a spec
class in the _spec_ directory. To configure a different path to the specs
you can use [suites]({{< relref "/cookbook/configuration" >}})
in the configuration file.

Run Command
-----------

The `run` command runs the specs:

```sh
$ bin/phpspec run
```

Will run all the specs in the _spec_ directory.

```sh
$ bin/phpspec run spec/ClassNameSpec.php
```

Will run only the ClassNameSpec.

```sh
$ bin/phpspec run spec/ClassNameSpec.php:56
```

Will run only specification defined in the ClassNameSpec on line 56.

You can run just the specs in a directory with:

```sh
$ bin/phpspec run spec/Markdown
```

Which will run any specs found in _spec/Markdown_ and its subdirectories.
Note that it is the spec location and not namespaces that are used to
decide which specs to run. Any spec which has a namespace which does not
match its file path will be ignored.

By default, you will be asked whether missing methods and classes should
be generated. You can suppress these prompts and automatically choose
not to generate code with:

```sh
$ bin/phpspec run --no-code-generation
```

You can choose to stop on failure and avoid running the remaining specs
with:

```sh
$ bin/phpspec run --stop-on-failure
```

TDD work cycle can be described using three steps: Fail, Pass, Refactor.
If you create a failing spec for a new method, the next step will be to
make it pass. The easiest way to achieve it, is to simply hard code the
method, so it returns the expected value.

**phpspec** can do that for you.

You can opt to automatically fake return values with:

```sh
$ bin/phpspec run --fake
```

You can choose the output format with the `--format` option e.g.:

```sh
$ bin/phpspec run --format=dot
```

The formatters available by default are:

-   progress (default)
-   html
-   pretty
-   junit
-   dot

More formatters can be added by [extensions]({{< relref "/cookbook/extensions" >}}).
