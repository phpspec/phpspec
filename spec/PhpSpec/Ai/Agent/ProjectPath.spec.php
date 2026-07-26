<?php

use PhpSpec\Ai\Agent\ProjectPath;

// The ONE project-path normaliser, shared by the registry, the agent, and pair:
// separators are normalised on BOTH sides before stripping the cwd, so a
// Windows path never silently fails the starts-with check (the PR #1537 lesson).
describe(ProjectPath::class, function () {

    it('makes an absolute path under the project relative, with forward slashes', function () {
        expect(ProjectPath::relative(getcwd() . '/features/adding.feature'))->toBe('features/adding.feature');
    });

    it('normalises backslashes on both sides before stripping', function () {
        expect(ProjectPath::relative(str_replace('/', '\\', getcwd() . '/src/App/Calc.php')))->toBe('src/App/Calc.php');
    });

    it('leaves an already-relative path as it is', function () {
        expect(ProjectPath::relative('spec/App/Calc.spec.php'))->toBe('spec/App/Calc.spec.php');
    });

    it('passes null through for optional scans', function () {
        expect(ProjectPath::relativeOrNull(null))->toBeNull();
        expect(ProjectPath::relativeOrNull(getcwd() . '/src/X.php'))->toBe('src/X.php');
    });

    it('rebuilds the absolute path for a relative one', function () {
        expect(ProjectPath::absolute('src/X.php'))->toBe(getcwd() . '/src/X.php');
    });

});
