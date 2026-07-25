<?php

use PhpSpec\CodeGeneration\LegacySpecDetector;

// The ONE detector for phpspec 8 ObjectBehavior idioms, shared by every guard
// that vets AI-written spec content (the pipeline's propose_edit and pair's
// raw writes). The model's training prior leans hard on ObjectBehavior, so
// this is enforced in code, never just discouraged in a prompt.
describe(LegacySpecDetector::class, function () {

    it('detects the ObjectBehavior class syntax', function () {
        expect(LegacySpecDetector::looksLegacy("<?php\nclass BasketSpec extends ObjectBehavior {}"))->toBe(true);
    });

    it('detects the old spec file namespace', function () {
        expect(LegacySpecDetector::looksLegacy("<?php\nnamespace spec\\App;\n// anything"))->toBe(true);
    });

    it('detects shouldXxx matchers', function () {
        expect(LegacySpecDetector::looksLegacy("<?php\ndescribe('Basket', fn() => \$basket->total()->shouldReturn(5));"))->toBe(true);
    });

    it('detects a method called on the subject $this', function () {
        expect(LegacySpecDetector::looksLegacy("<?php\ndescribe('Basket', function () { it('x', fn() => expect(\$this->total())->toBe(0)); });"))->toBe(true);
    });

    it('passes a modern spec, including let-bound property access on $this', function () {
        $modern = <<<'SPEC'
        <?php

        use App\Basket;

        describe(Basket::class, function () {
            let('basket', fn() => new Basket());

            it('adds an item to the total', function () {
                expect($this->basket->add(5)->total())->toBe(5);
            });
        });
        SPEC;

        expect(LegacySpecDetector::looksLegacy($modern))->toBe(false);
    });

});
