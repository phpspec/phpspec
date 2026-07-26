<?php

use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Filesystem;

// The single write gate: nothing in the pipeline touches disk except this,
// after the presenter has confirmed.
describe(Writer::class, function () {

    beforeEach(function (Filesystem $fs) {
        $this->written = [];
        $this->made = [];
        $written = &$this->written;
        $made = &$this->made;
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });
        allow($fs->mkdir())->toReturnUsing(function (string $path) use (&$made) {
            $made[] = $path;
        });
    });

    it('creates the directory and writes a new file under the base dir', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        (new Writer($fs, '/proj'))->apply(new Proposal('features/steps/adding.steps.php', '', '<?php // steps', true, 'write_steps'));

        expect($this->made)->toContain('/proj/features/steps');
        expect($this->written['/proj/features/steps/adding.steps.php'])->toBe('<?php // steps');
    });

    it('writes into an existing directory without recreating it', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);

        (new Writer($fs, '/proj'))->apply(new Proposal('src/Calc.php', '<?php // old', '<?php // new', false));

        expect($this->made)->toBe([]);
        expect($this->written['/proj/src/Calc.php'])->toBe('<?php // new');
    });

});
