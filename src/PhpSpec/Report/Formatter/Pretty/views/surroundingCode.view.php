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
<?php
end($surroundingCode);
$decimalPlace = strlen((string) key($surroundingCode));
reset($surroundingCode);

foreach ($surroundingCode as $line => $code) {
    $indent = strlen((string) $line) < $decimalPlace ? ' ' : '';

    if ($errorLine === $line) {
        $this->write(" <fg=red>></> {$indent}<options=bold>$line</>  <fg=gray>|</> <fg=red>$code</>");
    } else {
        $this->write("   {$indent}<fg=gray>$line  |</> $code");
    }
}
?>
