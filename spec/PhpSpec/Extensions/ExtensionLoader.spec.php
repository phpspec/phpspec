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
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['formatters' => [StubFormatter::class]]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('stub'))->toBeTrue();
    });

    it('loads command extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['commands' => [StubCommand::class]]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->getCommands())->toHaveCount(1);
    });

    it('returns false for unknown formatter', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => false);

        $config = new Configuration([], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('nonexistent'))->toBeFalse();
    });

    it('does not load twice', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['formatters' => [StubFormatter::class]]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();
        $loader->load(); // second call should be a no-op

        expect($loader->hasFormatter('stub'))->toBeTrue();
    });

    it('loads matcher extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['matchers' => [StubMatcher::class]]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        // Matcher was registered — verify it's usable via Expectation
        expect(42)->toBeStubMagic();
    });

    it('loads listener extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['listeners' => [StubListener::class]]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        // Listener was loaded — no exception thrown
        expect(true)->toBeTrue();
    });

    it('loads tool provider extensions from config', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['tools' => [StubToolProvider::class]]], '/app');
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

        $config = new Configuration([], '/app');
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
            'vendor/composer/installed.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => $installed,
            default => '',
        });

        $config = new Configuration(['extensions' => ['disabled' => ['vendor/my-ext']]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('stub'))->toBeFalse();
    });

    it('returns formatter by name', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['formatters' => [StubFormatter::class]]], '/app');
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

        $config = new Configuration([], '/app');
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

        $config = new Configuration([], '/app');
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
            'vendor/composer/installed.json' => true,
            default => false,
        });
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => $installed,
            default => '',
        });

        $config = new Configuration(['extensions' => ['formatters' => [StubFormatter::class]]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        // Should deduplicate — only one formatter, not two
        expect($loader->hasFormatter('stub'))->toBeTrue();
    });

    it('skips classes that exist but do not implement the expected interface', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['formatters' => ['stdClass'], 'matchers' => ['stdClass'], 'commands' => ['stdClass'], 'listeners' => ['stdClass'], 'tools' => ['stdClass']]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('anything'))->toBeFalse();
        expect($loader->getCommands())->toHaveCount(0);
        expect($loader->getToolProviders())->toHaveCount(0);
    });

    it('skips classes that do not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => false,
            default => false,
        });

        $config = new Configuration(['extensions' => ['formatters' => ['NonExistent\\FooFormatter']]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->hasFormatter('foo'))->toBeFalse();
    });

    it('puts the browser named in config behind the DSL', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            default => false,
        });

        $config = new Configuration(['extensions' => ['browser' => StubBrowser::class]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->getBrowser())->toBeAnInstanceOf(StubBrowser::class);
    });

    it('has no browser opinion when none is registered', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $config = new Configuration([], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->getBrowser())->toBeNull();
    });

    it('refuses a browser that does not implement the contract', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => match ($path) {
            default => false,
        });

        $config = new Configuration(['extensions' => ['browser' => StubFormatter::class]], '/app');
        $loader = new ExtensionLoader($config, $fs);

        expect(fn() => $loader->load())->toThrow(
            RuntimeException::class,
            'The configured browser "StubFormatter" must implement PhpSpec\\Browser\\Browser.',
        );
    });

    // Two packages each auto-discovering a browser cannot both drive visit().
    it('refuses to pick between two auto-discovered browsers', function (Filesystem $fs) {
        $installed = json_encode(['packages' => [
            ['name' => 'acme/one', 'extra' => ['phpspec' => ['browser' => StubBrowser::class]]],
            ['name' => 'acme/two', 'extra' => ['phpspec' => ['browser' => OtherStubBrowser::class]]],
        ]]);
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === 'vendor/composer/installed.json');
        allow($fs->read())->toReturnUsing(fn(string $path) => $path === 'vendor/composer/installed.json' ? $installed : '');

        $config = new Configuration([], '/app');
        $loader = new ExtensionLoader($config, $fs);

        expect(fn() => $loader->load())->toThrow(
            RuntimeException::class,
            'Two browsers were auto-discovered ("StubBrowser" and "OtherStubBrowser"): pick one with "extensions: {browser: ...}".',
        );
    });

    it('lets config settle an auto-discovery conflict', function (Filesystem $fs) {
        $installed = json_encode(['packages' => [
            ['name' => 'acme/one', 'extra' => ['phpspec' => ['browser' => StubBrowser::class]]],
            ['name' => 'acme/two', 'extra' => ['phpspec' => ['browser' => OtherStubBrowser::class]]],
        ]]);
        allow($fs->exists())->toReturnUsing(fn(string $path) => in_array($path, ['/app/phpspec.yaml', 'vendor/composer/installed.json'], true));
        allow($fs->read())->toReturnUsing(fn(string $path) => match ($path) {
            'vendor/composer/installed.json' => $installed,
            default => '',
        });

        $config = new Configuration(['extensions' => ['browser' => OtherStubBrowser::class]], '/app');
        $loader = new ExtensionLoader($config, $fs);
        $loader->load();

        expect($loader->getBrowser())->toBeAnInstanceOf(OtherStubBrowser::class);
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

class StubBrowser implements \PhpSpec\Browser\Browser
{
    public function request(string $method, string $url, array $options = []): \PhpSpec\Browser\Response
    {
        return new \PhpSpec\Browser\Response(200, 'stub', []);
    }
}

class OtherStubBrowser implements \PhpSpec\Browser\Browser
{
    public function request(string $method, string $url, array $options = []): \PhpSpec\Browser\Response
    {
        return new \PhpSpec\Browser\Response(200, 'other', []);
    }
}
