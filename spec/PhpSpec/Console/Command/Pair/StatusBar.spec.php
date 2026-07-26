<?php

use PhpSpec\Console\Command\Pair\RoleState;
use PhpSpec\Console\Command\Pair\StatusBar;

describe(StatusBar::class, function () {

    it('shows the working directory, the provider, and the model when AI is on', function () {
        $bar = new StatusBar('~/lab/test-phpspec-9', true, 'google', new RoleState(), null, 'gemini-3-pro-preview');

        [$status, $role] = $bar->lines(120);

        expect($status)->toContain('~/lab/test-phpspec-9');
        expect($status)->toContain('ai: on');
        expect($status)->toContain('provider: google');
        expect($status)->toContain('model: gemini-3-pro-preview');
        expect($role)->toContain('ai is navigator');
        expect($role)->toContain('/swap');
    });

    it('leaves the model segment out when no model is known', function () {
        $bar = new StatusBar('~/x', true, 'google', new RoleState());

        [$status] = $bar->lines(80);

        expect($status)->toContain('provider: google');
        expect($status)->not()->toContain('model:');
    });

    it('reflects the AI driving after a swap', function () {
        $roleState = new RoleState();
        $roleState->swap();

        $bar = new StatusBar('~/x', true, 'google', $roleState);

        [, $role] = $bar->lines(80);

        expect($role)->toContain('ai is driver');
    });

    it('shows ai off and a hint to enable when no provider is configured', function () {
        $bar = new StatusBar('~/x', false, null, new RoleState());

        [$status, $role] = $bar->lines(80);

        expect($status)->toContain('ai: off');
        expect($status)->not()->toContain('provider');
        expect($role)->toContain('ai: section');
        expect($role)->not()->toContain('navigator');
    });

    it('shows ai unavailable when a configured provider could not start', function () {
        $bar = new StatusBar('~/x', false, 'anthropic', new RoleState(), 'provider package not installed');

        [$status, $role] = $bar->lines(80);

        expect($status)->toContain('ai: unavailable');
        expect($role)->toContain('could not start');
        expect($role)->not()->toContain('navigator');
    });

    it('abbreviates the home-directory prefix to ~', function () {
        expect(StatusBar::abbreviateHome('/home/md/lab/proj', '/home/md'))->toBe('~/lab/proj');
        expect(StatusBar::abbreviateHome('/var/www', '/home/md'))->toBe('/var/www');
        expect(StatusBar::abbreviateHome('/home/md/x', ''))->toBe('/home/md/x');
    });

    it('right-aligns the provider info within the given width', function () {
        $bar = new StatusBar('~/x', true, 'google', new RoleState());

        [$status] = $bar->lines(80);

        // working dir on the left, provider info flush to the right
        $plain = preg_replace('/\033\[[0-9;]*m/', '', $status);
        expect(strpos($plain, '~/x'))->toBeLessThan((int) strpos($plain, 'ai: on'));
    });

});
