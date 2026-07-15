<?php

use PhpSpec\Report\Formatter\Agent\Offers;

describe(Offers::class, function () {

    it('maps a missing spec class to create_class', function () {
        expect(Offers::fromCandidates(['missingSpecClasses' => ['App\\Coupon']]))
            ->toBe([['action' => 'create_class', 'target' => 'App\\Coupon']]);
    });

    it('maps a missing mock type to create_interface', function () {
        expect(Offers::fromCandidates(['missingMockTypes' => ['App\\Repository']]))
            ->toBe([['action' => 'create_interface', 'target' => 'App\\Repository']]);
    });

    it('maps an undefined class method to create_method with a Class::method target', function () {
        $candidates = ['undefinedClassMethods' => [
            ['className' => 'App\\Basket', 'methodName' => 'checkout', 'file' => 'x', 'line' => 1],
        ]];

        expect(Offers::fromCandidates($candidates))
            ->toBe([['action' => 'create_method', 'target' => 'App\\Basket::checkout']]);
    });

    it('maps a fakeable method to fake_method carrying its return expression as value', function () {
        $candidates = ['fakeableMethods' => [
            ['className' => 'App\\Calc', 'methodName' => 'add', 'fakeExpression' => 'return 3', 'file' => 'x', 'line' => 1],
        ]];

        expect(Offers::fromCandidates($candidates))
            ->toBe([['action' => 'fake_method', 'target' => 'App\\Calc::add', 'value' => 'return 3']]);
    });

    it('maps undefined steps to create_steps keyed by feature path', function () {
        $candidates = ['undefinedSteps' => [
            'features/checkout.feature' => [['keyword' => 'Given', 'text' => 'a basket']],
        ]];

        expect(Offers::fromCandidates($candidates))
            ->toBe([['action' => 'create_steps', 'target' => 'features/checkout.feature']]);
    });

    it('deduplicates the same action and target', function () {
        $candidates = ['missingSpecClasses' => ['App\\Coupon'], 'missingStepClasses' => ['App\\Coupon']];

        expect(Offers::fromCandidates($candidates))
            ->toBe([['action' => 'create_class', 'target' => 'App\\Coupon']]);
    });

    it('returns an empty list when there is nothing to generate', function () {
        expect(Offers::fromCandidates([]))->toBe([]);
    });

    it('derives a create_class offer from a "Class not found" error', function () {
        expect(Offers::forError('Class "App\\Coupon" not found'))
            ->toBe(['action' => 'create_class', 'target' => 'App\\Coupon']);
    });

    it('derives a create_interface offer from a mock-creation error', function () {
        expect(Offers::forError("Cannot create mock: class or interface 'App\\Repo' does not exist"))
            ->toBe(['action' => 'create_interface', 'target' => 'App\\Repo']);
    });

    it('derives a create_method offer from an undefined-method error', function () {
        expect(Offers::forError('Call to undefined method App\\Basket::checkout()'))
            ->toBe(['action' => 'create_method', 'target' => 'App\\Basket::checkout']);
    });

    it('returns null for an error a generator cannot resolve', function () {
        expect(Offers::forError('Division by zero'))->toBeNull();
    });

});
