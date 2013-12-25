<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\Util\Instantiator;

abstract class DuringCall
{
    private $matcher;
    private $subject;
    private $arguments;
    private $wrappedObject;

    public function __construct(MatcherInterface $matcher)
    {
        $this->matcher = $matcher;
    }

    public function match($alias, $subject, array $arguments = array(), $wrappedObject = null)
    {
        $this->subject = $subject;
        $this->arguments = $arguments;
        $this->wrappedObject = $wrappedObject;

        return $this;
    }
    
    public function during($method, array $arguments = array())
    {
        if ($method === '__construct') {
            $this->subject->beAnInstanceOf($this->wrappedObject->getClassname(), $arguments);
            $instantiator = new Instantiator;
            $object = $instantiator->instantiate($this->wrappedObject->getClassname());
        } else {
            $class = $this->wrappedObject->getClassname();
            $constructionArguments = $this->wrappedObject->getArguments();

            $reflection = new \ReflectionClass($class);

            if (!empty($constructionArguments)) {
                $object = $reflection->newInstanceArgs($constructionArguments);
            } else {
                $object = $reflection->newInstance();
            }
        }

        return $this->runDuring($object, $method, $arguments);
    }
    
    public function __call($method, array $arguments = array())
    {
        if (strpos($method, 'during') === false) {
            throw new MatcherException('Incorrect usage of matcher Throw, either prefix the method with "during" and capitalize the first character of the method or use ->during(\'callable\', array(arguments)).' .PHP_EOL. 'E.g.'.PHP_EOL.'->during' . ucfirst($method) . '(arguments)'.PHP_EOL.'or'.PHP_EOL.'->during(\'' . $method . '\', array(arguments))');
        }

        if (preg_match('/^during(.+)$/', $method, $matches)) {
            return $this->during(lcfirst($matches[1]), $arguments);
        }
    }

    protected function getArguments()
    {
        return $this->arguments;
    }

    protected function getMatcher()
    {
        return $this->matcher;
    }

    abstract protected function runDuring($object, $method, array $arguments = array());
}
