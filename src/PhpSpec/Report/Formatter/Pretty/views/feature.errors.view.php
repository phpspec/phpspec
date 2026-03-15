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
<?php foreach ($feature->getResults() as $scenario): ?>
<?php foreach ($scenario->getResults() as $step): ?>
<?php if ($step->isFailure() && $step->getError()): ?>
<?php $this->write(PHP_EOL . '  <fg=red>• ' . $feature->getTitle() . ' > ' . $scenario->getTitle() . ' > ' . $step->getTitle() . '</>' . PHP_EOL) ?>
<?php $this->write(PHP_EOL . '  ' . $step->getError()->getMessage() . PHP_EOL) ?>
<?php endif ?>
<?php endforeach ?>
<?php endforeach ?>
