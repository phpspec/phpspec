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
 * Where a project keeps its features and its steps on disk. How steps are
 * grouped into files is the project's choice, so no steps file belongs to a
 * feature here.
 */
final class FeatureLayout
{
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
