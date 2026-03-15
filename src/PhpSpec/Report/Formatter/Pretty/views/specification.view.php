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
<?php $this->write($specification->getTitle()) ?>
<?php foreach ($specification->getResults() as $exampleResult): ?>
<?php if ($exampleResult->isContext()): ?>
<?php $this->render('context', [
    'context' => $exampleResult,
    'indentation' => 2,
    'verbose' => $verbose ?? false,
]) ?>
<?php else : ?>
<?php $this->render('example', [
    'example' => $exampleResult,
    'indentation' => 2,
    'verbose' => $verbose ?? false,
]) ?>
<?php endif ?>
<?php endforeach ?>
