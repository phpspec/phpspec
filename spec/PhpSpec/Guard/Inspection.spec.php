<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Baseline;
use PhpSpec\Guard\CannotJudge;
use PhpSpec\Guard\Changes;
use PhpSpec\Guard\Coverage;
use PhpSpec\Guard\Delta;
use PhpSpec\Guard\Git;
use PhpSpec\Guard\Guard;
use PhpSpec\Guard\Inspection;

describe(Inspection::class, function () {

    beforeEach(function () {
        $this->exercised = Coverage::fromHits(['/app/src/Basket.php' => [7 => 1]], '/app');
        $this->recorded = '{"recorded":{"kind":"commit","commit":"abc123"}}';
    });

    // The state of every fresh clone, because the baseline is local by design.
    // Answering "clean" here is how a team ends up believing it is guarded
    // when nobody is.
    it('refuses to judge a checkout that recorded no baseline', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(false);
        $inspection = new Inspection(new Baseline($fs, $git, '/app'), new FakeChanges(Delta::nothing()), new Guard($fs, '/app'));

        $verdict = $inspection->judge($this->exercised);

        expect($verdict->judged())->toBeFalse();
        expect($verdict->reason())->toContain('no baseline is recorded in this checkout');
    });

    it('refuses to judge a run that collected no coverage', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn($this->recorded);
        $inspection = new Inspection(new Baseline($fs, $git, '/app'), new FakeChanges(Delta::nothing()), new Guard($fs, '/app'));

        $verdict = $inspection->judge(Coverage::nothing());

        expect($verdict->judged())->toBeFalse();
        expect($verdict->reason())->toContain('no coverage was collected');
    });

    // A shallow clone, or a commit that has been rebased away: git cannot
    // answer for it, and a delta of nothing would read as a change of nothing.
    it('refuses to judge against a baseline the reader cannot reach', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn($this->recorded);
        $changes = new FakeChanges(null, 'Guard cannot compare against the recorded commit.');
        $inspection = new Inspection(new Baseline($fs, $git, '/app'), $changes, new Guard($fs, '/app'));

        $verdict = $inspection->judge($this->exercised);

        expect($verdict->judged())->toBeFalse();
        expect($verdict->reason())->toBe('Guard cannot compare against the recorded commit.');
    });

    it('judges what changed once it has a baseline and coverage', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn($this->recorded);
        $inspection = new Inspection(new Baseline($fs, $git, '/app'), new FakeChanges(Delta::nothing()), new Guard($fs, '/app'));

        $verdict = $inspection->judge($this->exercised);

        expect($verdict->judged())->toBeTrue();
        expect($verdict->held())->toBeTrue();
    });

    it('judges against a commit named on the command line without reading a baseline', function (Filesystem $fs, Git $git) {
        allow($fs->exists())->toReturn(false);
        $inspection = new Inspection(new Baseline($fs, $git, '/app'), new FakeChanges(Delta::nothing()), new Guard($fs, '/app'));

        $verdict = $inspection->judgeAgainst('origin/main', $this->exercised);

        expect($verdict->judged())->toBeTrue();
        expect($verdict->held())->toBeTrue();
    });

});

/**
 * A reader that answers with what it was given. Written out rather than
 * doubled: Changes returns a final class, which a double cannot stand in for.
 */
final readonly class FakeChanges implements Changes
{
    public function __construct(
        private ?Delta $delta,
        private ?string $refusal = null,
    ) {}

    public function since(array $baseline): Delta
    {
        if ($this->refusal !== null) {
            throw new CannotJudge($this->refusal);
        }

        return $this->delta ?? Delta::nothing();
    }
}
