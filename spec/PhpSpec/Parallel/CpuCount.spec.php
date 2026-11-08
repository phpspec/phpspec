<?php

use PhpSpec\Parallel\CpuCount;
use PhpSpec\TemporaryDirectory;

describe(CpuCount::class, function () {

    $made = [];

    afterAll(function () use (&$made) {
        foreach ($made as $directory) {
            $directory->remove();
        }
    });

    this('cgroups', function () use (&$made) {
        return function (array $files) use (&$made): string {
            $directory = TemporaryDirectory::create('phpspec_cpucount_spec_');
            $made[] = $directory;

            foreach ($files as $path => $content) {
                if (!is_dir(dirname($directory->path . '/' . $path))) {
                    mkdir(dirname($directory->path . '/' . $path), 0777, true);
                }
                file_put_contents($directory->path . '/' . $path, $content);
            }

            return $directory->path;
        };
    });

    this('answering', fn() => fn(?string $printed) => fn(string $command): ?string => $printed);

    it('is the count the machine reports when no control group limits it', function () {
        expect((new CpuCount(($this->cgroups)([]), 'Linux', ($this->answering)("8\n")))->count())->toBe(8);
    });

    it('is the quota a control group gives, rounded up, never more than the machine has', function () {
        $answering = ($this->answering)('64');

        expect((new CpuCount(($this->cgroups)(['cpu.max' => "150000 100000\n"]), 'Linux', $answering))->count())->toBe(2);
        expect((new CpuCount(($this->cgroups)(['cpu.max' => "max 100000\n"]), 'Linux', $answering))->count())->toBe(64);
        expect((new CpuCount(($this->cgroups)(['cpu/cpu.cfs_quota_us' => "200000\n", 'cpu/cpu.cfs_period_us' => "100000\n"]), 'Linux', $answering))->count())->toBe(2);
        expect((new CpuCount(($this->cgroups)(['cpu/cpu.cfs_quota_us' => "-1\n", 'cpu/cpu.cfs_period_us' => "100000\n"]), 'Linux', $answering))->count())->toBe(64);
        expect((new CpuCount(($this->cgroups)(['cpu.max' => "1600000 100000\n"]), 'Linux', ($this->answering)('4')))->count())->toBe(4);
    });

    it('asks macOS by sysctl, and reads Windows from its environment', function () {
        $asked = [];
        $answering = function (string $command) use (&$asked): string {
            $asked[] = $command;

            return '10';
        };

        expect((new CpuCount(($this->cgroups)([]), 'Darwin', $answering))->count())->toBe(10);
        expect($asked)->toBe(['sysctl -n hw.ncpu']);
        expect((new CpuCount(($this->cgroups)([]), 'Windows', $answering, ['NUMBER_OF_PROCESSORS' => '12']))->count())->toBe(12);
    });

    it('is four when the machine does not say', function () {
        expect((new CpuCount(($this->cgroups)([]), 'Linux', ($this->answering)(null)))->count())->toBe(4);
        expect((new CpuCount(($this->cgroups)([]), 'Linux', ($this->answering)("nproc: not found\n")))->count())->toBe(4);
    });
});
