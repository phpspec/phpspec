<?php

use PhpSpec\Coverage\Recordings;

describe(Recordings::class, function () {
    this('sources', fn () => [
        '/project/src/Repo.php' => <<<'PHP'
            <?php

            final class Repo
            {
                public function filter(string $id): array
                {
                    return $this->with([
                        'id' => $id,
                        'status' => Status::ENABLED,
                    ]);
                }

                public function name(): string
                {
                    return 'repo';
                }

                public function other(): string { return 'other'; } public string $text { get {
                    return 'text';
                } }
            }

            if (getenv('NEVER') !== false) {
                echo 'never';
            }
            PHP,
    ]);

    this('recordings', fn () => function (array ...$recorded): array {
        $recordings = new Recordings(fn (string $file): ?string => $this->sources[$file] ?? null);

        foreach ($recorded as $recording) {
            $recordings->add($recording);
        }

        return $recordings->merged();
    });

    it('takes a line one process left out of a function it reported for no code, though another compiled it and never ran it', function () {
        $folded = ['/project/src/Repo.php' => [7 => 1, 8 => 1, 10 => 1, 15 => -1]];
        $unfolded = ['/project/src/Repo.php' => [7 => -1, 8 => -1, 9 => -1, 10 => -1, 15 => 1]];

        expect(($this->recordings)($folded, $unfolded)['/project/src/Repo.php'])->toBe([7 => 1, 8 => 1, 10 => 1, 15 => 1, 9 => -2]);
    });

    it('gives the same answer whatever order the processes come in', function () {
        $folded = ['/project/src/Repo.php' => [7 => 1, 8 => 1, 10 => 1, 15 => -1]];
        $unfolded = ['/project/src/Repo.php' => [7 => -1, 8 => -1, 9 => -1, 10 => -1, 15 => 1]];
        $sorted = function (array $merged): array {
            ksort($merged['/project/src/Repo.php']);

            return $merged;
        };

        expect($sorted(($this->recordings)($unfolded, $folded)))->toBe($sorted(($this->recordings)($folded, $unfolded)));
    });

    it('keeps a line missed where the process that left it out reported nothing of its function', function () {
        $ran = ['/project/src/Repo.php' => [7 => -1, 8 => -1, 9 => -1, 10 => -1, 15 => 1]];
        $other = ['/project/src/Repo.php' => [15 => -1, 18 => 1]];

        expect(($this->recordings)($ran, $other)['/project/src/Repo.php'][9])->toBe(-1);
    });

    it('takes no line two units share for a sign that a process reached either of them', function () {
        $hooked = ['/project/src/Repo.php' => [18 => -1, 19 => -1]];
        $method = ['/project/src/Repo.php' => [18 => 1]];

        expect(($this->recordings)($hooked, $method)['/project/src/Repo.php'][19])->toBe(-1);
    });

    it('never takes the file\'s own code for no code', function () {
        $compiledWhileRecording = ['/project/src/Repo.php' => [23 => 1, 24 => -1]];
        $compiledBefore = ['/project/src/Repo.php' => [23 => 1, 15 => 1]];

        expect(($this->recordings)($compiledWhileRecording, $compiledBefore)['/project/src/Repo.php'][24])->toBe(-1);
    });

    it('keeps a line any process ran covered, and a file one process recorded as it recorded it', function () {
        $ran = ['/project/src/Repo.php' => [9 => 1], '/project/src/Only.php' => [3 => -1]];
        $left = ['/project/src/Repo.php' => [7 => -1, 8 => -1, 10 => -1]];

        $merged = ($this->recordings)($ran, $left);

        expect($merged['/project/src/Repo.php'][9])->toBe(1);
        expect($merged['/project/src/Only.php'])->toBe([3 => -1]);
    });

    it('merges a single process as it recorded', function () {
        expect(($this->recordings)(['/project/src/Repo.php' => [7 => 1, 9 => -1]]))->toBe(['/project/src/Repo.php' => [7 => 1, 9 => -1]]);
    });
});
