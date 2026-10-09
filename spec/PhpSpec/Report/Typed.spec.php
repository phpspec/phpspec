<?php

use PhpSpec\ObjectName;
use PhpSpec\Report\ReportedObject;
use PhpSpec\Report\Typed;

final class TypedPoint
{
    public function __construct(public int $x, public int $y) {}
}

describe(Typed::class, function () {

    it("shows a value typed: a string in quotes, a number bare, null as null, a bool as a word, an array element by element", function () {
        expect(Typed::value('text'))->toBe('"text"');
        expect(Typed::value(3))->toBe('3');
        expect(Typed::value(1.5))->toBe('1.5');
        expect(Typed::value(null))->toBe('null');
        expect(Typed::value(true))->toBe('true');
        expect(Typed::value([1, 'b' => 'two']))->toBe('[1, b => "two"]');
    });

    it("shows an object by its name with its properties when the name alone tells nothing", function () {
        expect(Typed::value(new TypedPoint(1, 2)))->toBe('TypedPoint{x: 1, y: 2}');
    });

    it("shows an object reported from another process the way that process showed it, and names it as that process named it", function () {
        $reported = new ReportedObject('TypedPoint', 'TypedPoint{x: 1, y: 2}');

        expect(Typed::value($reported))->toBe('TypedPoint{x: 1, y: 2}');
        expect(Typed::value(['at' => $reported]))->toBe('[at => TypedPoint{x: 1, y: 2}]');
        expect(ObjectName::of($reported))->toBe('TypedPoint');
    });
});
