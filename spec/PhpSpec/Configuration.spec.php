<?php

use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use PhpSpec\StopConditions;

describe(Configuration::class, function () {

    it('returns defaults when no config file exists', function (Filesystem $fs) {
        $config = new Configuration('/app', $fs);

        expect($config->getSpecPath())->toBe('./spec');
        expect($config->getSrcPath())->toBe('./src');
        expect($config->getFormat())->toBe('pretty');
        expect($config->getBootstrap())->toBeNull();
        expect($config->getStopOnFailure())->toBe(false);
        expect($config->getBaseUrl())->toBeNull();
    });

    it('loads from phpspec.json when it exists', function (Filesystem $fs) {
        $json = json_encode([
            'spec_path' => './tests',
            'format' => 'dot',
            'bootstrap' => 'vendor/autoload.php',
            'stop_on_failure' => true,
        ]);
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml' => false,
            '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read())->toReturn($json);

        $config = new Configuration('/app', $fs);

        expect($config->getSpecPath())->toBe('./tests');
        expect($config->getFormat())->toBe('dot');
        expect($config->getBootstrap())->toBe('vendor/autoload.php');
        expect($config->getStopOnFailure())->toBe(true);
    });

    it('reads arbitrary keys via get()', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml' => false,
            '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read())->toReturn(json_encode(['custom_key' => 'custom_value']));

        $config = new Configuration('/app', $fs);

        expect($config->get('custom_key'))->toBe('custom_value');
        expect($config->get('missing', 'fallback'))->toBe('fallback');
    });

    it('exposes config as array via toArray()', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml' => false,
            '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read())->toReturn(json_encode(['format' => 'tap']));

        $config = new Configuration('/app', $fs);

        expect($config->toArray())->toBe(['format' => 'tap']);
    });

    it('falls back to phpspec.php when json does not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml', '/app/phpspec.json' => false,
            '/app/phpspec.php' => true,
            default => false,
        });
        allow($fs->requirePhp())->toReturn(['format' => 'junit']);

        $config = new Configuration('/app', $fs);

        expect($config->getFormat())->toBe('junit');
    });

    it('loads from phpspec.yaml when it exists', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("spec_path: ./tests\nformat: dot\n");

        $config = new Configuration('/app', $fs);

        expect($config->getSpecPath())->toBe('./tests');
        expect($config->getFormat())->toBe('dot');
    });

    it('loads from phpspec.yml when yaml does not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => false,
            '/app/phpspec.yml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("format: tap\n");

        $config = new Configuration('/app', $fs);

        expect($config->getFormat())->toBe('tap');
    });

    it('prioritises yaml over yml over json', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml', '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "format: yaml_format\n",
            '/app/phpspec.yml' => "format: yml_format\n",
            '/app/phpspec.json' => json_encode(['format' => 'json_format']),
            default => '',
        });

        $config = new Configuration('/app', $fs);

        expect($config->getFormat())->toBe('yaml_format');
    });

    it('synthesises a default suite when no suites key exists', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("spec_path: ./tests\nsrc_path: ./lib\n");

        $config = new Configuration('/app', $fs);

        expect($config->getSuites())->toBe([
            'default' => ['paths' => ['./tests'], 'src' => './lib'],
        ]);
    });

    it('returns named suites from the suites key', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn(
            "suites:\n  unit:\n    paths:\n      - unit_specs\n    src: src\n  acceptance:\n    paths:\n      - features\n    steps:\n      - features/steps\n",
        );

        $config = new Configuration('/app', $fs);
        $suites = $config->getSuites();

        expect($suites)->toHaveKey('unit');
        expect($suites)->toHaveKey('acceptance');
        expect($suites['unit']['paths'])->toBe(['unit_specs']);
        expect($suites['acceptance']['paths'])->toBe(['features']);
    });

    it('returns all load paths combined from suites', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn(
            "suites:\n  unit:\n    paths:\n      - unit_specs\n  integration:\n    paths:\n      - integration_specs\n",
        );

        $config = new Configuration('/app', $fs);

        expect($config->getAllLoadPaths())->toBe('unit_specs,integration_specs');
    });

    it('returns stop_on_error from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("stop_on_error: true\n");

        $config = new Configuration('/app', $fs);

        expect($config->getStopOnError())->toBe(true);
    });

    it('returns StopConditions value object from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("stop_on_failure: true\nstop_on_warning: true\n");

        $config = new Configuration('/app', $fs);
        $stop = $config->getStopConditions();

        expect($stop)->toBeAnInstanceOf(StopConditions::class);
        expect($stop->onFailure)->toBe(true);
        expect($stop->onError)->toBe(false);
        expect($stop->onWarning)->toBe(true);
        expect($stop->onDeprecation)->toBe(false);
    });

    it('returns spec_suffix from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("spec_suffix: Spec.php\n");

        $config = new Configuration('/app', $fs);

        expect($config->getSpecSuffix())->toBe('Spec.php');
    });

    it('returns default spec_suffix when not configured', function (Filesystem $fs) {
        $config = new Configuration('/app', $fs);

        expect($config->getSpecSuffix())->toBe('.spec.php');
    });

    it('returns extensions config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("extensions:\n  formatters:\n    - MyFormatter\n");

        $config = new Configuration('/app', $fs);

        expect($config->getExtensions())->toBe(['formatters' => ['MyFormatter']]);
    });

    it('returns ai config when configured with api_key', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("ai:\n  provider: google\n  model: gemini-2.5-flash\n  api_key: test-key-123\n");

        $config = new Configuration('/app', $fs);

        expect($config->getAiConfig())->toBe([
            'provider' => 'google',
            'model' => 'gemini-2.5-flash',
            'api_key' => 'test-key-123',
        ]);
    });

    it('exposes a configured max_tokens as an int', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("ai:\n  provider: google\n  api_key: test-key-123\n  max_tokens: 32000\n");

        $config = new Configuration('/app', $fs);

        expect($config->getAiConfig())->toBe([
            'provider' => 'google',
            'maxTokens' => 32000,
            'api_key' => 'test-key-123',
        ]);
    });

    it('returns null for ai config when not configured', function (Filesystem $fs) {
        $config = new Configuration('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
    });

    it('returns null for ai config when api_key is missing', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("ai:\n  provider: google\n");

        $config = new Configuration('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
    });

    it('loads config from phpspec.php when no yaml or json exists', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.php' => true,
            default => false,
        });
        allow($fs->requirePhp())->toReturn(['spec_path' => 'tests', 'src_path' => 'lib']);

        $config = new Configuration('/app', $fs);

        expect($config->getSpecPath())->toBe('tests');
        expect($config->getSrcPath())->toBe('lib');
    });

    it('returns default features path when not configured', function (Filesystem $fs) {
        $config = new Configuration('/app', $fs);

        expect($config->getFeaturesPath())->toBe('features/');
    });

    it('returns configured features_path', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("features_path: tests/features\n");

        $config = new Configuration('/app', $fs);

        expect($config->getFeaturesPath())->toBe('tests/features');
    });

    it('returns src_path from first suite when flat key absent', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn(
            "suites:\n  unit:\n    paths:\n      - unit_specs\n    src: lib\n",
        );

        $config = new Configuration('/app', $fs);

        expect($config->getSrcPath())->toBe('lib');
    });

    it('returns psr4_prefix from top-level config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn('psr4_prefix: App\\Models\\');
        $config = new Configuration('/app', $fs);
        expect($config->getPsr4Prefix())->toBe('App\\Models');
    });

    it('returns namespace from suite config as psr4_prefix', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read())->toReturn("suites:\n  default:\n    namespace: App\n    src: src");
        $config = new Configuration('/app', $fs);
        expect($config->getPsr4Prefix())->toBe('App');
    });

    it('loads an explicit config file instead of the working directory cascade', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === 'custom/my-config.json');
        allow($fs->read())->toReturn(json_encode(['format' => 'dot']));

        $config = new Configuration('/app', $fs, configFile: 'custom/my-config.json');

        expect($config->getFormat())->toBe('dot');
    });

    it('throws when the explicit config file does not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        expect(fn() => new Configuration('/app', $fs, configFile: 'nope.yaml'))
            ->toThrow(RuntimeException::class);
    });

    it('resolves the config path from argv tokens', function () {
        expect(Configuration::configPathFromArgv(['phpspec', 'run', '--config=a.yaml']))->toBe('a.yaml');
        expect(Configuration::configPathFromArgv(['phpspec', 'run', '--config', 'b.json']))->toBe('b.json');
        expect(Configuration::configPathFromArgv(['phpspec', 'run', '-c', 'c.yml']))->toBe('c.yml');
        expect(Configuration::configPathFromArgv(['phpspec', 'run']))->toBeNull();
    });

    it('has no steps path by default', function (Filesystem $fs) {
        $config = new Configuration('/app', $fs);
        expect($config->getStepsPath())->toBeNull();
    });

    it('returns the configured steps path', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === '/app/phpspec.json');
        allow($fs->read())->toReturn(json_encode(['steps_path' => 'acceptance_steps']));

        $config = new Configuration('/app', $fs);

        expect($config->getStepsPath())->toBe('acceptance_steps');
    });

});
