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
<?php if ($example->isError()): ?>
<?php $this->write(PHP_EOL . '  <fg=red>• ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL) ?>
<?php $this->write(PHP_EOL . '  <fg=red>' . '</>' . 'Error: ' . $example->getError()->getMessage() . PHP_EOL . PHP_EOL) ?>
<?php $this->render('surroundingCode', [
    'surroundingCode' => $example->getError()->getSurroundingCode(),
    'errorLine' => $example->getError()->getLine(),
]) ?>
<?php $this->write(PHP_EOL . '  at ' . $example->getError()->getFile() . ':' . $example->getError()->getLine() . PHP_EOL) ?>
<?php $trace = $example->getError()->getFilteredTrace();
    if (!empty($trace)): ?>
<?php foreach (array_slice($trace, 0, 5) as $frame): ?>
<?php $this->write('     ' . ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?') . PHP_EOL) ?>
<?php endforeach ?>
<?php endif ?>
<?php elseif ($example->isFailure()): ?>
<?php $this->write(str_repeat(' ', 2)) ?>
<?php $this->write(PHP_EOL . '  <fg=red>• ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL) ?>
<?php $this->write(PHP_EOL) ?>
<?php foreach ($example->getResults() as $matchResult): ?>
<?php $this->write(str_repeat(' ', 2)) ?><?php $this->render('matchResult', [
        'matchResult' => $matchResult,
        'indentation' => 2 + 4,
    ]) ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($example->hasWarnings()): ?>
<?php foreach ($example->getWarnings() as $warning): ?>
<?php $this->write(PHP_EOL . '  <fg=yellow>⚠️ ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL) ?>
<?php $this->write(PHP_EOL . '  Warning: ' . $warning['message'] . PHP_EOL . PHP_EOL) ?>
<?php $this->render('surroundingCode', [
        'surroundingCode' => (new \PhpSpec\CodeGeneration\SurroundingCode($warning['file'], $warning['line']))->toArray(),
        'errorLine' => $warning['line'],
    ]) ?>
<?php $this->write(PHP_EOL . '  at ' . $warning['file'] . ':' . $warning['line'] . PHP_EOL) ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($example->hasDeprecations()): ?>
<?php foreach ($example->getDeprecations() as $deprecation): ?>
<?php $this->write(PHP_EOL . '  <fg=yellow>⚠️ ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL) ?>
<?php $this->write(PHP_EOL . '  Deprecated: ' . $deprecation['message'] . PHP_EOL . PHP_EOL) ?>
<?php $this->render('surroundingCode', [
        'surroundingCode' => (new \PhpSpec\CodeGeneration\SurroundingCode($deprecation['file'], $deprecation['line']))->toArray(),
        'errorLine' => $deprecation['line'],
    ]) ?>
<?php $this->write(PHP_EOL . '  at ' . $deprecation['file'] . ':' . $deprecation['line'] . PHP_EOL) ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($example->hasNotices()): ?>
<?php foreach ($example->getNotices() as $notice): ?>
<?php $this->write(PHP_EOL . '  <fg=yellow>⚠️ ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL) ?>
<?php $this->write(PHP_EOL . '  Notice: ' . $notice['message'] . PHP_EOL . PHP_EOL) ?>
<?php $this->render('surroundingCode', [
        'surroundingCode' => (new \PhpSpec\CodeGeneration\SurroundingCode($notice['file'], $notice['line']))->toArray(),
        'errorLine' => $notice['line'],
    ]) ?>
<?php $this->write(PHP_EOL . '  at ' . $notice['file'] . ':' . $notice['line'] . PHP_EOL) ?>
<?php endforeach ?>
<?php endif ?>
