<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Extension;

use InvalidArgumentException;
use RuntimeException;

trait ValidatingExtensionTrait
{
    /**
     * @var \PhpSpec\ServiceContainer
     */
    private $container;

    /**
     * @var array
     */
    private $map = [
        'matchers' => 'PhpSpec\Matcher\MatcherInterface',
    ];

    /**
     * @param \PhpSpec\ServiceContainer $container
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }

    /**
     * @param string          $id
     * @param object|callable $value
     *
     * @throws \InvalidArgumentException if service does not implement correct interface
     */
    public function set($id, $value)
    {
        if (!isset($this->container)) {
            throw new RuntimeException(sprintf(
                'ValidatingExtensionTrait::setContainer() call required.',
                $id
            ));
        }

        $this->validate($id, $value);
        $this->container->set($id, $value);
    }

    /**
     * Validates a service and then calls parent set() method.
     *
     * @param string          $id
     * @param object|callable $value
     *
     * @throws \InvalidArgumentException if service does not implement appropriate interface
     */
    private function validate($id, $value)
    {
        $prefix = $this->getPrefixAndSid($id)[0];
        if (null === $prefix) {
            return true;
        }

        if (in_array($prefix, array_keys($this->map))) {
            if (is_callable($value)) {
                $tmpObj = call_user_func($value, $this->container);
                if (!($tmpObj instanceof $this->map[$prefix])) {
                    throw new InvalidArgumentException(sprintf(
                        'Callable service "%s" has to implement correct interface.',
                        $id
                    ));
                };
            } elseif (is_object($value)) {
                if (!($value instanceof $this->map[$prefix])) {
                    throw new InvalidArgumentException(sprintf(
                        'Service "%s" has to implement correct interface.',
                        $id
                    ));
                }
            }
        }

        return true;
    }

    /**
     * Retrieves the prefix and sid of a given service
     *
     * Copied from PhpSpec\ServiceContainer.
     *
     * @param string $id
     *
     * @return array
     */
    private function getPrefixAndSid($id)
    {
        if (count($parts = explode('.', $id)) < 2) {
            return array(null, $id);
        }

        $sid    = array_pop($parts);
        $prefix = implode('.', $parts);

        return array($prefix, $sid);
    }

}
