<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

// E10 (eval) — an inferred generate target must land in the project's CONFIGURED
// layout, not hardcoded src/spec/features. Covers a custom src_path with a PSR-4
// prefix (stripped, exactly like phpspec's own generators), a custom spec_path
// (mirrors the full namespace), and a custom features_path.
describe('E10 generate: inferred paths respect the project layout', function () {

    // A GenerateAgent whose Configuration reads the given phpspec.yaml, replaying
    // a fixed model reply (the derived path must win over the reply's own path).
    $agentFor = function (Filesystem $fs, string $yaml, string $reply): GenerateAgent {
        $configFile = './phpspec.yaml';
        allow($fs->exists())->toReturnUsing(fn(string $path): bool => $path === $configFile);
        allow($fs->read())->toReturnUsing(fn(string $path): string => $path === $configFile ? $yaml : '');
        allow($fs->isDir())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->scandir())->toReturn([]);

        return new GenerateAgent(new Configuration('.', $fs), $fs, fn(): string => $reply);
    };

    it('places inferred code under the configured src_path with the PSR-4 prefix stripped', function (Filesystem $fs) use ($agentFor) {
        $agent = $agentFor($fs, "src_path: lib\npsr4_prefix: App\n", (string) json_encode(['path' => 'src/App/Coupon.php', 'content' => '<?php // impl']));

        $proposal = $agent->propose(['provider' => 'google', 'api_key' => 'x'], 'implement the apply method on App\\Coupon');

        expect($proposal['path'])->toBe('lib/Coupon.php');
    });

    it('places an inferred spec under the configured spec_path, mirroring the namespace', function (Filesystem $fs) use ($agentFor) {
        $agent = $agentFor($fs, "spec_path: test/spec\n", (string) json_encode(['path' => 'spec/Whatever.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"]));

        $proposal = $agent->propose(['provider' => 'google', 'api_key' => 'x'], 'a spec for App\\Coupon');

        expect($proposal['path'])->toBe('test/spec/App/Coupon.spec.php');
    });

    it('places an inferred feature under the configured features_path', function (Filesystem $fs) use ($agentFor) {
        $agent = $agentFor($fs, "features_path: test/features\n", (string) json_encode(['path' => 'x', 'content' => 'y']));

        $proposal = $agent->propose(['provider' => 'google', 'api_key' => 'x'], 'a feature for adding a task');

        expect($proposal['path'])->toMatch('~^test/features/.+\.feature$~');
    });

});
