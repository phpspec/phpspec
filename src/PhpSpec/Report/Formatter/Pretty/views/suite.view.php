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
<?php if ($suiteResults->isEmpty()): ?>
<?php $this->write('No specs found.' . PHP_EOL) ?>
<?php else : ?>
<?php $this->write('Once you spec, you never go back!' . PHP_EOL) ?>
<?php foreach ($suiteResults->getResults() as $specificationResult): ?>
<?php if ($specificationResult instanceof \PhpSpec\Result\FeatureResult): ?>
<?php $this->render('feature', ['feature' => $specificationResult]) ?>
<?php else: ?>
<?php $this->render('specification', ['specification' => $specificationResult, 'verbose' => $verbose]) ?>
<?php endif ?>
<?php $this->write(PHP_EOL) ?>
<?php endforeach ?>
<?php foreach ($suiteResults->getResults() as $specificationResult): ?>
<?php if ($specificationResult instanceof \PhpSpec\Result\FeatureResult): ?>
<?php $this->render('feature.errors', ['feature' => $specificationResult]) ?>
<?php else: ?>
<?php $this->render('specification.errors', ['specification' => $specificationResult]) ?>
<?php endif ?>
<?php endforeach ?>
<?php $this->write(PHP_EOL) ?>
<?php $this->render('counts', ['counts' => $counts, 'duration' => $suiteResults->getDuration()]) ?>
<?php endif ?>
