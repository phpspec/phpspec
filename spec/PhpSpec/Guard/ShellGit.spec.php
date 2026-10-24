<?php

use PhpSpec\Guard\ShellGit;

describe(ShellGit::class, function () {
    $git = function (string $dir, string ...$arguments): void {
        $process = proc_open(['git', '-c', 'user.name=Spec', '-c', 'user.email=spec@example.com', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        proc_close($process);
    };

    $remove = function (string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
                $remove($path . '/' . $entry);
            }

            rmdir($path);

            return;
        }

        @chmod($path, 0666);
        unlink($path);
    };

    beforeEach(function () use ($git) {
        $this->dir = sys_get_temp_dir() . '/phpspec-shellgit-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/src', 0777, true);
        file_put_contents($this->dir . '/src/Café.php', "<?php\n\nreturn 1;\n");
        $git($this->dir, 'init', '-q');
        $git($this->dir, 'add', '.');
        $git($this->dir, 'commit', '-q', '-m', 'Café');
    });

    afterEach(fn () => $remove($this->dir));

    it('answers from the repository it works in', function () {
        $shell = new ShellGit($this->dir);

        expect($shell->isRepository())->toBeTrue();
        expect($shell->head())->toMatch('/^[0-9a-f]{40}$/');
    });

    it('gives the new lines since a commit, and the files it does not track, as they are named on disk', function () {
        $shell = new ShellGit($this->dir);
        $head = (string) $shell->head();
        file_put_contents($this->dir . '/src/Café.php', "<?php\n\nreturn 2;\n");
        file_put_contents($this->dir . '/src/Thé.php', "<?php\n");

        expect($shell->diff($head, ['src']))->toContain("+++ b/src/Café.php\n@@ -3 +3 @@\n-return 1;\n+return 2;");
        expect($shell->untracked(['src']))->toBe(['src/Thé.php']);
    });

    it('has no head in a repository with no commit yet', function () use ($git, $remove) {
        $empty = $this->dir . '/../phpspec-shellgit-empty-' . bin2hex(random_bytes(4));
        mkdir($empty);
        $git($empty, 'init', '-q');

        try {
            expect((new ShellGit($empty))->head())->toBeNull();
        } finally {
            $remove($empty);
        }
    });

    it('answers as no repository where there is none, rather than failing', function () {
        $outside = $this->dir . '/../phpspec-shellgit-none-' . bin2hex(random_bytes(4));
        mkdir($outside);

        try {
            $shell = new ShellGit($outside);

            expect($shell->isRepository())->toBeFalse();
            expect($shell->head())->toBeNull();
            expect($shell->diff('HEAD', ['src']))->toBe('');
            expect($shell->untracked(['src']))->toBe([]);
        } finally {
            rmdir($outside);
        }
    });
});
