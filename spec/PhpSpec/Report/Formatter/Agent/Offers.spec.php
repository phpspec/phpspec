<?php

use PhpSpec\Report\Formatter\Agent\Offers;

describe(Offers::class, function () {

    it('maps a missing spec class to create_class', function () {
        $offers = Offers::fromCandidates(['missingSpecClasses' => ['App\\Coupon']]);

        expect($offers[0]['action'])->toBe('create_class');
        expect($offers[0]['target'])->toBe('App\\Coupon');
    });

    it('maps a missing mock type to create_interface', function () {
        $offers = Offers::fromCandidates(['missingMockTypes' => ['App\\Repository']]);

        expect($offers[0]['action'])->toBe('create_interface');
        expect($offers[0]['target'])->toBe('App\\Repository');
    });

    it('maps an undefined class method to create_method with a Class::method target', function () {
        $candidates = ['undefinedClassMethods' => [
            ['className' => 'App\\Basket', 'methodName' => 'checkout', 'file' => 'x', 'line' => 1],
        ]];

        $offers = Offers::fromCandidates($candidates);

        expect($offers[0]['action'])->toBe('create_method');
        expect($offers[0]['target'])->toBe('App\\Basket::checkout');
    });

    it('maps a fakeable method to fake_method carrying its return expression as value', function () {
        $candidates = ['fakeableMethods' => [
            ['className' => 'App\\Calc', 'methodName' => 'add', 'fakeExpression' => 'return 3', 'file' => 'x', 'line' => 1],
        ]];

        $offers = Offers::fromCandidates($candidates);

        expect($offers[0]['action'])->toBe('fake_method');
        expect($offers[0]['target'])->toBe('App\\Calc::add');
        expect($offers[0]['value'])->toBe('return 3');
    });

    it('maps undefined steps to create_steps keyed by feature path', function () {
        $candidates = ['undefinedSteps' => [
            'features/checkout.feature' => [['keyword' => 'Given', 'text' => 'a basket']],
        ]];

        $offers = Offers::fromCandidates($candidates);

        expect($offers[0]['action'])->toBe('create_steps');
        expect($offers[0]['target'])->toBe('features/checkout.feature');
    });

    it('deduplicates the same action and target', function () {
        $candidates = ['missingSpecClasses' => ['App\\Coupon'], 'missingStepClasses' => ['App\\Coupon']];

        $offers = Offers::fromCandidates($candidates);

        expect($offers)->toHaveLength(1);
        expect($offers[0]['target'])->toBe('App\\Coupon');
    });

    it('returns an empty list when there is nothing to generate', function () {
        expect(Offers::fromCandidates([]))->toBe([]);
    });

    it('derives a create_class offer from a "Class not found" error', function () {
        $offer = Offers::forError('Class "App\\Coupon" not found');

        expect($offer['action'])->toBe('create_class');
        expect($offer['target'])->toBe('App\\Coupon');
        // The same id the run-wide offer carries: both are derived from the same thing.
        expect($offer['id'])->toBe(Offers::fromCandidates(['missingSpecClasses' => ['App\\Coupon']])[0]['id']);
    });

    it('derives a create_interface offer from a mock-creation error', function () {
        $offer = Offers::forError("Cannot create mock: class or interface 'App\\Repo' does not exist");

        expect($offer['action'])->toBe('create_interface');
        expect($offer['target'])->toBe('App\\Repo');
    });

    it('derives a create_method offer from an undefined-method error', function () {
        $offer = Offers::forError('Call to undefined method App\\Basket::checkout()');

        expect($offer['action'])->toBe('create_method');
        expect($offer['target'])->toBe('App\\Basket::checkout');
    });

    it('returns null for an error a generator cannot resolve', function () {
        expect(Offers::forError('Division by zero'))->toBeNull();
    });

});
