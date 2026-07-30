<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E10 (eval) — an inferred generate target must land in the project's
// CONFIGURED layout, not hardcoded src/spec/features. Covers a custom src_path
// with a PSR-4 prefix (stripped, exactly like phpspec's own generators), a
// custom spec_path (mirrors the full namespace), and a custom features_path.
describe('E10 generate: inferred paths respect the project layout', function () {

    // An Agent whose Configuration reads the given phpspec.yaml, replaying a
    // fixed model reply (the derived path must win over the reply's own path).
    $agentFor = function (Filesystem $fs, string $yaml, ReplayProvider $replay): Agent {
        $configFile = './phpspec.yaml';
        allow($fs->exists())->toReturnUsing(fn(string $path): bool => $path === $configFile);
        allow($fs->read())->toReturnUsing(fn(string $path): string => $path === $configFile ? $yaml : '');
        allow($fs->isDir())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        return new Agent(new Configuration('.', $fs), $fs, $replay);
    };

    $editCall = fn(string $path, string $content): ReplayProvider => new ReplayProvider([
        new Response('', [new ToolCall('1', 'propose_edit', ['path' => $path, 'content' => $content])]),
    ]);

    it('places inferred code under the configured src_path with the PSR-4 prefix stripped', function (Filesystem $fs) use ($agentFor, $editCall) {
        $agent = $agentFor($fs, "src_path: lib\npsr4_prefix: App\nai:\n  provider: google\n  api_key: x\n", $editCall('src/App/Coupon.php', '<?php // impl'));

        $outcome = $agent->chat('generate', 'implement the apply method on App\\Coupon');

        expect($outcome->proposals[0]->path)->toBe('lib/Coupon.php');
    });

    it('places an inferred spec under the configured spec_path, mirroring the namespace', function (Filesystem $fs) use ($agentFor, $editCall) {
        $agent = $agentFor($fs, "spec_path: test/spec\nai:\n  provider: google\n  api_key: x\n", $editCall('spec/Whatever.spec.php', "<?php\ndescribe('Coupon', fn() => null);"));

        $outcome = $agent->chat('generate', 'a spec for App\\Coupon');

        expect($outcome->proposals[0]->path)->toBe('test/spec/App/Coupon.spec.php');
    });

    it('places an inferred feature under the configured features_path, whatever the model says', function (Filesystem $fs) use ($agentFor) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'write_feature', ['path' => 'features/wrong_place.feature', 'content' => "Feature: Adding\n  Scenario: Adds\n    Given a list\n    When I add \"milk\"\n    Then it is there"])]),
        ]);
        $agent = $agentFor($fs, "features_path: test/features\nai:\n  provider: google\n  api_key: x\n", $replay);

        $outcome = $agent->chat('generate', 'a feature for adding a task');

        expect($replay->requests)->toHaveLength(1);
        expect($outcome->proposals[0]->path)->toMatch('~^test/features/.+\.feature$~');
        expect($outcome->proposals[0]->new)->toContain('When I add "milk"');
    });

});
