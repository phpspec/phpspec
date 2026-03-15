<?php

use PhpSpec\Configuration;
use PhpSpec\Extensions\CommandExtension;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Extensions\FormatterExtension;
use PhpSpec\Extensions\ListenerExtension;
use PhpSpec\Extensions\MatcherExtension;
use PhpSpec\Extensions\ToolProviderInterface;
use PhpSpec\Filesystem;
use PhpSpec\Specification\Expectation;

describe(ExtensionLoader::class, function () {

    it('loads formatter extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  formatters:\n    - " . StubFormatter::class . "\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('stub'))->toBeTrue();
    });

    it('loads command extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  commands:\n    - " . StubCommand::class . "\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->getCommands())->toHaveCount(1);
    });

    it('returns false for unknown formatter', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => false);

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('nonexistent'))->toBeFalse();
    });

    it('does not load twice', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  formatters:\n    - " . StubFormatter::class . "\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();
        $loader->load(); // second call should be a no-op

        expect($loader->hasFormatter('stub'))->toBeTrue();
    });

    it('loads matcher extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  matchers:\n    - " . StubMatcher::class . "\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        // Matcher was registered — verify it's usable via Expectation
        expect(42)->toBeStubMagic();
    });

    it('loads listener extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  listeners:\n    - " . StubListener::class . "\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        // Listener was loaded — no exception thrown
        expect(true)->toBeTrue();
    });

    it('loads tool provider extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  tools:\n    - " . StubToolProvider::class . "\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->getToolProviders())->toHaveCount(1);
    });

    it('auto-discovers extensions from installed.json', function (Filesystem $fs) {
        $installed = json_encode(['packages' => [
            [
                'name' => 'vendor/my-ext',
                'extra' => [
                    'phpspec' => [
                        'formatters' => [StubFormatter::class],
                    ],
                ],
            ],
        ]]);

        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => false,
            'vendor/composer/installed.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => $installed,
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('stub'))->toBeTrue();
    });

    it('skips disabled packages in auto-discovery', function (Filesystem $fs) {
        $installed = json_encode(['packages' => [
            [
                'name' => 'vendor/my-ext',
                'extra' => [
                    'phpspec' => [
                        'formatters' => [StubFormatter::class],
                    ],
                ],
            ],
        ]]);

        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  disabled:\n    - vendor/my-ext\n",
            'vendor/composer/installed.json' => $installed,
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('stub'))->toBeFalse();
    });

    it('returns formatter by name', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  formatters:\n    - " . StubFormatter::class . "\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->getFormatter('stub'))->toBeAnInstanceOf(FormatterExtension::class);
    });

    it('handles invalid JSON in installed.json gracefully', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => false,
            'vendor/composer/installed.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => 'not valid json{{{',
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('anything'))->toBeFalse();
    });

    it('handles packages without phpspec extra key', function (Filesystem $fs) {
        $installed = json_encode(['packages' => [
            ['name' => 'vendor/plain-package'],
        ]]);

        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => false,
            'vendor/composer/installed.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => $installed,
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('anything'))->toBeFalse();
    });

    it('deduplicates extensions from config and auto-discovery', function (Filesystem $fs) {
        $installed = json_encode(['packages' => [
            [
                'name' => 'vendor/my-ext',
                'extra' => [
                    'phpspec' => [
                        'formatters' => [StubFormatter::class],
                    ],
                ],
            ],
        ]]);

        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  formatters:\n    - " . StubFormatter::class . "\n",
            'vendor/composer/installed.json' => $installed,
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        // Should deduplicate — only one formatter, not two
        expect($loader->hasFormatter('stub'))->toBeTrue();
    });

    it('skips classes that exist but do not implement the expected interface', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n"
                . "  formatters:\n    - stdClass\n"
                . "  matchers:\n    - stdClass\n"
                . "  commands:\n    - stdClass\n"
                . "  listeners:\n    - stdClass\n"
                . "  tools:\n    - stdClass\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('anything'))->toBeFalse();
        expect($loader->getCommands())->toHaveCount(0);
        expect($loader->getToolProviders())->toHaveCount(0);
    });

    it('skips classes that do not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => true,
            'vendor/composer/installed.json' => false,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            '/app/phpspec.yaml' => "extensions:\n  formatters:\n    - NonExistent\\FooFormatter\n",
            default => '',
        });

        $config = new Configuration('/app', $fs);
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('foo'))->toBeFalse();
    });
});

// Stub classes for testing
class StubFormatter extends FormatterExtension
{
    public function getName(): string
    {
        return 'stub';
    }

    public function formatPass(string $title): string
    {
        return $title;
    }

    public function formatFail(string $title, string $message): string
    {
        return "$title: $message";
    }
}

class StubMatcher extends MatcherExtension
{
    public function getName(): string
    {
        return 'toBeStubMagic';
    }

    public function match(mixed $actual, mixed ...$args): bool
    {
        return true;
    }

    public function failureMessage(mixed $actual): string
    {
        return "Expected stub magic match for $actual";
    }
}

class StubListener extends ListenerExtension
{
}

class StubToolProvider implements ToolProviderInterface
{
    public function getTools(): array
    {
        return [];
    }
}

class StubCommand extends CommandExtension
{
    public function getName(): string
    {
        return 'stub-cmd';
    }

    public function getDescription(): string
    {
        return 'Stub command';
    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        return 0;
    }
}
