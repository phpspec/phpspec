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
<?php $this->write(PHP_EOL . str_repeat(' ', $indentation)) ?><?php $this->write($context->getTitle()) ?>
<?php foreach ($context->getResults() as $example): ?>
<?php if ($example->isContext()): ?>
<?php $this->render('context', [
    'context' => $example,
    'indentation' => $indentation + 2,
    'verbose' => $verbose ?? false,
]) ?>
<?php else : ?>
<?php $this->render('example', [
    'example' => $example,
    'indentation' => $indentation + 2,
    'verbose' => $verbose ?? false,
]) ?>
<?php endif ?>
<?php endforeach ?>
