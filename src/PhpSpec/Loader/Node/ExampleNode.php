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

namespace PhpSpec\Loader\Node;

use ReflectionFunctionAbstract;

/**
 * Class ExampleNode
 * @package PhpSpec\Loader\Node
 */
class ExampleNode
{
    /**
     * @var
     */
    private $title;
    /**
     * @var \ReflectionFunctionAbstract
     */
    private $function;
    /**
     * @var
     */
    private $specification;
    /**
     * @var bool
     */
    private $isPending = false;

    /**
     * @param $title
     * @param ReflectionFunctionAbstract $function
     */
    public function __construct($title, ReflectionFunctionAbstract $function)
    {
        $this->title    = $title;
        $this->function = $function;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param bool $isPending
     */
    public function markAsPending($isPending = true)
    {
        $this->isPending = $isPending;
    }

    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->isPending;
    }

    /**
     * @return ReflectionFunctionAbstract
     */
    public function getFunctionReflection()
    {
        return $this->function;
    }

    /**
     * @param SpecificationNode $specification
     */
    public function setSpecification(SpecificationNode $specification)
    {
        $this->specification = $specification;
    }

    /**
     * @return mixed
     */
    public function getSpecification()
    {
        return $this->specification;
    }
}
