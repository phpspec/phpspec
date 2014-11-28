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

namespace PhpSpec;

use Behat\Testwork\ServiceContainer\ContainerLoader;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * The Service Container is a lightweight container based on Pimple to handle
 * object creation of PhpSpec services.
 */
class ServiceContainer
{
    const COMPAT_SERVICE_FACTORY_ID = 'compat.service-factory';
    private $serviceFactory;
    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;
    private $params = array();
    private $defaultExtensions = array();

    /**
     * @var array
     */
    private $configurators = array();

    public function __construct($container)
    {
        $container = new ContainerBuilder();
        $container->setParameter('paths.base', __DIR__);

        $this->containerBuilder = $container;
        $definition = new Definition('PhpSpec\\ServiceFactory');
        $this->containerBuilder->setDefinition(self::COMPAT_SERVICE_FACTORY_ID, $definition);
        $this->serviceFactory = $this->containerBuilder->get(self::COMPAT_SERVICE_FACTORY_ID);
        $this->serviceFactory->setContainer($this);
    }

    /**
     * Sets a param in the container
     *
     * @param string $id
     * @param mixed  $value
     */
    public function setParam($id, $value)
    {
        $this->containerBuilder->setParameter($id, $value);
        $this->params[$id] = $value;
    }

    public function processParams()
    {
        foreach ($this->params as $id => $value) {
            $this->containerBuilder->setParameter($id, $value);
        }
    }

    /**
     * Gets a param from the container or a default value.
     *
     * @param string $id
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getParam($id, $default = null)
    {
        if (!$this->containerBuilder->hasParameter($id)) {
            return $default;
        }

        $value = $this->containerBuilder->getParameter($id);

        if (isset($value)) {
            return $value;
        }

        return $default;
    }

    /**
     * Retrieves a service from the container
     *
     * @param string $id
     *
     * @return object
     *
     * @throws \InvalidArgumentException if service is not defined
     */
    public function get($id)
    {
        if (!$this->containerBuilder->has($id)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not defined.', $id));
        }

        return $this->containerBuilder->get($id);
    }

    /**
     * @param $id
     * @return bool
     */
    public function isDefined($id)
    {
        return $this->containerBuilder->has($id);
    }

    /**
     * Retrieves a list of services of a given prefix
     *
     * @param string $prefix
     *
     * @return array
     */
    public function getByPrefix($prefix)
    {
        return array_map(function($id) {return $this->containerBuilder->get($id);}, array_keys($this->containerBuilder->findTaggedServiceIds($prefix)));
    }

    /**
     * Removes a service from the container
     *
     * @param string $id
     *
     * @throws \InvalidArgumentException if service is not defined
     */
    public function remove($id)
    {
        if (!$this->containerBuilder->has($id)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not defined.', $id));
        }

        $this->containerBuilder->removeDefinition($id);
    }

    /**
     * Sets a object or a callable for the object creation. A callable will be invoked
     * every time get is called.
     *
     * @param string          $id
     * @param object|callable $value
     *
     * @throws \InvalidArgumentException if service is not an object or callable
     */
    public function set($id, $value)
    {
        $definition = $this->createDefinition($id, $value);
        $definition->setScope('prototype');

        $this->containerBuilder->setDefinition($id, $definition);
    }

    /**
     * Sets a callable for the object creation. The same object will
     * be returned every time
     *
     * @param string   $id
     * @param callable $callable
     *
     * @throws \InvalidArgumentException if service is not a callable
     */
    public function setShared($id, $callable)
    {
        $definition = $this->createDefinition($id, $callable);

        $this->containerBuilder->setDefinition($id, $definition);
    }

    /**
     * Adds a configurator, that can configure many services in one callable
     *
     * @param callable $configurator
     *
     * @throws \InvalidArgumentException if configurator is not a callable
     */
    public function addConfigurator($configurator)
    {
        if (!is_callable($configurator)) {
            throw new InvalidArgumentException(sprintf(
                'Configurator should be callable, but %s given.', gettype($configurator)
            ));
        }

        $this->configurators[] = $configurator;
    }

    /**
     * Loop through all configurators and invoke them
     */
    public function compile()
    {
        $this->addDefaultExtension(new LegacyExtension($this));
        $containerLoader = new ContainerLoader(new ExtensionManager($this->defaultExtensions));
        $containerLoader->load($this->containerBuilder, array());
        $this->containerBuilder->addObjectResource($containerLoader);
        $this->containerBuilder->compile();
    }

    /**
     * Retrieves the prefix and sid of a given service
     *
     * @param string $id
     *
     * @return array
     */
    private function getPrefix($id)
    {
        if (count($parts = explode('.', $id)) < 2) {
            return null;
        }

        array_pop($parts);
        $prefix = implode('.', $parts);

        return $prefix;
    }

    /**
     * @param $id
     * @param $value
     * @return Definition
     * @throws \InvalidArgumentException
     */
    private function createDefinition($id, $value)
    {
        if (!is_object($value) && !is_callable($value)) {
            throw new InvalidArgumentException(sprintf(
                'Service should be callable or object, but %s given.', gettype($value)
            ));
        }

        $definition = new Definition('\stdClass');
        $this->serviceFactory->setService($id, $value);

        $definition->setFactoryService(self::COMPAT_SERVICE_FACTORY_ID);
        $definition->setFactoryMethod('create');
        $definition->addArgument($id);
        $definition->addTag($this->getPrefix($id));

        return $definition;
    }

    public function configure()
    {
        foreach ($this->configurators as $configurator) {
            call_user_func($configurator, $this);
        }
    }

    public function addDefaultExtension(Extension $extension)
    {
        $this->defaultExtensions[] = $extension;
    }
}
