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
<?php foreach ($specification->getResults() as $exampleResult): ?>
<?php if ($exampleResult->isContext() && $exampleResult->isError() === false): ?>
<?php $this->render('context.errors', [
    'context' => $exampleResult,
    'specification' => $specification->getTitle() . ' > ' . $exampleResult->getTitle(),
]) ?>
<?php elseif ($exampleResult->isContext() && $exampleResult->isError()): ?>
<?php $this->write(PHP_EOL . '  <fg=red>• ' . $specification->getTitle() . ' > ' . $exampleResult->getTitle() . '</>' . PHP_EOL) ?>
<?php $this->write(PHP_EOL) ?>
<?php $this->write('  <fg=red>' . '</>' . $exampleResult->getError()->getType() . ': ' . $exampleResult->getError()->getMessage() . "\n\n") ?>
<?php $this->render('surroundingCode', [
    'surroundingCode' => $exampleResult->getError()->getSurroundingCode(),
    'errorLine' => $exampleResult->getError()->getLine(),
]) ?>
<?php $this->write(PHP_EOL . '  at ' . $exampleResult->getError()->getFile() . ':' . $exampleResult->getError()->getLine() . PHP_EOL) ?>
<?php else : ?>
<?php $this->render('example.errors', [
    'example' => $exampleResult,
    'specification' => $specification->getTitle(),
]) ?>
<?php endif ?>
<?php endforeach ?>
