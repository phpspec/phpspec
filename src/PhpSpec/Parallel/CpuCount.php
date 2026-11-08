<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Parallel;

use Closure;

/**
 * @internal
 * How many workers a parallel run starts when it is not told: the CPUs the
 * machine has, or fewer when a control group (a container's --cpus) limits
 * this process to fewer, as a CI runner on a large host does.
 */
final readonly class CpuCount
{
    /** The count when the machine does not say. */
    private const UNKNOWN = 4;

    /**
     * @param string $cgroups where the control group files are mounted
     * @param (Closure(string): ?string)|null $answer runs a command and answers what it printed, null when it could not
     * @param array<string, string>|null $environment the environment variables, this process's when null
     */
    public function __construct(
        private string $cgroups = '/sys/fs/cgroup',
        private string $os = PHP_OS_FAMILY,
        private ?Closure $answer = null,
        private ?array $environment = null,
    ) {}

    public function count(): int
    {
        $machine = $this->machine() ?? self::UNKNOWN;
        $limit = $this->limit();

        return max(1, $limit === null ? $machine : min($limit, $machine));
    }

    /**
     * The CPUs a control group's quota allows, rounded up: cgroup v2's
     * cpu.max, else cgroup v1's quota and period; none when there is no quota.
     */
    private function limit(): ?int
    {
        $v2 = $this->read('cpu.max');
        if ($v2 !== null) {
            [$quota, $period] = array_pad(preg_split('/\s+/', $v2) ?: [], 2, '');

            return self::cpus($quota, $period);
        }

        $quota = $this->read('cpu/cpu.cfs_quota_us');
        $period = $this->read('cpu/cpu.cfs_period_us');

        return $quota === null || $period === null ? null : self::cpus($quota, $period);
    }

    private static function cpus(string $quota, string $period): ?int
    {
        if (!ctype_digit($quota) || !ctype_digit($period) || (int) $period === 0 || (int) $quota === 0) {
            return null;
        }

        return (int) ceil((int) $quota / (int) $period);
    }

    private function machine(): ?int
    {
        $count = match ($this->os) {
            'Darwin' => $this->answered('sysctl -n hw.ncpu'),
            'Windows' => ($this->environment ?? getenv())['NUMBER_OF_PROCESSORS'] ?? null,
            default => $this->answered('nproc'),
        };

        $count = trim((string) $count);

        return ctype_digit($count) && (int) $count > 0 ? (int) $count : null;
    }

    private function answered(string $command): ?string
    {
        $answer = $this->answer ?? static function (string $command): ?string {
            $printed = shell_exec($command . ' 2>/dev/null');

            return is_string($printed) ? $printed : null;
        };

        return $answer($command);
    }

    private function read(string $file): ?string
    {
        $path = $this->cgroups . '/' . $file;

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : trim($content);
    }
}
