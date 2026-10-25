<?php

use PhpSpec\Source\Declarations;

describe(Declarations::class, function () {
    it('reads the interfaces a file declares and the ones each extends, named as PHP resolves them', function () {
        $declared = new Declarations(<<<'PHP'
            <?php

            namespace App\Contracts;

            use Vendor\Tags\Named;
            use Vendor\Tags\Labelled as Tag;
            use Vendor\{Kinds\Kind, Marks\Mark as Marked};
            use function strlen;
            use const PHP_EOL;

            interface Child extends Tag, Named, Local, Sub\Deep, \Rooted, namespace\Relative, Marked, Kind {}

            interface Plain {}
            PHP);

        expect($declared->interfaces())->toBe([
            'app\contracts\child' => ['vendor\tags\labelled', 'vendor\tags\named', 'app\contracts\local', 'app\contracts\sub\deep', 'rooted', 'app\contracts\relative', 'vendor\marks\mark', 'vendor\kinds\kind'],
            'app\contracts\plain' => [],
        ]);
    });

    it('reads the enums a file declares, as they are spelled, and the interfaces each implements', function () {
        $declared = new Declarations(<<<'PHP'
            <?php

            namespace Domain;

            use App\Contracts\Child;

            enum Status: string implements Child, \JsonSerializable
            {
                case Sent = 'sent';

                public function jsonSerialize(): mixed
                {
                    return $this->value;
                }
            }

            enum Plain
            {
                case One;
            }
            PHP);

        expect($declared->enums())->toBe([
            'Domain\Status' => ['app\contracts\child', 'jsonserializable'],
            'Domain\Plain' => [],
        ]);
    });

    it('takes no import from a class body, and no declaration from an anonymous class or a class constant', function () {
        $declared = new Declarations(<<<'PHP'
            <?php

            namespace App;

            final class Shelf
            {
                use Countable;

                public function make(): object
                {
                    $name = self::class;

                    return new class implements Missing {};
                }
            }

            interface After extends Countable {}
            PHP);

        expect($declared->interfaces())->toBe(['app\after' => ['app\countable']]);
        expect($declared->enums())->toBe([]);
    });

    it('starts each namespace afresh, its imports with it', function () {
        $declared = new Declarations(<<<'PHP'
            <?php

            namespace First {
                use Shared\Base;

                interface One extends Base {}
            }

            namespace Second {
                interface Two extends Base {}
            }

            namespace {
                interface Three extends Base {}
            }
            PHP);

        expect($declared->interfaces())->toBe([
            'first\one' => ['shared\base'],
            'second\two' => ['second\base'],
            'three' => ['base'],
        ]);
    });

    it('reads a source cut short, as one changed mid-run, without failing', function () {
        foreach (['<?php use Foo\\Bar', '<?php use function f', '<?php interface'] as $source) {
            expect((new Declarations($source))->enums())->toBe([]);
        }

        expect((new Declarations('<?php enum Status implements'))->enums())->toBe(['Status' => []]);
    });
});
