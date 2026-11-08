<?php

use PhpSpec\Configuration;
use PhpSpec\ConfigurationException;
use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Filesystem;
use PhpSpec\Source\ComposerAutoload;
use PhpSpec\StopConditions;

describe(Configuration::class, function () {

    context('what a project states is checked as it is read', function () {
        $yaml = function (Filesystem $fs, string $content): void {
            allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/phpspec.yaml');
            allow($fs->read(any()))->toReturn($content);
        };

        it('refuses an unknown key, naming the file and the nearest known key', function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "formatt: dot\n");

            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: unknown key "formatt". Did you mean "format"?',
            );
        });

        it("takes a key none of PhpSpec's own is near as the project's, read through get()", function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "zebra: true\n");

            expect(Configuration::load('/app', $fs)->get('zebra'))->toBe(true);
        });

        it('refuses a value of the wrong type, naming what the key expects', function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "stop_on_failure: maybe\n");

            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: stop_on_failure expects true or false, "maybe" given.',
            );
        });

        it('refuses a coverage adapter it does not have, naming the two it has', function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "coverage_adapter: pcv\n");

            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: coverage_adapter expects pcov or xdebug, "pcv" given.',
            );
        });

        it('refuses coverage exclusions that are not a list of paths and names', function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "coverage_exclude: src/\n");

            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: coverage_exclude expects a list of paths and names, "src/" given.',
            );
        });

        it('names the file that could not be parsed', function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "formatt: [\n");

            try {
                Configuration::load('/app', $fs);
                expect(false)->toBeTrue();
            } catch (ConfigurationException $e) {
                expect($e->getMessage())->toStartWith('/app/phpspec.yaml could not be read: ');
            }
        });

        it('refuses psr4_prefix, and a suite\'s namespace, saying what maps namespaces instead', function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "psr4_prefix: App\\\n");
            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: psr4_prefix is not a setting: composer.json maps namespaces to directories, or autoload does in a project without one.',
            );

            $yaml($fs, "suites:\n  main:\n    paths: [spec]\n    namespace: App\n");
            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: suites.main.namespace is not a setting: composer.json maps namespaces to directories, or autoload does in a project without one.',
            );
        });

        it('refuses a file that does not hold a map of settings', function (Filesystem $fs) use ($yaml) {
            $yaml($fs, "just a string\n");

            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml does not hold a map of settings.',
            );
        });
    });

    it('is built from values and touches no disk', function () {
        $config = new Configuration(['spec_path' => 'tests', 'autoload' => ['Brew\\' => 'lib/Brew']], '/app');

        expect($config->getSpecPath())->toBe('tests');
        expect($config->getSourceLayout()->mappings())->toBe(['Brew' => 'lib/Brew']);
        expect((new Configuration())->getSourceLayout()->mappings())->toBe([]);
        expect((new Configuration([], '/app', new ComposerAutoload(['App\\' => ['src/']])))->getSourceLayout()->mappings())->toBe(['App' => 'src']);
    });

    it('keeps the directories of an autoload map as written, one above the project root included', function () {
        $layout = (new Configuration(['autoload' => ['App\\' => '../shared/src/', 'Tools\\' => './tools']], '/app'))->getSourceLayout();

        expect($layout->mappings())->toBe(['App' => '../shared/src', 'Tools' => 'tools']);
    });

    context('a project with a composer.json leaves the autoloading to it', function () {
        $project = function (Filesystem $fs, string $settings, array $composer): void {
            allow($fs->exists(any()))->toReturnUsing(fn(string $path) => in_array($path, ['/app/phpspec.yaml', '/app/composer.json'], true));
            allow($fs->read(any()))->toReturnUsing(fn(string $path) => $path === '/app/phpspec.yaml' ? $settings : json_encode($composer));
        };

        it('lays classes out by its mappings alone, the source path stated beside them, or a suite\'s, included', function (Filesystem $fs) use ($project) {
            foreach (["src_path: src\ndefault_namespace: Roastery\n", "suites:\n  main:\n    paths: [spec]\n    src: ./src/\n"] as $settings) {
                $project($fs, $settings, ['autoload' => ['psr-4' => ['Roastery\\' => 'src/']]]);

                $layout = Configuration::load('/app', $fs)->getSourceLayout();

                expect($layout->mappings())->toBe(['Roastery' => 'src']);
                expect($layout->relativeFileFor('Roastery\\Repository\\InMemoryRoastRepository'))->toBe(str_replace('/', DIRECTORY_SEPARATOR, 'src/Repository/InMemoryRoastRepository.php'));
            }
        });

        it('refuses an autoload map', function (Filesystem $fs) use ($project) {
            $project($fs, "autoload:\n  Tools\\: tools\n", ['require' => ['php' => '^8.3']]);

            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: autoload cannot be set in a project with a composer.json.',
            );
        });

        it('refuses a source path, or a suite\'s, it maps no namespace to, naming the directories it maps', function (Filesystem $fs) use ($project) {
            $composer = ['autoload' => ['psr-4' => ['Roastery\\' => 'src/', 'Tools\\' => ['tools/', 'lib/tools/']]]];

            $project($fs, "src_path: lib\n", $composer);
            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: src_path "lib" is not a directory composer.json maps a namespace to: src, tools, lib/tools.',
            );

            $project($fs, "suites:\n  main:\n    paths: [spec]\n    src: lib\n", $composer);
            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: suites.main.src "lib" is not a directory composer.json maps a namespace to: src, tools, lib/tools.',
            );
        });

        it('refuses a default namespace it places nowhere while another namespace claims the source path', function (Filesystem $fs) use ($project) {
            $project($fs, "default_namespace: Roaster\n", ['autoload' => ['psr-4' => ['Roastery\\' => 'src/']]]);

            expect(fn() => Configuration::load('/app', $fs))->toThrow(
                ConfigurationException::class,
                '/app/phpspec.yaml: default_namespace "Roaster" is under no namespace composer.json maps, and src/ cannot take its classes: Composer maps src/ to Roastery\\.',
            );
        });

        it('takes a default namespace it places, or one the source path is free to take', function (Filesystem $fs) use ($project) {
            foreach ([
                ["default_namespace: Roastery\\Beans\n", ['Roastery\\' => 'src/']],
                ["default_namespace: Roaster\n", ['Roastery\\' => 'src/Roastery/']],
                ["default_namespace: Roaster\n", ['Roastery\\' => 'src/', '' => 'lib/']],
            ] as [$settings, $psr4]) {
                $project($fs, $settings, ['autoload' => ['psr-4' => $psr4]]);

                expect(Configuration::load('/app', $fs)->getSourceLayout()->defaultNamespace())->not()->toBeNull();
            }
        });

        it('takes any source path while it maps no namespace', function (Filesystem $fs) use ($project) {
            $project($fs, "src_path: lib\n", ['require' => ['php' => '^8.3']]);

            expect(Configuration::load('/app', $fs)->getSrcPath())->toBe('lib');
        });
    });

    it('returns defaults when no config file exists', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        $config = Configuration::load('/app', $fs);

        expect($config->getSpecPath())->toBe('./spec');
        expect($config->getSrcPath())->toBe('./src');
        expect($config->getFormat())->toBe('pretty');
        expect($config->getBootstrap())->toBeNull();
        expect($config->getStopOnFailure())->toBe(false);
        expect($config->getBaseUrl())->toBeNull();
    });

    it('takes src as the source path when nothing configures it, and walks every directory composer.json maps besides', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/composer.json');
        allow($fs->read(any()))->toReturn(json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/', 'Database\\Factories\\' => 'database/factories/']]]));

        $config = Configuration::load('/app', $fs);

        expect($config->getSrcPath())->toBe('./src');
        expect($config->sourceRoots())->toBe(['src', 'app', 'database/factories']);
    });

    it('lays every composer mapping out, not only the first', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/composer.json');
        allow($fs->read(any()))->toReturn(json_encode(['autoload' => ['psr-4' => ['Brew\\' => 'src/Brew/', 'Another\\' => ['src/Another/', 'lib/']]]]));

        $layout = (Configuration::load('/app', $fs))->getSourceLayout();

        expect($layout->mappings())->toBe(['Brew' => 'src/Brew', 'Another' => 'src/Another']);
        expect($layout->srcPath())->toBe('src');
        expect($layout->defaultNamespace())->toBeNull();
    });

    it('lays out by the autoload map and the default namespace the config states', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/phpspec.yaml');
        allow($fs->read(any()))->toReturn("default_namespace: Brew\\Acme\nautoload:\n  Brew\\Acme\\: src/Brew/Acme\n  Another\\Acme\\: src/Another\n");

        $layout = (Configuration::load('/app', $fs))->getSourceLayout();

        expect($layout->mappings())->toBe(['Brew\\Acme' => 'src/Brew/Acme', 'Another\\Acme' => 'src/Another']);
        expect($layout->defaultNamespace())->toBe('Brew\\Acme');
        expect($layout->withinDefaultNamespace('Thing'))->toBe('Brew\\Acme\\Thing');
    });

    it('takes the first directory of a composer mapping that lists several', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/composer.json');
        allow($fs->read(any()))->toReturn(json_encode(['autoload' => ['psr-4' => ['App\\' => ['src/', 'lib/']]]]));

        expect(Configuration::load('/app', $fs)->getSourceLayout()->mappings())->toBe(['App' => 'src']);
    });

    // A real directory for the autoloader, which reads the disk; composer.json
    // stays on the fake filesystem the configuration reads.
    $projectWith = function (Filesystem $fs, string $mapping, string $classFile, string $code): string {
        $root = sys_get_temp_dir() . '/phpspec_autoload_' . uniqid();
        mkdir(dirname($root . '/' . $classFile), 0777, true);
        file_put_contents($root . '/' . $classFile, $code);
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === $root . '/composer.json');
        allow($fs->read(any()))->toReturn(json_encode(['autoload' => ['psr-4' => ['Tasker\\' => $mapping]]]));

        return $root;
    };

    // The autoloaders the configuration registers serve the check alone:
    // neither they nor the project outlive it.
    $autoloadingFrom = function (string $root, Configuration $config, Closure $check): void {
        $registered = spl_autoload_functions();
        $config->registerAutoloaders();

        try {
            $check();
        } finally {
            foreach (spl_autoload_functions() as $autoloader) {
                if (!in_array($autoloader, $registered, true)) {
                    spl_autoload_unregister($autoloader);
                }
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }

            rmdir($root);
        }
    };

    it('loads a class from the path the layout maps its namespace to', function (Filesystem $fs) use ($projectWith, $autoloadingFrom) {
        $root = $projectWith($fs, 'src/Tasker/', 'src/Tasker/MappedByPrefix.php', "<?php\nnamespace Tasker;\nclass MappedByPrefix {}\n");

        $autoloadingFrom($root, Configuration::load($root, $fs), fn() => expect(class_exists('Tasker\\MappedByPrefix'))->toBeTrue());
    });

    it('loads a class under a mapping from nowhere else, as Composer would not', function (Filesystem $fs) use ($projectWith, $autoloadingFrom) {
        $root = $projectWith($fs, 'src/', 'src/Tasker/FiledInFull.php', "<?php\nnamespace Tasker;\nclass FiledInFull {}\n");

        $autoloadingFrom($root, Configuration::load($root, $fs), fn() => expect(class_exists('Tasker\\FiledInFull'))->toBeFalse());
    });

    it('loads from phpspec.json when it exists', function (Filesystem $fs) {
        $json = json_encode([
            'spec_path' => './tests',
            'format' => 'dot',
            'bootstrap' => 'vendor/autoload.php',
            'stop_on_failure' => true,
        ]);
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml' => false,
            '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn($json);

        $config = Configuration::load('/app', $fs);

        expect($config->getSpecPath())->toBe('./tests');
        expect($config->getFormat())->toBe('dot');
        expect($config->getBootstrap())->toBe('vendor/autoload.php');
        expect($config->getStopOnFailure())->toBe(true);
    });

    it('reads arbitrary keys via get()', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml' => false,
            '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn(json_encode(['custom_key' => 'custom_value']));

        $config = Configuration::load('/app', $fs);

        expect($config->get('custom_key'))->toBe('custom_value');
        expect($config->get('missing', 'fallback'))->toBe('fallback');
    });

    it('exposes config as array via toArray()', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml' => false,
            '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn(json_encode(['format' => 'tap']));

        $config = Configuration::load('/app', $fs);

        expect($config->toArray())->toBe(['format' => 'tap']);
    });

    it('falls back to phpspec.php when json does not exist', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml', '/app/phpspec.json' => false,
            '/app/phpspec.php' => true,
            default => false,
        });
        allow($fs->requirePhp(any()))->toReturn(['format' => 'junit']);

        $config = Configuration::load('/app', $fs);

        expect($config->getFormat())->toBe('junit');
    });

    it('loads from phpspec.yaml when it exists', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("spec_path: ./tests\nformat: dot\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getSpecPath())->toBe('./tests');
        expect($config->getFormat())->toBe('dot');
    });

    it('loads from phpspec.yml when yaml does not exist', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => false,
            '/app/phpspec.yml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("format: tap\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getFormat())->toBe('tap');
    });

    it('prioritises yaml over yml over json', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml', '/app/phpspec.yml', '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "format: yaml_format\n",
            '/app/phpspec.yml' => "format: yml_format\n",
            '/app/phpspec.json' => json_encode(['format' => 'json_format']),
            default => '',
        });

        $config = Configuration::load('/app', $fs);

        expect($config->getFormat())->toBe('yaml_format');
    });

    it('synthesises a default suite when no suites key exists', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("spec_path: ./tests\nsrc_path: ./lib\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getSuites())->toBe([
            'default' => ['paths' => ['./tests'], 'src' => './lib'],
        ]);
    });

    it('returns named suites from the suites key', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn(
            "suites:\n  unit:\n    paths:\n      - unit_specs\n    src: src\n  acceptance:\n    paths:\n      - features\n    steps:\n      - features/steps\n",
        );

        $config = Configuration::load('/app', $fs);
        $suites = $config->getSuites();

        expect($suites)->toHaveKey('unit');
        expect($suites)->toHaveKey('acceptance');
        expect($suites['unit']['paths'])->toBe(['unit_specs']);
        expect($suites['acceptance']['paths'])->toBe(['features']);
    });

    it('returns all load paths combined from suites', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn(
            "suites:\n  unit:\n    paths:\n      - unit_specs\n  integration:\n    paths:\n      - integration_specs\n",
        );

        $config = Configuration::load('/app', $fs);

        expect($config->getAllLoadPaths())->toBe('unit_specs,integration_specs');
    });

    it('returns stop_on_error from config', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("stop_on_error: true\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getStopOnError())->toBe(true);
    });

    it('reads stop_on_pending into the stop conditions', function () {
        $config = new Configuration(['stop_on_pending' => true]);

        expect($config->getStopConditions()->onPending)->toBeTrue();
        expect((new Configuration())->getStopConditions()->onPending)->toBeFalse();
    });

    it('returns StopConditions value object from config', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("stop_on_failure: true\nstop_on_warning: true\n");

        $config = Configuration::load('/app', $fs);
        $stop = $config->getStopConditions();

        expect($stop)->toBeAnInstanceOf(StopConditions::class);
        expect($stop->onFailure)->toBe(true);
        expect($stop->onError)->toBe(false);
        expect($stop->onWarning)->toBe(true);
        expect($stop->onDeprecation)->toBe(false);
    });

    it('returns spec_suffix from config', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("spec_suffix: Spec.php\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getSpecSuffix())->toBe('Spec.php');
    });

    it('returns default spec_suffix when not configured', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        $config = Configuration::load('/app', $fs);

        expect($config->getSpecSuffix())->toBe('.spec.php');
    });

    it('returns extensions config', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("extensions:\n  formatters:\n    - MyFormatter\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getExtensions())->toBe(['formatters' => ['MyFormatter']]);
    });

    // Guard is off until a project says otherwise, and every value it needs has
    // a default, so `guard: {status: active}` is a complete configuration.
    it('leaves guard off when nothing says otherwise', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        $config = Configuration::load('/app', $fs);

        expect($config->getGuardConfig())->toBe([
            'status' => 'off',
            'scope' => 'spec',
            'detection' => 'git',
            'standards' => 'phpspec',
            'paths' => ['src'],
            'allow' => [],
        ]);
        expect($config->guardConfigProblem())->toBeNull();
    });

    it('takes the guard settings a project states and defaults the rest', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("guard:\n  status: active\n  scope: story\n  allow: [\"src/Migrations/**\"]\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getGuardConfig())->toBe([
            'status' => 'active',
            'scope' => 'story',
            'detection' => 'git',
            'standards' => 'phpspec',
            'paths' => ['src'],
            'allow' => ['src/Migrations/**'],
        ]);
    });

    it('gives the source and spec directories as code joins them: no leading ./, no trailing slash, a parent kept', function () {
        expect((new Configuration(['src_path' => 'src/', 'spec_path' => './spec']))->srcDir())->toBe('src');
        expect((new Configuration(['src_path' => 'src/', 'spec_path' => './spec']))->specDir())->toBe('spec');
        expect((new Configuration(['src_path' => '../lib', 'spec_path' => 'tests\\spec\\']))->srcDir())->toBe('../lib');
        expect((new Configuration(['src_path' => '../lib', 'spec_path' => 'tests\\spec\\']))->specDir())->toBe('tests/spec');
        expect((new Configuration(['src_path' => '.']))->srcDir())->toBe('');
        expect((new Configuration())->srcDir())->toBe('src');
    });

    it('says guard is on only when a usable section makes it active', function () {
        expect((new Configuration())->guardIsOn())->toBeFalse();
        expect((new Configuration(['guard' => ['status' => 'off']]))->guardIsOn())->toBeFalse();
        expect((new Configuration(['guard' => ['status' => 'active']]))->guardIsOn())->toBeTrue();
        expect((new Configuration(['guard' => ['status' => 'active', 'detecton' => 'git']]))->guardIsOn())->toBeFalse();
    });

    it('names the options a phpspec it starts needs to read this same configuration', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/conf/qa.yml');
        allow($fs->read(any()))->toReturn("spec_path: altspec\n");

        expect(Configuration::load('/app', $fs, '/app/conf/qa.yml')->childOptions())->toBe(['--config=/app/conf/qa.yml']);
        expect((new Configuration())->childOptions())->toBe([]);
    });

    it('leaves a file it found by itself to be found again by the phpspec it starts', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/phpspec.yml');
        allow($fs->read(any()))->toReturn("spec_path: altspec\n");

        expect(Configuration::load('/app', $fs)->childOptions())->toBe([]);
    });

    it('names the guard key it does not know, and the one it thinks was meant', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("guard:\n  status: active\n  detecton: git\n");

        $config = Configuration::load('/app', $fs);

        expect($config->guardConfigProblem())->toBe('Unknown guard key "detecton". Did you mean "detection"?');
        expect($config->getGuardConfig())->toBeNull();
    });

    it('says which values a guard setting accepts when given another', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("guard:\n  status: on\n");

        $config = Configuration::load('/app', $fs);

        expect($config->guardConfigProblem())->toBe('The guard section\'s status must be active or off, not "on".');
    });

    it('refuses a guarded root that is not a list of paths', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("guard:\n  status: active\n  paths: src\n");

        $config = Configuration::load('/app', $fs);

        expect($config->guardConfigProblem())->toBe('The guard section\'s paths must be a list of paths.');
    });

    it('returns ai config when configured with api_key', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  model: gemini-2.5-flash\n  api_key: test-key-123\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBe([
            'provider' => 'google',
            'model' => 'gemini-2.5-flash',
            'api_key' => 'test-key-123',
        ]);
    });

    it('accepts hyphenated spellings of the ai keys, api-key and max-tokens', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api-key: test-key-123\n  max-tokens: 32000\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBe([
            'provider' => 'google',
            'maxTokens' => 32000,
            'api_key' => 'test-key-123',
        ]);
    });

    it('names the config file to add the ai section to, and shows one', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("spec_path: spec\n");

        $problem = Configuration::load('/app', $fs)->aiConfigProblem();

        expect($problem)->toStartWith('AI configuration required. Add an "ai" section to phpspec.yaml, for example:');
        expect($problem)->toContain("\nai:\n  provider: ");
        expect($problem)->toContain("\n  api_key: YOUR_API_KEY");
    });

    it('shows the ai section in the shape of the config file the project has', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.json' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn('{"spec_path": "spec"}');

        $problem = Configuration::load('/app', $fs)->aiConfigProblem();

        expect($problem)->toContain('Add an "ai" section to phpspec.json, for example:');
        expect($problem)->toContain('"ai": {"provider": "');
        expect($problem)->toContain('"api_key": "YOUR_API_KEY"}');
    });

    it('says which file to create when the project has no config file at all', function () {
        $problem = (new Configuration())->aiConfigProblem();

        expect($problem)->toStartWith('AI configuration required. Create phpspec.yml with an "ai" section, for example:');
        expect($problem)->toContain("\nai:\n  provider: ");
    });

    it('names the missing key when the ai section exists without api_key', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
        expect($config->aiConfigProblem())->toBe('The ai section is missing api_key. Add it to your phpspec config.');
    });

    it('gives a missing ai section as a problem and, apart, the section that would answer it', function () {
        $refusal = (new Configuration([], '/app'))->aiConfigRefusal();

        expect($refusal['problem'] ?? null)->toBe('AI configuration required. Create phpspec.yml with an "ai" section.');
        expect($refusal['remedy'] ?? null)->toContain("ai:\n  provider: ");
        expect((new Configuration(['ai' => ['provider' => 'google', 'api_key' => '']]))->aiConfigRefusal())->toBe(['problem' => 'The ai section\'s api_key is empty. Put your key in your phpspec config.', 'remedy' => null]);
        expect((new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k']]))->aiConfigRefusal())->toBeNull();
    });

    it('takes an empty api_key for a missing one', function () {
        expect((new Configuration(['ai' => ['provider' => 'google', 'api_key' => '']]))->aiConfigProblem())->toBe('The ai section\'s api_key is empty. Put your key in your phpspec config.');
        expect((new Configuration(['ai' => ['provider' => 'google', 'api_key' => '  ']]))->getAiConfig())->toBeNull();
    });

    it('requires provider, and names the installed papi package as the answer', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  api_key: test-key-123\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
        expect($config->aiConfigProblem())->toBe('The ai section is missing provider. papi-ai/google is installed, so set provider: google.');
    });

    it('rejects an unknown provider, listing the known ones', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: googel\n  api_key: test-key-123\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
        expect($config->aiConfigProblem())->toBe('Unknown ai provider "googel". The known providers are google, anthropic, openai, grok, deepseek, and ollama.');
    });

    it('accepts an ollama section without api_key, passing its base_url through', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: ollama\n  base_url: http://localhost:11434\n");

        $config = Configuration::load('/app', $fs);

        expect($config->aiConfigProblem())->toBeNull();
        expect($config->getAiConfig())->toBe([
            'provider' => 'ollama',
            'base_url' => 'http://localhost:11434',
        ]);
    });

    it('catches a misspelled ai key with a suggestion', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api_keys: test-key-123\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
        expect($config->aiConfigProblem())->toBe('Unknown ai key "api_keys". Did you mean "api_key"?');
    });

    it('names a wrong-typed optional key instead of silently dropping it', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api_key: test-key-123\n  max_tokens: plenty\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
        expect($config->aiConfigProblem())->toBe('The ai section\'s max_tokens must be a positive number.');
    });

    it('names the wrong type when api_key is present but not a string', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api_key: 12345\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
        expect($config->aiConfigProblem())->toBe('The ai section\'s api_key must be a string. Quote it in your phpspec config.');
    });

    it('asks for the ai section when the config has none', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("spec_path: spec\n");

        $config = Configuration::load('/app', $fs);

        expect($config->aiConfigProblem())->toStartWith('AI configuration required. Add an "ai" section to phpspec.yaml, for example:');
    });

    it('reports no ai config problem when the section is usable', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api_key: test-key-123\n");

        $config = Configuration::load('/app', $fs);

        expect($config->aiConfigProblem())->toBeNull();
    });

    it('exposes a configured max_tokens as an int', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api_key: test-key-123\n  max_tokens: 32000\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBe([
            'provider' => 'google',
            'maxTokens' => 32000,
            'api_key' => 'test-key-123',
        ]);
    });

    it('exposes a configured reasoning effort, ignoring a non-string value', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api_key: test-key-123\n  effort: high\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBe([
            'provider' => 'google',
            'effort' => 'high',
            'api_key' => 'test-key-123',
        ]);

        // A wrong-typed effort is named, never silently dropped.
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n  api_key: test-key-123\n  effort: 12\n");
        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
        expect($config->aiConfigProblem())->toBe('The ai section\'s effort must be a non-empty string.');
    });

    it('returns null for ai config when not configured', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
    });

    it('returns null for ai config when api_key is missing', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("ai:\n  provider: google\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getAiConfig())->toBeNull();
    });

    it('loads config from phpspec.php when no yaml or json exists', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.php' => true,
            default => false,
        });
        allow($fs->requirePhp(any()))->toReturn(['spec_path' => 'tests', 'src_path' => 'lib']);

        $config = Configuration::load('/app', $fs);

        expect($config->getSpecPath())->toBe('tests');
        expect($config->getSrcPath())->toBe('lib');
    });

    it('returns default features path when not configured', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        $config = Configuration::load('/app', $fs);

        expect($config->getFeaturesPath())->toBe('features/');
    });

    it('returns configured features_path', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn("features_path: tests/features\n");

        $config = Configuration::load('/app', $fs);

        expect($config->getFeaturesPath())->toBe('tests/features');
    });

    it('returns src_path from first suite when flat key absent', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            default => false,
        });
        allow($fs->read(any()))->toReturn(
            "suites:\n  unit:\n    paths:\n      - unit_specs\n    src: lib\n",
        );

        $config = Configuration::load('/app', $fs);

        expect($config->getSrcPath())->toBe('lib');
    });

    it('loads an explicit config file instead of the working directory cascade', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === 'custom/my-config.json');
        allow($fs->read(any()))->toReturn(json_encode(['format' => 'dot']));

        $config = Configuration::load('/app', $fs, configFile: 'custom/my-config.json');

        expect($config->getFormat())->toBe('dot');
    });

    it('throws when the explicit config file does not exist', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);

        expect(fn() => Configuration::load('/app', $fs, configFile: 'nope.yaml'))
            ->toThrow(RuntimeException::class);
    });

    it('resolves the config path from argv tokens', function () {
        expect(Configuration::configPathFromArgv(['phpspec', 'run', '--config=a.yaml']))->toBe('a.yaml');
        expect(Configuration::configPathFromArgv(['phpspec', 'run', '--config', 'b.json']))->toBe('b.json');
        expect(Configuration::configPathFromArgv(['phpspec', 'run', '-c', 'c.yml']))->toBe('c.yml');
        expect(Configuration::configPathFromArgv(['phpspec', 'run']))->toBeNull();
    });

    it('has no steps path by default', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturn(false);
        $config = Configuration::load('/app', $fs);
        expect($config->getStepsPath())->toBeNull();
    });

    it('returns the configured steps path', function (Filesystem $fs) {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => $path === '/app/phpspec.json');
        allow($fs->read(any()))->toReturn(json_encode(['steps_path' => 'acceptance_steps']));

        $config = Configuration::load('/app', $fs);

        expect($config->getStepsPath())->toBe('acceptance_steps');
    });

    context('coverage exclusions', function () {
        it('names none when the config says nothing', function () {
            expect((new Configuration())->coverageExclusions())->toBe([]);
        });

        it('names the paths and names the config lists', function () {
            expect((new Configuration(['coverage_exclude' => ['src/Kernel.php', 'App\\Legacy\\*']]))->coverageExclusions())
                ->toBe(['src/Kernel.php', 'App\\Legacy\\*']);
        });
    });

    context('coverage adapter', function () {
        it('names none when the config says nothing', function () {
            expect((new Configuration())->coverageAdapter())->toBeNull();
        });

        it('names the adapter the config states', function () {
            expect((new Configuration(['coverage_adapter' => 'pcov']))->coverageAdapter())->toBe(CoverageAdapter::Pcov);
            expect((new Configuration(['coverage_adapter' => 'xdebug']))->coverageAdapter())->toBe(CoverageAdapter::Xdebug);
        });
    });

});
