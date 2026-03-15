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
<?php if ($example->isPending()): ?>
<?php $this->write(str_repeat(' ', $indentation)) ?><?php $this->write(
    '<fg=yellow>-</> <fg=gray>' . $example->getTitle() . ' (pending)</>',
) ?>
<?php elseif ($example->isSkipped()): ?>
<?php $this->write(str_repeat(' ', $indentation)) ?><?php $this->write(
    '<fg=cyan>-</> <fg=gray>' . $example->getTitle() . ' (skipped)</>',
) ?>
<?php elseif ($example->isError()): ?>
<?php $this->write(str_repeat(' ', $indentation)) ?><?php $this->write(
    '<fg=red>✘</> <fg=gray>' . $example->getTitle() . '</>',
) ?>
<?php elseif ($example->isFailure()): ?>
<?php $this->write(str_repeat(' ', $indentation)) ?><?php $this->write(
    '<fg=red>✘</> <fg=gray>' . $example->getTitle() . '</>',
) ?>
<?php else : ?>
<?php $this->write(str_repeat(' ', $indentation)) ?><?php $this->write(
    '<info>' . '√ ' . '</info><fg=gray>' . $example->getTitle() . '</>',
) ?>
<?php if (!empty($verbose) && $example->getDuration() > 0): ?>
<?php $this->write(' <fg=gray>(' . number_format($example->getDuration() * 1000, 1) . 'ms)</>') ?>
<?php endif ?>
<?php endif ?>
<?php if ($example->hasWarnings()): ?>
<?php foreach ($example->getWarnings() as $warning): ?>
<?php $this->write(PHP_EOL . str_repeat(' ', $indentation) . '  <fg=yellow>⚠️ ' . $warning['message'] . ' — at ' . basename($warning['file']) . ':' . $warning['line'] . '</>') ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($example->hasDeprecations()): ?>
<?php foreach ($example->getDeprecations() as $deprecation): ?>
<?php $this->write(PHP_EOL . str_repeat(' ', $indentation) . '  <fg=yellow>⛔ ' . $deprecation['message'] . ' — at ' . basename($deprecation['file']) . ':' . $deprecation['line'] . '</>') ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($example->hasNotices()): ?>
<?php foreach ($example->getNotices() as $notice): ?>
<?php $this->write(PHP_EOL . str_repeat(' ', $indentation) . '  <fg=yellow>ℹ ' . $notice['message'] . ' — at ' . basename($notice['file']) . ':' . $notice['line'] . '</>') ?>
<?php endforeach ?>
<?php endif ?>
