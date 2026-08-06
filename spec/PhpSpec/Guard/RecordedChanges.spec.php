<?php

use PhpSpec\Guard\Changes;
use PhpSpec\Guard\Delta;
use PhpSpec\Guard\RecordedChanges;

// Two readers that answer with what they were built with, so an example can say
// which of them was asked without stubbing the same interface twice.
final class RecordedChangesSpecReader implements Changes
{
    public function __construct(private readonly Delta $delta) {}

    public function since(array $baseline): Delta
    {
        return $this->delta;
    }
}

describe(RecordedChanges::class, function () {

    $reading = fn(string $file) => new RecordedChangesSpecReader(Delta::of([$file => [12]]));

    it('reads a commit baseline with the reader that understands commits', function () use ($reading) {
        $changes = new RecordedChanges($reading('src/FromCommit.php'), $reading('src/FromSnapshot.php'));

        expect($changes->since(['kind' => 'commit', 'commit' => 'abc123'])->files())->toBe(['src/FromCommit.php']);
    });

    // The case that failed a run over legacy code: a project asking for mtime
    // detection in a git repository must not have its baseline read as a commit.
    it('reads a snapshot baseline with the reader that understands snapshots', function () use ($reading) {
        $changes = new RecordedChanges($reading('src/FromCommit.php'), $reading('src/FromSnapshot.php'));

        expect($changes->since(['kind' => 'snapshot', 'files' => []])->files())->toBe(['src/FromSnapshot.php']);
    });

});
