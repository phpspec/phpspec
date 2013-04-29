<?php

namespace PhpSpec\Runner;

use PhpSpec\Exception\Wrapper\CollaboratorException;
use PhpSpec\Formatter\Presenter\PresenterInterface;

use ReflectionFunctionAbstract;

class CollaboratorManager
{
    private $presenter;
    private $collaborators = array();

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function set($name, $collaborator)
    {
        $this->collaborators[$name] = $collaborator;
    }

    public function has($name)
    {
        return isset($this->collaborators[$name]);
    }

    public function get($name)
    {
        if (!$this->has($name)) {
            throw new CollaboratorException(
                sprintf('Collaborator %s not found.', $this->presenter->presentString($name))
            );
        }

        return $this->collaborators[$name];
    }

    public function getArgumentsFor(ReflectionFunctionAbstract $function)
    {
        $parameters = array();
        foreach ($function->getParameters() as $parameter) {
            if ($this->has($parameter->getName())) {
                $parameters[] = $this->get($parameter->getName());
            } else {
                $parameters[] = null;
            }
        }

        return $parameters;
    }
}
