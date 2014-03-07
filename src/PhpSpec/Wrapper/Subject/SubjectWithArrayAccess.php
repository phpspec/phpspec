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

use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Subject;

use PhpSpec\Exception\Wrapper\SubjectException;

use PhpSpec\Exception\Fracture\InterfaceNotImplementedException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class SubjectWithArrayAccess
 * @package PhpSpec\Wrapper\Subject
 */
class SubjectWithArrayAccess extends TraversableSubject
{
    /**
     * @var Caller
     */
    private $caller;
    /**
     * @var \PhpSpec\Formatter\Presenter\PresenterInterface
     */
    private $presenter;
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param Caller                   $caller
     * @param PresenterInterface       $presenter
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(Caller $caller, PresenterInterface $presenter,
        EventDispatcherInterface $dispatcher)
    {
        $this->caller     = $caller;
        $this->presenter  = $presenter;
        $this->dispatcher = $dispatcher;

        parent::__construct($caller->getWrappedObject(), $presenter);
    }

    /**
     * @param string|integer $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        return isset($subject[$key]);
    }

    /**
     * @param string|integer $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        return $subject[$key];
    }

    /**
     * @param string|integer $key
     * @param mixed          $value
     */
    public function offsetSet($key, $value)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);
        $value   = $unwrapper->unwrapOne($value);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        $subject[$key] = $value;
    }

    /**
     * @param string|integer $key
     */
    public function offsetUnset($key)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        unset($subject[$key]);
    }

    /**
     * @param mixed $subject
     *
     * @throws \PhpSpec\Exception\Wrapper\SubjectException
     * @throws \PhpSpec\Exception\Fracture\InterfaceNotImplementedException
     */
    private function checkIfSubjectImplementsArrayAccess($subject)
    {
        if (is_object($subject) && !($subject instanceof \ArrayAccess)) {
            throw $this->interfaceNotImplemented('ArrayAccess');
        } elseif (!($subject instanceof \ArrayAccess) && !is_array($subject)) {
            throw $this->cantUseAsArray($subject);
        }
    }
    
    /**
     * @param mixed $subject
     *
     * @return SubjectException
     */
    private function cantUseAsArray($subject)
    {
        return new SubjectException(sprintf(
            'Can not use %s as array.', $this->presenter->presentValue($subject)
        ));
    }
}
