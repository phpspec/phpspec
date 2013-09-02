<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Subject;

use PhpSpec\Exception\Wrapper\SubjectException;

use PhpSpec\Exception\Fracture\InterfaceNotImplementedException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubjectWithArrayAccess
{
    private $caller;
    private $presenter;
    private $dispatcher;

    public function __construct(Caller $caller, PresenterInterface $presenter,
        EventDispatcherInterface $dispatcher)
    {
        $this->caller     = $caller;
        $this->presenter  = $presenter;
        $this->dispatcher = $dispatcher;
    }

    public function offsetExists($key)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        return isset($subject[$key]);
    }

    public function offsetGet($key)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        return $subject[$key];
    }

    public function offsetSet($key, $value)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);
        $value   = $unwrapper->unwrapOne($value);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        $subject[$key] = $value;
    }

    public function offsetUnset($key)
    {
        $unwrapper = new Unwrapper;
        $subject = $this->caller->getWrappedObject();
        $key     = $unwrapper->unwrapOne($key);

        $this->checkIfSubjectImplementsArrayAccess($subject);

        unset($subject[$key]);
    }

    private function checkIfSubjectImplementsArrayAccess($subject)
    {
        if (is_object($subject) && !($subject instanceof \ArrayAccess)) {
            throw $this->interfaceNotImplemented();
        } elseif (!($subject instanceof \ArrayAccess) && !is_array($subject)) {
            throw $this->cantUseAsArray($subject);
        }
    }

    private function interfaceNotImplemented()
    {
        return new InterfaceNotImplementedException(
            sprintf('%s does not implement %s interface, but should.',
                $this->presenter->presentValue($this->caller->getWrappedObject()),
                $this->presenter->presentString('ArrayAccess')
            ),
            $this->caller->getWrappedObject(),
            'ArrayAccess'
        );
    }

    private function cantUseAsArray($subject)
    {
        return new SubjectException(sprintf(
            'Can not use %s as array.', $this->presenter->presentValue($subject)
        ));
    }
}
