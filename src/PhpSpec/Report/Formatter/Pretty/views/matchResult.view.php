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
<?php $this->write('Failure: ' . $matchResult->getMessage() . PHP_EOL . PHP_EOL) ?>
<?php
$expected = $matchResult->getExpected();
$actual = $matchResult->getActual();
if ($expected !== null || $actual !== null):
    $format = function ($value) {
        if (is_string($value)) {
            return '"' . $value . '"';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_array($value)) {
            return var_export($value, true);
        }
        if (is_object($value)) {
            return get_class($value) . '#' . spl_object_id($value);
        }
        return (string) $value;
    };
    $this->write('  <fg=red>  expected: ' . $format($actual) . '</>' . PHP_EOL);
    $this->write('  <fg=green>       got: ' . $format($expected) . '</>' . PHP_EOL . PHP_EOL);
endif;
?>
<?php $this->render('surroundingCode', [
    'surroundingCode' => $matchResult->getCode(),
    'errorLine' => $matchResult->getLine(),
]) ?>
<?php $this->write(PHP_EOL . '  at ' . $matchResult->getFile() . ':' . $matchResult->getLine() . PHP_EOL) ?>
