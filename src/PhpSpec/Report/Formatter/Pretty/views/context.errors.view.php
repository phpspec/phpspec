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
<?php foreach ($context->getResults() as $example): ?>
<?php if ($example->isContext()): ?>
<?php $this->render('context.errors', [
    'context' => $example,
    'specification' => $specification . ' > ' . $example->getTitle(),
]) ?>
<?php else : ?>
<?php $this->render('example.errors', [
    'example' => $example,
    'specification' => $specification,
]) ?>
<?php endif ?>
<?php endforeach ?>
