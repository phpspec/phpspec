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
<?php $this->write(PHP_EOL) ?>
<?php if ($step->isPassed()): ?>
<?php $this->write('    <fg=green>✓ ' . $step->getTitle() . '</>') ?>
<?php elseif ($step->isFailure()): ?>
<?php $this->write('    <fg=red>✗ ' . $step->getTitle() . '</>') ?>
<?php elseif ($step->isPending()): ?>
<?php $this->write('    <fg=yellow>○ ' . $step->getTitle() . '</>') ?>
<?php elseif ($step->isUndefined()): ?>
<?php $this->write('    <fg=bright-blue>? ' . $step->getTitle() . '</>') ?>
<?php elseif ($step->isSkipped()): ?>
<?php $this->write('    <fg=cyan>- ' . $step->getTitle() . '</>') ?>
<?php endif ?>
