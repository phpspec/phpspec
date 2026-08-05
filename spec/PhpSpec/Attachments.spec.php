<?php

use PhpSpec\Attachments;
use PhpSpec\EventDispatcher\Event\AttachmentCreated;

describe(Attachments::class, function () {

    it('has nothing to say until something is handed over', function () {
        $attachments = new Attachments();

        expect($attachments->isEmpty())->toBeTrue();
        expect($attachments->read())->toBe([]);
    });

    it('keeps text under the name it was handed over with', function () {
        $attachments = new Attachments();
        $attachments->add('stdout', 'CANNOT READ --watch');

        expect($attachments->read())->toBe(['stdout' => 'CANNOT READ --watch']);
    });

    it('reads a closure at the end, not when it was handed over', function () {
        $log = 'nothing yet';
        $attachments = new Attachments();
        $attachments->add('log', function () use (&$log) {
            return $log;
        });

        // The process the step started goes on writing after the attachment was made.
        $log = 'CANNOT READ --watch';

        expect($attachments->read())->toBe(['log' => 'CANNOT READ --watch']);
    });

    it('keeps the latest under a name, so a helper offering it every poll leaves one', function () {
        $attachments = new Attachments();
        $attachments->add('log', 'first look');
        $attachments->add('log', 'second look');

        expect($attachments->read())->toBe(['log' => 'second look']);
    });

    it('says a closure could not be read rather than showing an empty block', function () {
        $attachments = new Attachments();
        $attachments->add('log', fn() => false);

        expect($attachments->read())->toBe(['log' => ['error' => 'Nothing could be read']]);
    });

    it('says what went wrong when reading throws', function () {
        $attachments = new Attachments();
        $attachments->add('log', function () {
            throw new RuntimeException('the workspace is gone');
        });

        expect($attachments->read())->toBe(['log' => ['error' => 'the workspace is gone']]);
    });

    it('subscribes to the attachment event', function () {
        expect((new Attachments())->getSubscribedEvents())->toBe([AttachmentCreated::NAME => 'onAttachmentCreated']);
    });

});
