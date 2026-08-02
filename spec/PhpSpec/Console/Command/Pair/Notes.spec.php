<?php

use PhpSpec\Console\Command\Pair\Notes;

describe(Notes::class, function () {

    beforeEach(function () {
        $this->notes = new Notes();
    });

    it('leaves the instruction alone when nothing was noted', function () {
        $this->notes->record('Do you want to run specs now?', true, '');

        expect($this->notes->brief('what next?'))->toBe('what next?');
    });

    it('briefs a declined answer with the question and the note', function () {
        $this->notes->record('Do you want me to create class App\Todo for you?', false, 'it should be a value object');

        $briefing = $this->notes->brief('what next?');

        expect($briefing)->toContain('[Notes I left on the choices I just made]');
        expect($briefing)->toContain('I declined "Do you want me to create class App\Todo for you?" and said: it should be a value object');
        expect($briefing)->toEndWith("\n\nwhat next?");
    });

    it('briefs an accepted answer as accepted', function () {
        $this->notes->record('Do you want to run specs now?', true, 'only the features');

        expect($this->notes->brief('go on'))->toContain('I accepted "Do you want to run specs now?" and said: only the features');
    });

    it('strips console markup from the question', function () {
        $this->notes->record('Create class <fg=white>App\Todo</> for you?', false, 'later');

        expect($this->notes->brief('go on'))->toContain('I declined "Create class App\Todo for you?" and said: later');
    });

    it('briefs every pending note in the order they were left', function () {
        $this->notes->record('Create the class?', false, 'value object');
        $this->notes->record('Run specs now?', true, 'features only');

        $briefing = $this->notes->brief('go on');

        expect(strpos($briefing, 'value object'))->toBeLessThan(strpos($briefing, 'features only'));
    });

    it('drains the pending notes so the next briefing repeats nothing', function () {
        $this->notes->record('Create the class?', false, 'value object');
        $this->notes->brief('go on');

        expect($this->notes->brief('and now?'))->toBe('and now?');
    });

    it('hands the latest note to a caller that claims it', function () {
        $this->notes->record('Apply this change?', false, 'use a collection');

        expect($this->notes->take())->toBe('use a collection');
    });

    it('leaves nothing to brief once the latest note is claimed', function () {
        $this->notes->record('Apply this change?', false, 'use a collection');
        $this->notes->take();

        expect($this->notes->brief('go on'))->toBe('go on');
    });

    it('claims nothing when the latest answer carried no note', function () {
        $this->notes->record('Create the class?', false, 'value object');
        $this->notes->record('Apply this change?', true, '');

        expect($this->notes->take())->toBe('');
    });

    it('keeps an earlier note pending when a later answer is claimed', function () {
        $this->notes->record('Create the class?', false, 'value object');
        $this->notes->record('Apply this change?', true, 'rename it');

        expect($this->notes->take())->toBe('rename it');
        expect($this->notes->brief('go on'))->toContain('value object');
        expect($this->notes->brief('go on'))->toBe('go on');
    });

    it('claims a note only once', function () {
        $this->notes->record('Apply this change?', false, 'use a collection');

        expect($this->notes->take())->toBe('use a collection');
        expect($this->notes->take())->toBe('');
    });

    it('ignores a note that is only whitespace', function () {
        $this->notes->record('Apply this change?', false, '   ');

        expect($this->notes->take())->toBe('');
        expect($this->notes->brief('go on'))->toBe('go on');
    });
});
