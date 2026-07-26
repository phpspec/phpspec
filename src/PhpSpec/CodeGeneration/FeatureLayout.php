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

namespace PhpSpec\CodeGeneration;

/**
 * @internal
 * The one home of the steps-beside-feature layout convention
 * (`<feature dir>/steps/<name>.steps.php`): where a feature's step
 * definitions live, and the way back.
 */
final class FeatureLayout
{
    /**
     * The steps file a feature's definitions live in, beside the feature
     * under its own steps directory.
     */
    public function stepsPathFor(string $featurePath): string
    {
        return dirname($featurePath) . '/steps/' . basename($featurePath, '.feature') . '.steps.php';
    }

    /**
     * The feature a steps file belongs to, inverting {@see stepsPathFor()}.
     */
    public function featurePathFor(string $stepsPath): string
    {
        return dirname($stepsPath, 2) . '/' . basename($stepsPath, '.steps.php') . '.feature';
    }
}
