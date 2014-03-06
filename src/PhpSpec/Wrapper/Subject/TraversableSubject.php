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

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Subject;

use PhpSpec\Exception\Fracture\InterfaceNotImplementedException;

/**
 * Class TraversableSubject
 * @package PhpSpec\Wrapper\Subject
 */
class TraversableSubject implements \Iterator
{
    /**
     * @var PresenterInterface
     */
    private $presenter;
    /**
     * @var mixed
     */
    private $wrappedObject;

    /**
     * @param mixed              $wrappedObject
     * @param PresenterInterface $presenter
     */
    public function __construct($wrappedObject, PresenterInterface $presenter)
    {
        $this->wrappedObject = $wrappedObject;
        $this->presenter     = $presenter;
    }

    /**
     * @return mixed
     */
    public function current()
    {
        if ($this->canAccessAsArray($this->wrappedObject)) {
            return current($this->wrappedObject);
        }
        else {
            $this->checkIfSubjectImplementsTraversable();

            return $this->wrappedObject->current();
        }
    }

    /**

     * @return void
     */
    public function next()
    {
        if ($this->canAccessAsArray($this->wrappedObject)) {
            next($this->wrappedObject);
        } else {
            $this->checkIfSubjectImplementsTraversable();

            return $this->wrappedObject->next();
        }
    }

    /**
     * @return mixed
     */
    public function key()
    {
        if ($this->canAccessAsArray($this->wrappedObject)) {
            return key($this->wrappedObject);
        } else {
            $this->checkIfSubjectImplementsTraversable();

            return $this->wrappedObject->key();
        }
    }

    /**
     * @return boolean
     */
    public function valid()
    {
        if ($this->canAccessAsArray($this->wrappedObject)) {
            return current($this->wrappedObject) !== false;
        } else {
            $this->checkIfSubjectImplementsTraversable();

            return $this->wrappedObject->valid();
        }
    }

    /**
     * @return void
     */
    public function rewind()
    {
        if ($this->canAccessAsArray($this->wrappedObject)) {
            reset($this->wrappedObject);
        } else {
            $this->checkIfSubjectImplementsTraversable();

            $this->wrappedObject->rewind();
        }
    }

    /**
     * @throws \PhpSpec\Exception\Fracture\InterfaceNotImplementedException
     */
    private function checkIfSubjectImplementsTraversable()
    {
        if (!$this->wrappedObject instanceof \Traversable) {
            throw $this->interfaceNotImplemented('Traversable');
        }
    }

    /**
     * @param string $interfaceName
     *
     * @return InterfaceNotImplementedException
     */
    private function interfaceNotImplemented($interfaceName)
    {
        return new InterfaceNotImplementedException(
            sprintf('%s does not implement %s interface, but should.',
                $this->presenter->presentValue($this->wrappedObject),
                $this->presenter->presentString($interfaceName)
            ),
            $this->wrappedObject,
            $interfaceName
        );
    }

    /**
     * @param $subject
     * @return bool
     */
    private function canAccessAsArray($subject)
    {
        return is_array($subject) || $subject instanceof \ArrayAccess;
    }
}
