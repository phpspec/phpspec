<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Subject;

use PhpSpec\Exception\Wrapper\SubjectException;

use PhpSpec\Exception\Fracture\InterfaceNotImplementedException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ArrayAccess
{
    private $caller;
    private $unwrapper;
    private $presenter;
    private $matchers;
    private $dispatcher;

    public function __construct(Caller $caller, Unwrapper $unwrapper, PresenterInterface $presenter, MatcherManager $matchers, EventDispatcherInterface $dispatcher)
    {
        $this->caller     = $caller;
        $this->unwrapper  = $unwrapper;
        $this->presenter  = $presenter;
        $this->matchers   = $matchers;
        $this->dispatcher = $dispatcher;
    }
    
    public function offsetExists($key)
    {
        $subject = $this->caller->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);

        $this->validateInterface($subject);

        return isset($subject[$key]);
    }

    public function offsetGet($key)
    {
        $subject = $this->caller->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);

        $this->validateInterface($subject);

        return $subject[$key];
    }

    public function offsetSet($key, $value)
    {
        $subject = $this->caller->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);
        $value   = $this->unwrapper->unwrapOne($value);

        $this->validateInterface($subject);

        $subject[$key] = $value;
    }

    public function offsetUnset($key)
    {
        $subject = $this->caller->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);

        $this->validateInterface($subject);

        unset($subject[$key]);
    }

    private function validateInterface($subject)
    {
        if (is_object($subject) && !($subject instanceof ArrayAccess)) {
            throw $this->interfaceNotImplemented();
        } elseif (!($subject instanceof ArrayAccess) && !is_array($subject)) {
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
