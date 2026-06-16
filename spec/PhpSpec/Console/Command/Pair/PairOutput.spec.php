<?php

use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Pair\ScrollRegionOutput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(PairOutput::class, function () {
    let('buffer', fn() => new BufferedOutput());
    let('pairOutput', fn() => new PairOutput($this->buffer));

    it('instantiates', fn() => expect($this->pairOutput)->toBeAnInstanceOf(PairOutput::class));

    it('returns the raw output before setupLayout', function () {
        expect($this->pairOutput->getOutput())->toBe($this->buffer);
    });

    it('returns ScrollRegionOutput after setupLayout', function () {
        $this->pairOutput->setupLayout();
        expect($this->pairOutput->getOutput())->toBeAnInstanceOf(ScrollRegionOutput::class);
    });

    it('draws header on setupLayout', function () {
        $this->pairOutput->setupLayout();
        $output = $this->buffer->fetch();
        expect($output)->toContain('PhpSpec Pair Programming Mode');
    });

    it('shows goodbye message', function () {
        $this->pairOutput->showGoodbye();
        $output = $this->buffer->fetch();
        expect($output)->toContain('Goodbye!');
    });

    it('resets scroll region on goodbye', function () {
        $this->pairOutput->showGoodbye();
        $output = $this->buffer->fetch();
        // Should contain scroll region reset escape
        expect($output)->toContain("\033[r");
    });

    it('displays a success message', function () {
        $this->pairOutput->success('All good');
        $output = $this->buffer->fetch();
        expect($output)->toContain('All good');
    });

    it('displays an error message', function () {
        $this->pairOutput->error('Something broke');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Something broke');
    });

    it('displays a new file with green line numbers and plus markers', function () {
        $this->pairOutput->fileDisplay('/tmp/test.php', "<?php\necho 'hi';", true);
        $output = $this->buffer->fetch();
        expect($output)->toContain('[NEW FILE]');
        expect($output)->toContain('/tmp/test.php');
        expect($output)->toContain('1 + ');
        expect($output)->toContain('2 + ');
    });

    it('displays a modified file with MODIFIED label', function () {
        $this->pairOutput->fileDisplay('/tmp/test.php', "<?php\n", false);
        $output = $this->buffer->fetch();
        expect($output)->toContain('[MODIFIED]');
    });

    it('sets up scroll region escape on setupLayout', function () {
        $this->pairOutput->setupLayout();
        $output = $this->buffer->fetch();
        // Should contain scroll region set escape (CSI n;m r)
        expect($output)->toMatch('/\033\[\d+;\d+r/');
    });

    it('echoes user input as a quoted line', function () {
        $this->pairOutput->echoInput('describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain("\u{2502}");
        expect($output)->toContain('describe Acme\Greeter');
    });

    it('prepareForInput delegates to scroll output after setup', function () {
        $this->pairOutput->setupLayout();
        $this->buffer->fetch(); // discard setup output
        $this->pairOutput->prepareForInput();
        $output = $this->buffer->fetch();
        // Should contain divider and cursor positioning
        expect($output)->toContain("\u{2500}");
    });

    it('prepareForInput triggers layout setup on first call', function () {
        $this->pairOutput->prepareForInput();
        $output = $this->buffer->fetch();
        // Lazy resize triggers setupLayout, which draws divider
        expect($output)->toContain("\u{2500}");
    });

    it('returnToContent delegates to scroll output after setup', function () {
        $this->pairOutput->setupLayout();
        $this->buffer->fetch(); // discard setup output
        $this->pairOutput->returnToContent();
        $output = $this->buffer->fetch();
        expect($output)->toContain("\033[K");
    });

    it('returnToContent emits escape after lazy setup', function () {
        $this->pairOutput->prepareForInput(); // triggers lazy setup
        $this->buffer->fetch(); // discard
        $this->pairOutput->returnToContent();
        $output = $this->buffer->fetch();
        expect($output)->toContain("\033[K");
    });

    it('clearScreen re-runs setupLayout', function () {
        $this->pairOutput->clearScreen();
        $output = $this->buffer->fetch();
        expect($output)->toContain('PhpSpec Pair Programming Mode');
    });

    context('fileDiff', function () {
        it('shows MODIFIED label and path', function () {
            $this->pairOutput->fileDiff('/tmp/file.php', "old\n", "new\n");
            $output = $this->buffer->fetch();
            expect($output)->toContain('[MODIFIED]');
            expect($output)->toContain('/tmp/file.php');
        });

        it('shows added lines with plus marker', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', '', "line1\nline2");
            $output = $this->buffer->fetch();
            expect($output)->toContain('+ line1');
            expect($output)->toContain('+ line2');
        });

        it('shows removed lines with minus marker', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', "old line\n", '');
            $output = $this->buffer->fetch();
            expect($output)->toContain('- old line');
        });

        it('shows unchanged lines without marker', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', "same\n", "same\n");
            $output = $this->buffer->fetch();
            expect($output)->toContain('same');
            expect($output)->not()->toContain('+ same');
            expect($output)->not()->toContain('- same');
        });

        it('shows mixed diff with adds, removes, and unchanged', function () {
            $old = "line1\nline2\nline3";
            $new = "line1\nchanged\nline3";
            $this->pairOutput->fileDiff('/tmp/f.php', $old, $new);
            $output = $this->buffer->fetch();
            expect($output)->toContain('- line2');
            expect($output)->toContain('+ changed');
        });

        it('includes line numbers in diff output', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', "a\nb", "a\nc");
            $output = $this->buffer->fetch();
            expect($output)->toMatch('/\d+ \+ c/');
            expect($output)->toMatch('/\d+ - b/');
        });

        it('handles empty old content with all adds', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', '', "new1\nnew2\nnew3");
            $output = $this->buffer->fetch();
            expect($output)->toContain('+ new1');
            expect($output)->toContain('+ new2');
            expect($output)->toContain('+ new3');
        });

        it('handles empty new content with all removes', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', "old1\nold2", '');
            $output = $this->buffer->fetch();
            expect($output)->toContain('- old1');
            expect($output)->toContain('- old2');
        });

        it('handles identical content with no markers', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', "a\nb\nc", "a\nb\nc");
            $output = $this->buffer->fetch();
            expect($output)->not()->toContain('+ ');
            expect($output)->not()->toContain('- ');
        });

        it('handles appended lines', function () {
            $this->pairOutput->fileDiff('/tmp/f.php', "a\nb", "a\nb\nc");
            $output = $this->buffer->fetch();
            expect($output)->toContain('+ c');
            expect($output)->not()->toContain('- a');
        });
    });
});
