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
?>
<?php if (!empty($counts['features'])): ?>
<?php $this->write($counts['features'] . ' feature' . ($counts['features'] != 1 ? 's' : '') . ', ') ?>
<?php $this->write($counts['scenarios'] . ' scenario' . ($counts['scenarios'] != 1 ? 's' : '') . ', ') ?>
<?php $this->write($counts['steps'] . ' step' . ($counts['steps'] != 1 ? 's' : '') . ' (') ?>
<?php $parts = [] ?>
<?php if ($counts['stepPasses']) {
    $parts[] = '<fg=green>' . $counts['stepPasses'] . ' passed</>';
} ?>
<?php if ($counts['stepFailures']) {
    $parts[] = '<fg=red>' . $counts['stepFailures'] . ' failed</>';
} ?>
<?php if ($counts['pending']) {
    $parts[] = '<fg=yellow>' . $counts['pending'] . ' pending</>';
} ?>
<?php if ($counts['undefined']) {
    $parts[] = '<fg=bright-blue>' . $counts['undefined'] . ' undefined</>';
} ?>
<?php if ($counts['skipped']) {
    $parts[] = '<fg=cyan>' . $counts['skipped'] . ' skipped</>';
} ?>
<?php $this->write(implode(', ', $parts)) ?>
<?php $this->write(')' . PHP_EOL) ?>
<?php elseif (isset($counts['specs'])): ?>
<?php $this->write($counts['specs'] . ' spec' . ($counts['specs'] != 1 ? 's' : '') . PHP_EOL) ?>
<?php endif ?>
<?php if (isset($counts['examples']) && $counts['examples'] > 0) : ?>
<?php $exParts = [] ?>
<?php if ($counts['passes']) {
    $exParts[] = '<fg=green>' . $counts['passes'] . ' passes</>';
} ?>
<?php if ($counts['failures']) {
    $exParts[] = '<fg=red>' . $counts['failures'] . ' failures</>';
} ?>
<?php if ($counts['errors']) {
    $exParts[] = '<fg=red>' . $counts['errors'] . ' errors</>';
} ?>
<?php if ($counts['pending']) {
    $exParts[] = '<fg=yellow>' . $counts['pending'] . ' pending</>';
} ?>
<?php if ($counts['exampleSkipped']) {
    $exParts[] = '<fg=cyan>' . $counts['exampleSkipped'] . ' skipped</>';
} ?>
<?php if ($counts['warnings']) {
    $exParts[] = '<fg=yellow>' . $counts['warnings'] . ' warning' . ($counts['warnings'] != 1 ? 's' : '') . '</>';
} ?>
<?php if ($counts['deprecations']) {
    $exParts[] = '<fg=yellow>' . $counts['deprecations'] . ' deprecation' . ($counts['deprecations'] != 1 ? 's' : '') . '</>';
} ?>
<?php if ($counts['notices']) {
    $exParts[] = '<fg=yellow>' . $counts['notices'] . ' notice' . ($counts['notices'] != 1 ? 's' : '') . '</>';
} ?>
<?php $this->write($counts['examples'] . ' example' . ($counts['examples'] != 1 ? 's' : '') . ' (' . implode(', ', $exParts) . ')' . PHP_EOL) ?>
<?php endif ?>
<?php if (isset($duration) && $duration > 0) : ?>
<?php $this->write(sprintf('Finished in %.4f seconds' . PHP_EOL, $duration)) ?>
<?php endif ?>
