---
title: "Configuration"
weight: 10
menu:
  cookbook:
    weight: 10
---

Some things in phpspec can be configured in a `phpspec.yml`,
`.phpspec.yml`, or `phpspec.yml.dist` file in the root of your project
(the directory where you run the `phpspec` command).

You can use a different config file name and path with the `--config`
option:

```sh
$ bin/phpspec run --config path/to/different-phpspec.yml
```

You can use the `.yaml` extension in place of `.yml` if preferred.

You can also specify default values for config variables across all
repositories by creating the file `.phpspec.yml` in your home folder
(Unix systems). phpspec will use your personal preference for all
settings that are not defined in the project's configuration.

PSR-4
-----

**phpspec** can try to autodetect your naming scheme by querying
Composer for autoload rules you can define in the Composer manifest. If
unsuccessful, it assumes a PSR-0 mapping of namespaces to the src and
spec directories by default. So for example running:

```sh
$ bin/phpspec describe Acme/Text/Markdown
```

Will create a spec in the `spec/Acme/Text/MarkdownSpec.php` file and the
class will be created in `src/Acme/Text/Markdown.php`

To use PSR-4 you configure the `namespace` and `psr4_prefix` options in
a suite to the part that should be omitted from the directory structure:

```yaml
suites:
    acme_suite:
        namespace: Acme\Text
        psr4_prefix: Acme\Text
```

With this config running:

```sh
$ bin/phpspec describe Acme/Text/Markdown
```

will now put the spec in `spec/MarkdownSpec.php` and the class will be
created in `src/Markdown.php`.

Alternatively, you can choose to use Composer to provide the necessary
configuration:

```yaml
composer_suite_detection: true # translates to:
                               # - root_directory: '.'
                               # - spec_prefix: spec
```

Spec and source locations
-------------------------

The default locations used by **phpspec** for the spec files and source
files are spec and src respectively. You may find that this does not
always suit your needs. You can specify an alternative location in the
configuration file. You cannot do this at the command line as it does
not make sense for a spec or source files path to change at runtime.

You can specify alternative values depending on the namespace of the
class you are describing. In phpspec, you can group specification files
by a certain namespace in a *suite*. For each suite, you have several
configuration settings:

-   `namespace` - The namespace of the classes. Used for generating spec
    files, locating them and generating code;
-   `spec_prefix` \[**default**: `spec`\] - The namespace prefix for
    specifications. The complete namespace for specifications is
    `%spec_prefix%\%namespace%`;
-   `src_path` \[**default**: `src`\] - The path to store the generated
    classes. By default paths are relative to the location where
    **phpspec** was invoked. **phpspec** creates the directories if they
    do not exist. This does not include the namespace directories;
-   `spec_path` \[**default**: `.`\] - The path of the specifications.
    This does not include the spec prefix or namespace.
-   `psr4_prefix` \[**default**: `null`\] - A PSR-4 prefix to use.

Some examples:

```yaml
suites:
    acme_suite:
        namespace: Acme\Text
        spec_prefix: acme_spec

    # shortcut for
    # my_suite:
    #     namespace: The\Namespace
    my_suite: The\Namespace
```

> You may use `%paths.config%` in `src_path` and `spec_path` making paths
> relative to the location of the config file.

Some examples:

```yaml
suites:
    acme_suite:
        namespace: Acme\Text
        spec_prefix: acme_spec
        src_path: '%paths.config%/src'
        spec_path: '%paths.config%'
```

**phpspec** will use suite settings based on the namespaces. If you have
suites with different spec directories then `phpspec run` will run the
specs from each of the directories using the relevant suite settings.

When you use `phpspec desc` **phpspec** creates the spec using the
matching configuration. E.g. `phpspec desc Acme/Text/MyClass` will use
the the namespace `acme_spec\Acme\Text\MyClass`.

If the namespace does not match one of the namespaces in the suites
config then **phpspec** uses the default settings. If you want to change
the defaults then you can add a suite without specifying the namespace.

```yaml
suites:
    #...
    default:
        spec_prefix: acme_spec
        spec_path: acmes-specs
        src_path: acme-src
```

You can just set this suite if you wanted to override the default
settings for all namespaces. Since **phpspec** matches on namespaces you
cannot specify more than one set of configuration values for a null
namespace. If you do add more than one suite with a null namespace then
**phpspec** will use the last one defined.

Note that the default spec directory is `.`, specs are created in the
spec directory because it is the first part of the spec namespace. This
means that changing the spec\_path will result in additional directories
before spec not instead of it. For example, with the config:

```yaml
suites:
    acme_suite:
        namespace: Acme\Text
        spec_prefix: acme_spec
```

running:

```sh
$ bin/phpspec describe Acme/Text/Markdown
```

will create the spec in the file
`acme_spec/spec/Acme/Text/MarkdownSpec.php`

Formatter
---------

You can also set another default formatter instead of `progress`. The
`--format` option of the command can override this setting. To set the
formatter, use `formatter.name`:

```yaml
formatter.name: pretty
```

The formatters available by default are:

-   progress (default)
-   html/h
-   pretty
-   junit
-   dot
-   tap

More formatters can be added by [extensions]({{< relref "/cookbook/extensions" >}}).

Options
-------

You can turn off code generation in your config file by setting
`code_generation`:

```yaml
code_generation: false
```

You can also set your tests to stop on failure by setting
`stop_on_failure`:

```yaml
stop_on_failure: true
```

Moreover you can turn on verbosity by setting `verbose`:

```yaml
verbose: true
```

As PHPSpec does not have a distinction between canonicals verbosity
levels (`-v`, `-vv`, `-vvv`) you cannot specify it through configuration
file. As a matter of fact running PHPSpec with any of these flags as
unix command option, will result in the same output. If you run the
command with `-q` or `--quite` the `verbose` options will be overridden.

Extensions
----------

To register phpspec extensions, use the `extensions` option. This is an
array of extension classes:

```yaml
extensions:
    - PhpSpec\Symfony2Extension\Extension
```

Custom matchers
---------------

You may want to make custom matchers available in all specs. Custom
matchers can be registered by extension, but there is a simplier way:
use the `matchers` setting and provide an array of matcher classes. Each
of them must implement `PhpSpec\Matcher\Matcher` interface:

```yaml
matchers:
    - Acme\Matchers\ValidJsonMatcher
    - Acme\Matchers\PositiveIntegerMatcher
```

Bootstrapping
-------------

There are times when you would be required to load classes and execute
additional statements that the Composer-generated autoloader may not
provide, which is likely for a legacy project that wants to introduce
phpspec for designing new classes that may rely on some legacy
collaborators.

To load a custom bootstrap when running phpspec, use the `bootstrap`
setting:

```yaml
bootstrap: path/to/different-bootstrap.php
```

This setting should be in the root of the config file (i.e. not nested
under `suites` or anything else).
