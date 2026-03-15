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
<?php $this->write('<options=bold>Feature: ' . $feature->getTitle() . '</>') ?>
<?php foreach ($feature->getResults() as $scenarioResult): ?>
<?php $this->render('scenario', ['scenario' => $scenarioResult]) ?>
<?php endforeach ?>
