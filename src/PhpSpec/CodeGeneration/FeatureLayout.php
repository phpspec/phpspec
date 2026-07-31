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

use PhpSpec\Filesystem;

/**
 * @internal
 * The one home of the steps-beside-feature layout convention
 * (`<feature dir>/steps/<name>.steps.php`): where a feature's step
 * definitions live, the way back, and where a project keeps its feature
 * and steps roots.
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

    /**
     * The project's feature and steps roots, resolved from the layout on disk:
     * features live under `features/scenarios` when that subdirectory exists
     * (plain `features` otherwise), and steps under `features/steps` unless the
     * scenarios directory keeps its own `steps/`.
     *
     * @return array{features: string, steps: string} relative paths
     */
    public function roots(Filesystem $filesystem): array
    {
        $base = getcwd() . '/features';

        $featuresPath = 'features/scenarios';
        $stepsPath = 'features/steps';

        if ($filesystem->exists($base) && $filesystem->isDir($base)) {
            $entries = $filesystem->scandir($base);

            if (!in_array('scenarios', $entries)) {
                $featuresPath = 'features';
            }

            if (!in_array('steps', $entries)) {
                $scenariosSteps = $base . '/scenarios/steps';
                if ($filesystem->exists($scenariosSteps) && $filesystem->isDir($scenariosSteps)) {
                    $stepsPath = 'features/scenarios/steps';
                }
            }
        }

        return ['features' => $featuresPath, 'steps' => $stepsPath];
    }
}
